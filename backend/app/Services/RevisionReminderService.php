<?php

namespace App\Services;

use App\Models\Device;
use App\Models\ResourceRevisionReminder;
use App\Models\ResourceRevisionState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RevisionReminderService
{
    public const PRESETS = ['one_hour', 'three_hours', 'tomorrow', 'none'];

    public function __construct(private ResourceRevisionStateService $states, private ReminderTimeResolver $times) {}

    public function schedule(User $user, string $type, int|string $id, string $preset): array
    {
        if (! in_array($preset, self::PRESETS, true)) {
            throw ValidationException::withMessages(['preset' => ['Unsupported reminder preset.']]);
        }$statePayload = $this->states->state($user, $type, $id);
        if (! $statePayload['updateAvailable']) {
            throw ValidationException::withMessages(['reminder' => ['No update is currently available.']]);
        }$state = ResourceRevisionState::where(['resource_type' => $type, 'resource_id' => $id, 'user_id' => $user->id])->firstOrFail();
        $at = $this->times->forPreset($user, $preset);
        $lifecycleGeneration = app(ResourceLifecycleService::class)->generation($type, $id);
        $reminder = DB::transaction(function () use ($state, $statePayload, $preset, $at, $lifecycleGeneration) {
            $existing = ResourceRevisionReminder::where('resource_revision_state_id', $state->id)->lockForUpdate()->first();
            $values = ['target_revision_id' => $statePayload['latestRevision']['id'], 'preset' => $preset === 'none' ? null : $preset, 'remind_at' => $at, 'status' => $preset === 'none' ? 'none' : 'pending', 'generation' => ($existing?->generation ?? 0) + 1, 'lifecycle_generation' => $lifecycleGeneration, 'sent_at' => null];

            return $existing ? tap($existing)->update($values) : ResourceRevisionReminder::create(['resource_revision_state_id' => $state->id, ...$values]);
        });

        return $this->payload($reminder->refresh(), $user);
    }

    public function status(User $user, string $type, int|string $id): array
    {
        $this->states->state($user, $type, $id);
        $state = ResourceRevisionState::where(['resource_type' => $type, 'resource_id' => $id, 'user_id' => $user->id])->firstOrFail();
        $r = ResourceRevisionReminder::where('resource_revision_state_id', $state->id)->first();

        return $r ? $this->payload($r, $user) : $this->empty($user);
    }

    public function cancelForState(ResourceRevisionState $state, string $status = 'cancelled'): void
    {
        DB::transaction(function () use ($state, $status) {
            $r = ResourceRevisionReminder::where('resource_revision_state_id', $state->id)->lockForUpdate()->first();
            if ($r && in_array($r->status, ['pending', 'none'], true)) {
                $r->update(['status' => $status, 'remind_at' => null, 'generation' => $r->generation + 1]);
            }
        });
    }

    public function cancel(User $user, string $type, int|string $id): void
    {
        $this->states->state($user, $type, $id);
        $state = ResourceRevisionState::where(['resource_type' => $type, 'resource_id' => $id, 'user_id' => $user->id])->firstOrFail();
        $this->cancelForState($state);
    }

    public function processDue(int $limit = 100): int
    {
        $ids = ResourceRevisionReminder::where('status', 'pending')->where('remind_at', '<=', now())->orderBy('remind_at')->limit($limit)->pluck('id');
        $sent = 0;
        foreach ($ids as $id) {
            if ($this->processOne($id)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function processOne(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $r = ResourceRevisionReminder::with(['revisionState.user'])->lockForUpdate()->find($id);
            if (! $r || $r->status !== 'pending' || $r->remind_at?->isFuture()) {
                return false;
            }

            $state = $r->revisionState;
            $user = $state?->user;
            if (! $user || $user->status !== 'active') {
                return $this->resolve($r, 'cancelled');
            }

            if (! app(ResourceLifecycleService::class)->allowsGeneration($state->resource_type, $state->resource_id, (int) $r->lifecycle_generation)) {
                return $this->resolve($r, 'cancelled');
            }

            try {
                $payload = $this->states->state($user, $state->resource_type, $state->resource_id);
            } catch (\Throwable) {
                return $this->resolve($r, 'cancelled');
            }

            if (! $payload['updateAvailable']) {
                return $this->resolve($r, 'resolved');
            }

            $device = Device::find($state->resource_id);
            if (! $device) {
                return $this->resolve($r, 'cancelled');
            }
            app(NotificationMaterializationService::class)->createOnce(
                $user,
                'revision.available',
                "reminder:{$r->id}:generation:{$r->generation}",
                [
                    'organization_id' => $user->organization_id,
                    'resource_type' => 'device',
                    'resource_id' => $device->id,
                    'action_url' => "/app/devices/{$device->id}?tab=dashboard",
                    'data' => ['latest_revision_id' => $payload['latestRevision']['id'], 'reminder' => true],
                    'requires_action' => true,
                    'action_state' => 'pending',
                    'title' => 'Update still available',
                    'message' => "Update still available for {$device->name}.",
                    'severity' => 'info',
                ],
                true,
            );
            $r->update(['status' => 'sent', 'sent_at' => now()]);

            return true;
        });
    }

    private function resolve(ResourceRevisionReminder $r, string $status): bool
    {
        $r->update(['status' => $status, 'remind_at' => null, 'generation' => $r->generation + 1]);

        return false;
    }

    private function payload(ResourceRevisionReminder $r, User $user): array
    {
        return ['reminderPreset' => $r->preset, 'remindAt' => $r->remind_at?->toISOString(), 'isSnoozed' => $r->status === 'pending' && $r->remind_at?->isFuture(), 'status' => $r->status, 'generation' => $r->generation, 'effectiveTimezone' => $this->times->timezone($user)];
    }

    private function empty(User $user): array
    {
        return ['reminderPreset' => null, 'remindAt' => null, 'isSnoozed' => false, 'status' => null, 'generation' => 0, 'effectiveTimezone' => $this->times->timezone($user)];
    }
}
