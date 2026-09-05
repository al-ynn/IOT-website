<?php

namespace App\Services;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Collaboration\ResourceCapabilityRegistry;
use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\Notification;
use App\Models\ResourceLifecycleState;
use App\Models\ResourceRevision;
use App\Models\ResourceRevisionReminder;
use App\Models\ResourceDraft;
use App\Models\ResourceRevisionState;
use App\Models\User;
use App\Revisions\ResourceUpdatePolicyRegistry;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ResourceRevisionStateService
{
    public function __construct(
        private CollaborationResourceRegistry $registry,
        private ResourceUpdatePolicyRegistry $policies,
        private ResourceCapabilityRegistry $capabilities,
        private ResourceLifecycleService $lifecycle,
    ) {}

    public function hasHistory(string $type, int|string $id): bool
    {
        return ResourceRevision::query()->where('resource_type', $type)->where('resource_id', $id)->exists();
    }

    public function state(User $user, string $type, int|string $id): array
    {
        $resource = $this->resolve($user, $type, $id);
        $latest = $this->latest($type, $resource->getKey());
        abort_unless($latest, 404);
        $state = $this->initialize($user, $type, (int) $resource->getKey(), $latest);

        return $this->payload($state, $latest);
    }

    public function pull(User $user, string $type, int|string $id): array
    {
        return DB::transaction(function () use ($user, $type, $id) {
            $resource = $this->resolve($user, $type, $id);
            $latest = $this->latest($type, $resource->getKey(), true);
            abort_unless($latest, 404);
            abort_if(ResourceDraft::query()->where([
                'resource_type' => $type,
                'resource_id' => $resource->getKey(),
                'user_id' => $user->id,
            ])->exists(), 409, 'Save or discard your Draft before Pulling this update.');

            $this->initialize($user, $type, (int) $resource->getKey(), $latest);
            $state = ResourceRevisionState::query()->where([
                'resource_type' => $type,
                'resource_id' => $resource->getKey(),
                'user_id' => $user->id,
            ])->lockForUpdate()->firstOrFail();
            $accepted = $this->acceptedFor($state);

            abort_if($accepted->revision_number > $latest->revision_number, 409, 'Accepted revision state is invalid.');
            if ($accepted->revision_number < $latest->revision_number) {
                $state->update([
                    'accepted_revision_id' => $latest->id,
                    'seen_latest_revision_id' => $latest->id,
                    'last_reviewed_revision_id' => $latest->id,
                    'ignored_revision_id' => null,
                ]);
            }

            app(RevisionReminderService::class)->cancelForState($state, 'resolved');
            Notification::query()->where('user_id', $user->id)
                ->where('type', 'revision.available')
                ->where('resource_type', $type)
                ->where('resource_id', $resource->getKey())
                ->update(['requires_action' => false, 'action_state' => 'resolved']);

            return $this->payload($state->refresh(), $latest);
        });
    }

    public function ignore(User $user, string $type, int|string $id): array
    {
        return DB::transaction(function () use ($user, $type, $id) {
            $resource = $this->resolve($user, $type, $id);
            $latest = $this->latest($type, $resource->getKey(), true);
            abort_unless($latest, 404);
            $state = $this->initialize($user, $type, (int) $resource->getKey(), $latest);
            abort_unless($this->updateAvailable($state, $latest), 409, 'No update is currently available.');

            $state->update([
                'seen_latest_revision_id' => $latest->id,
                'ignored_revision_id' => $latest->id,
            ]);
            Notification::query()->where('user_id', $user->id)
                ->where('type', 'revision.available')
                ->where('resource_type', $type)
                ->where('resource_id', $resource->getKey())
                ->update(['dismissed_at' => now(), 'action_state' => 'deferred']);

            return $this->payload($state->refresh(), $latest);
        });
    }

    public function review(User $user, string $type, int|string $id): array
    {
        $resource = $this->resolve($user, $type, $id);
        $latest = $this->latest($type, $resource->getKey());
        abort_unless($latest, 404);
        $state = $this->initialize($user, $type, (int) $resource->getKey(), $latest);
        $accepted = $this->acceptedFor($state);
        $rows = ResourceRevision::query()
            ->where('resource_type', $type)
            ->where('resource_id', $resource->getKey())
            ->whereBetween('revision_number', [$accepted->revision_number + 1, $latest->revision_number])
            ->with('author:id,name')
            ->orderBy('revision_number')
            ->get();

        $state->update(['seen_latest_revision_id' => $latest->id, 'last_reviewed_revision_id' => $latest->id]);

        return [
            'fromRevision' => $this->revision($accepted),
            'toRevision' => $this->revision($latest),
            'revisions' => $rows->map(fn (ResourceRevision $revision) => $this->revision($revision))->values(),
            'pullableChangedSections' => $this->pullableSections($type, $rows->flatMap(fn (ResourceRevision $revision) => $revision->changed_sections)->unique()->values()->all()),
        ];
    }

    public function assertLatestForEdit(User $user, Device $device): void
    {
        $latest = $this->latest('device', $device->id);
        if (! $latest) {
            return;
        }
        $state = $this->initialize($user, 'device', $device->id, $latest);
        if ($this->updateAvailable($state, $latest)) {
            abort(409, 'Update available. Pull the latest revision before editing.');
        }
    }

    public function acceptedSnapshot(User $user, Device $device): ?array
    {
        $latest = $this->latest('device', $device->id);
        if (! $latest) {
            return null;
        }
        $state = $this->initialize($user, 'device', $device->id, $latest);

        return $this->acceptedFor($state)->snapshot;
    }

    public function initializeGrant(User $user, Device $device): void
    {
        $latest = $this->latest('device', $device->id);
        if (! $latest) {
            return;
        }

        $state = ResourceRevisionState::query()->updateOrCreate([
            'resource_type' => 'device',
            'resource_id' => $device->id,
            'user_id' => $user->id,
        ], [
            'accepted_revision_id' => $latest->id,
            'seen_latest_revision_id' => $latest->id,
            'last_reviewed_revision_id' => $latest->id,
            'ignored_revision_id' => null,
        ]);

        app(RevisionReminderService::class)->cancelForState($state, 'cancelled');
        Notification::query()->where('user_id', $user->id)
            ->where('type', 'revision.available')
            ->where('resource_type', 'device')
            ->where('resource_id', $device->id)
            ->update(['requires_action' => false, 'action_state' => 'superseded']);
    }

    public function revisionCreated(Device $device, ResourceRevision $revision, ?User $actor): void
    {
        if (! $this->isPullableRevision($revision)) {
            return;
        }
        $previous = $revision->parent_revision_id ? ResourceRevision::find($revision->parent_revision_id) : $revision;
        if ($actor) {
            $this->advanceAuthor($actor, $device, $revision);
        }

        $users = DeviceAccessAssignment::query()->where('device_id', $device->id)->with('user')->get()
            ->pluck('user')->filter(fn ($user) => $user && $user->status === 'active' && (! $actor || $user->id !== $actor->id));
        foreach ($users as $user) {
            $state = ResourceRevisionState::query()->firstOrCreate([
                'resource_type' => 'device',
                'resource_id' => $device->id,
                'user_id' => $user->id,
            ], [
                'accepted_revision_id' => $previous->id,
                'seen_latest_revision_id' => $previous->id,
                'last_reviewed_revision_id' => $previous->id,
            ]);
            $this->acceptedFor($state);
            if ($this->updateAvailable($state, $revision)) {
                $this->notify($user, $actor, $device, $revision);
            }
        }
    }

    public function changes(User $user, array $filters = []): LengthAwarePaginator
    {
        abort_unless($user->status === 'active' && $user->organization_id, 403);
        $authorizedIds = DeviceAccessAssignment::query()->where('user_id', $user->id)
            ->whereHas('device', fn ($query) => $query->where('organization_id', $user->organization_id))
            ->pluck('device_id');
        $inactiveIds = ResourceLifecycleState::query()->where('resource_type', 'device')
            ->where('state', '!=', 'active')->whereIn('resource_id', $authorizedIds)->pluck('resource_id');
        $authorizedIds = $authorizedIds->diff($inactiveIds)->values();

        $page = ResourceRevisionState::query()
            ->where('user_id', $user->id)
            ->where('resource_type', 'device')
            ->whereIn('resource_id', $authorizedIds)
            ->when($filters['q'] ?? null, function ($query, string $value) {
                $escaped = addcslashes($value, '%_');
                $query->whereIn('resource_id', Device::query()->select('id')->where(fn ($device) => $device
                    ->where('name', 'like', "%{$escaped}%")->orWhereRaw('CAST(id AS TEXT) LIKE ?', ["%{$escaped}%"])));
            })
            ->when($filters['lifecycle'] ?? null, function ($query, string $lifecycle) {
                $query->whereIn('resource_id', Device::query()->select('devices.id')
                    ->leftJoin('resource_lifecycle_states as lifecycle', fn ($join) => $join->on('lifecycle.resource_id', '=', 'devices.id')->where('lifecycle.resource_type', 'device'))
                    ->whereRaw("COALESCE(lifecycle.state, 'active') = ?", [$lifecycle]));
            })
            ->whereExists(function ($query) {
                $query->selectRaw('1')->from('resource_revisions as rr')
                    ->join('resource_revisions as accepted', 'accepted.id', '=', 'resource_revision_states.accepted_revision_id')
                    ->whereColumn('rr.resource_type', 'resource_revision_states.resource_type')
                    ->whereColumn('rr.resource_id', 'resource_revision_states.resource_id')
                    ->whereColumn('rr.revision_number', '>', 'accepted.revision_number')
                    ->whereJsonContains('rr.changed_sections', 'dashboard');
            })
            ->with('acceptedRevision.author:id,name')
            ->orderByDesc(ResourceRevision::query()->select('created_at')
                ->whereColumn('resource_type', 'resource_revision_states.resource_type')
                ->whereColumn('resource_id', 'resource_revision_states.resource_id')
                ->orderByDesc('revision_number')->limit(1))
            ->paginate($filters['per_page'] ?? 20);

        $ids = $page->getCollection()->pluck('resource_id');
        $latestByResource = ResourceRevision::query()->where('resource_type', 'device')->whereIn('resource_id', $ids)
            ->with('author:id,name')->orderByDesc('revision_number')->get()->unique('resource_id')->keyBy('resource_id');
        $devices = Device::query()->whereIn('id', $ids)->get(['id', 'name'])->keyBy('id');
        $lifecycles = ResourceLifecycleState::query()->where('resource_type', 'device')->whereIn('resource_id', $ids)->pluck('state', 'resource_id');

        $page->setCollection($page->getCollection()->map(function (ResourceRevisionState $state) use ($latestByResource, $devices, $lifecycles) {
            $latest = $latestByResource->get($state->resource_id);
            $device = $devices->get($state->resource_id);
            abort_unless($latest && $device, 404);

            $payload = $this->payload($state, $latest);
            $lifecycle = $lifecycles->get($state->resource_id, 'active');
            $pending = max(0, $latest->revision_number - $state->acceptedRevision->revision_number);

            return [...$payload, 'lifecycle' => $lifecycle, 'pendingRevisionCount' => $pending, 'actions' => [
                'canReview' => true, 'canPull' => $lifecycle === 'active', 'canIgnore' => $lifecycle === 'active', 'canRemind' => $lifecycle === 'active',
            ], 'resource' => [
                'id' => (string) $device->id,
                'name' => $device->name,
                'type' => 'device',
            ]];
        }));

        return $page;
    }

    public function changesCount(User $user): int { return $this->changes($user, ['per_page' => 1])->total(); }

    public function changesPreview(User $user, int $limit = 5): array
    {
        return $this->changes($user, ['per_page' => min(5, max(1, $limit))])->items();
    }

    private function resolve(User $user, string $type, int|string $id)
    {
        abort_unless($this->policies->isPullManaged($type), 404);
        $resolved = $this->registry->resolve($user, new CollaborationResourceReference($type, $id), 'view');
        abort_unless($this->capabilities->allows($user, $type, $resolved->resource, 'canPullUpdate'), 403);
        $this->lifecycle->assertActive($type, $resolved->resource->getKey(), 'Updates are unavailable while this resource is not Active.');

        return $resolved->resource;
    }

    private function latest(string $type, int|string $id, bool $lock = false): ?ResourceRevision
    {
        $query = ResourceRevision::query()->where('resource_type', $type)->where('resource_id', $id)->orderByDesc('revision_number');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function initialize(User $user, string $type, int $id, ResourceRevision $latest): ResourceRevisionState
    {
        $state = ResourceRevisionState::query()->firstOrCreate([
            'resource_type' => $type,
            'resource_id' => $id,
            'user_id' => $user->id,
        ], [
            'accepted_revision_id' => $latest->id,
            'seen_latest_revision_id' => $latest->id,
            'last_reviewed_revision_id' => $latest->id,
        ]);
        $this->acceptedFor($state);

        return $state;
    }

    private function acceptedFor(ResourceRevisionState $state): ResourceRevision
    {
        $accepted = ResourceRevision::query()->find($state->accepted_revision_id);
        abort_unless(
            $accepted
            && $accepted->resource_type === $state->resource_type
            && (string) $accepted->resource_id === (string) $state->resource_id,
            409,
            'Accepted revision state is invalid.'
        );
        $state->setRelation('acceptedRevision', $accepted);

        return $accepted;
    }

    private function advanceAuthor(User $user, Device $device, ResourceRevision $revision): void
    {
        ResourceRevisionState::query()->updateOrCreate([
            'resource_type' => 'device',
            'resource_id' => $device->id,
            'user_id' => $user->id,
        ], [
            'accepted_revision_id' => $revision->id,
            'seen_latest_revision_id' => $revision->id,
            'last_reviewed_revision_id' => $revision->id,
            'ignored_revision_id' => null,
        ]);
    }

    private function updateAvailable(ResourceRevisionState $state, ResourceRevision $latest): bool
    {
        $accepted = $this->acceptedFor($state);
        if ($accepted->revision_number >= $latest->revision_number) {
            return false;
        }

        return ResourceRevision::query()->where('resource_type', $state->resource_type)
            ->where('resource_id', $state->resource_id)
            ->where('revision_number', '>', $accepted->revision_number)
            ->whereJsonContains('changed_sections', 'dashboard')->exists();
    }

    private function isPullableRevision(ResourceRevision $revision): bool
    {
        return count($this->pullableSections($revision->resource_type, $revision->changed_sections)) > 0;
    }

    private function pullableSections(string $type, array $sections): array
    {
        return array_values(array_intersect($sections, $this->policies->pullableSections($type)));
    }

    private function payload(ResourceRevisionState $state, ResourceRevision $latest): array
    {
        $accepted = $this->acceptedFor($state);
        $sections = ResourceRevision::query()->where('resource_type', $state->resource_type)
            ->where('resource_id', $state->resource_id)
            ->where('revision_number', '>', $accepted->revision_number)
            ->get(['changed_sections'])->flatMap(fn (ResourceRevision $revision) => $revision->changed_sections)
            ->unique()->values()->all();

        return [
            'resourceType' => $state->resource_type,
            'resourceId' => (string) $state->resource_id,
            'acceptedRevision' => $this->revision($accepted),
            'latestRevision' => $this->revision($latest),
            'updateAvailable' => $this->updateAvailable($state, $latest),
            'pullableChangedSections' => $this->pullableSections($state->resource_type, $sections),
            'ignoredRevisionId' => $state->ignored_revision_id ? (string) $state->ignored_revision_id : null,
        ];
    }

    private function revision(ResourceRevision $revision): array
    {
        $revision->loadMissing('author:id,name');

        return [
            'id' => (string) $revision->id,
            'revisionNumber' => $revision->revision_number,
            'createdBy' => $revision->author ? ['id' => (string) $revision->author->id, 'name' => $revision->author->name] : null,
            'createdAt' => $revision->created_at->toISOString(),
            'changedSections' => $revision->changed_sections,
            'changeSummary' => $revision->change_summary,
        ];
    }

    private function notify(User $user, ?User $actor, Device $device, ResourceRevision $revision): void
    {
        if (! app(NotificationPreferenceService::class)->enabled($user, 'revision.available')) {
            return;
        }
        $state = ResourceRevisionState::query()->where([
            'resource_type' => 'device',
            'resource_id' => $device->id,
            'user_id' => $user->id,
        ])->first();
        $snoozed = $state && ResourceRevisionReminder::query()
            ->where('resource_revision_state_id', $state->id)->where('status', 'pending')->where('remind_at', '>', now())->exists();
        $notification = Notification::firstOrNew(['deduplication_key' => "revision.available:device:$device->id:$user->id"]);
        $notification->fill([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'type' => 'revision.available',
            'schema_version' => 1,
            'category' => 'updates',
            'actor_id' => $actor?->id,
            'resource_type' => 'device',
            'resource_id' => $device->id,
            'action_url' => "/app/devices/$device->id?tab=dashboard",
            'data' => ['latest_revision_id' => $revision->id],
            'requires_action' => true,
            'action_state' => $snoozed ? 'snoozed' : 'pending',
            'title' => 'Update available',
            'message' => ($actor?->name ?? 'A collaborator')." updated $device->name.",
            'severity' => 'info',
        ]);
        if (! $snoozed) {
            $notification->read_at = null;
        }
        $notification->save();
    }
}
