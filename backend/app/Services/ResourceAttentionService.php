<?php

namespace App\Services;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\DeviceTemplate;
use App\Models\Notification;
use App\Models\ResourceAttentionState;
use App\Models\ResourceCollaborator;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResourceAttentionService
{
    public const TYPES = ['device', 'device_template'];

    public const REASONS = ['configuration_follow_up', 'operational_follow_up', 'governance_follow_up', 'data_quality_follow_up', 'other'];

    public const LABELS = ['configuration_follow_up' => 'Configuration Follow-up', 'operational_follow_up' => 'Operational Follow-up', 'governance_follow_up' => 'Governance Follow-up', 'data_quality_follow_up' => 'Data Quality Follow-up', 'other' => 'Other'];

    public function __construct(private CollaborationResourceRegistry $registry) {}

    public function mark(User $admin, string $type, int $id, string $reason, string $note): ResourceAttentionState
    {
        $resource = $this->adminResource($admin, $type, $id);
        $this->validate($reason, $note);
        $notify = false;
        $state = DB::transaction(function () use ($admin, $type, $id, $reason, $note, &$notify) {
            $state = ResourceAttentionState::where(['resource_type' => $type, 'resource_id' => $id])->lockForUpdate()->first();
            if ($state?->status === 'open') {
                return $state;
            }$notify = true;
            $now = now();
            if (! $state) {
                $state = new ResourceAttentionState(['resource_type' => $type, 'resource_id' => $id]);
            }$state->fill(['status' => 'open', 'reason_code' => $reason, 'note' => $note, 'marked_by' => $admin->id, 'marked_at' => $now, 'updated_by' => null, 'resolved_by' => null, 'resolved_at' => null])->save();

            return $state;
        });
        if ($notify) {
            $this->notifyOpen($state, $resource, $admin);
        }

        return $state->fresh(['marker:id,name,status', 'updater:id,name,status']);
    }

    public function update(User $admin, string $type, int $id, string $reason, string $note): ResourceAttentionState
    {
        $this->adminResource($admin, $type, $id);
        $this->validate($reason, $note);

        return DB::transaction(function () use ($admin, $type, $id, $reason, $note) {
            $state = ResourceAttentionState::where(['resource_type' => $type, 'resource_id' => $id])->lockForUpdate()->firstOrFail();
            abort_unless($state->status === 'open', 409, 'Needs Attention is already cleared.');
            $state->update(['reason_code' => $reason, 'note' => $note, 'updated_by' => $admin->id]);

            return $state->fresh(['marker:id,name,status', 'updater:id,name,status']);
        });
    }

    public function clear(User $admin, string $type, int $id): ResourceAttentionState
    {
        $this->adminResource($admin, $type, $id);
        $state = DB::transaction(function () use ($admin, $type, $id) {
            $state = ResourceAttentionState::where(['resource_type' => $type, 'resource_id' => $id])->lockForUpdate()->firstOrFail();
            if ($state->status === 'resolved') {
                return $state;
            }$state->update(['status' => 'resolved', 'resolved_by' => $admin->id, 'resolved_at' => now()]);

            return $state;
        });
        Notification::where(['type' => 'resource.needs_attention', 'resource_type' => $type, 'resource_id' => $id])->whereNull('action_state')->update(['action_state' => 'resolved']);

        return $state->fresh(['marker:id,name,status', 'updater:id,name,status', 'resolver:id,name,status']);
    }

    public function show(User $user, string $type, int $id): ?array
    {
        $resolved = $this->registry->resolve($user, new CollaborationResourceReference($type, $id), 'view');
        $state = ResourceAttentionState::where(['resource_type' => $type, 'resource_id' => $id, 'status' => 'open'])->with('marker:id,name,status')->first();

        return $state ? $this->data($state, $resolved->resource, $user) : null;
    }

    public function queue(User $admin, array $filters): LengthAwarePaginator
    {
        abort_unless($admin->isPlatformAdmin(), 403);
        $this->validateFilters($filters);
        $query = ResourceAttentionState::where('status', 'open')->whereIn('resource_type', self::TYPES)->with(['marker:id,name,status', 'updater:id,name,status']);
        if (isset($filters['resource_type'])) {
            $query->where('resource_type', $filters['resource_type']);
        }if (isset($filters['reason'])) {
            $query->where('reason_code', $filters['reason']);
        }if (isset($filters['marked_by'])) {
            $query->where('marked_by', $filters['marked_by']);
        }$this->resourceFilters($query, $filters);
        ($filters['sort'] ?? 'newest') === 'oldest' ? $query->oldest('marked_at')->oldest('id') : $query->latest('marked_at')->latest('id');
        $page = $query->paginate((int) ($filters['per_page'] ?? 25));

        return $this->hydratePage($page, $admin);
    }

    public function count(User $admin): int
    {
        abort_unless($admin->isPlatformAdmin(), 403);

        return ResourceAttentionState::where('status', 'open')->whereIn('resource_type', self::TYPES)->count();
    }

    public function myWork(User $user, array $filters): LengthAwarePaginator
    {
        $deviceIds = DeviceAccessAssignment::where(['user_id' => $user->id, 'access_level' => 'full_access'])->select('device_id');
        $templateIds = ResourceCollaborator::where(['user_id' => $user->id, 'resource_type' => 'device_template', 'permission' => 'edit'])->select('resource_id');
        $query = ResourceAttentionState::where('status', 'open')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('resource_lifecycle_states as rls')->whereColumn('rls.resource_type', 'resource_attention_states.resource_type')->whereColumn('rls.resource_id', 'resource_attention_states.resource_id')->whereIn('rls.state', ['disabled', 'archived']))->where(fn ($q) => $q->where(fn ($x) => $x->where('resource_type', 'device')->whereIn('resource_id', $deviceIds))->orWhere(fn ($x) => $x->where('resource_type', 'device_template')->whereIn('resource_id', $templateIds)))->with('marker:id,name,status');
        if (isset($filters['resource_type'])) {
            if (! in_array($filters['resource_type'], self::TYPES, true)) {
                throw ValidationException::withMessages(['resource_type' => ['Unsupported attention resource type.']]);
            }$query->where('resource_type', $filters['resource_type']);
        }$page = $query->latest('marked_at')->latest('id')->paginate((int) ($filters['per_page'] ?? 20));

        return $this->hydratePage($page, $user);
    }

    private function hydratePage(LengthAwarePaginator $page, User $viewer): LengthAwarePaginator
    {
        $rows = collect($page->items());
        $devices = Device::whereIn('id', $rows->where('resource_type', 'device')->pluck('resource_id'))->with('organization:id,name')->get()->keyBy('id');
        $templates = DeviceTemplate::whereIn('id', $rows->where('resource_type', 'device_template')->pluck('resource_id'))->with('organization:id,name')->get()->keyBy('id');
        $page->setCollection($rows->map(function ($state) use ($devices, $templates, $viewer) {
            $resource = $state->resource_type === 'device' ? $devices->get($state->resource_id) : $templates->get($state->resource_id);

            return $resource ? $this->data($state, $resource, $viewer) : null;
        })->filter()->values());

        return $page;
    }

    private function data(ResourceAttentionState $state, $resource, User $viewer): array
    {
        $active = app(ResourceLifecycleService::class)->state($state->resource_type, $state->resource_id) === 'active';
        $canAct = $active && $this->registry->definition($state->resource_type)->authorizer->canEdit($viewer, $resource);

        return ['attentionId' => (string) $state->id, 'resourceType' => $state->resource_type, 'resourceId' => (string) $state->resource_id, 'resourceLabel' => $resource->name, 'organization' => $resource->organization ? ['id' => (string) $resource->organization->id, 'name' => $resource->organization->name] : null, 'reasonCode' => $state->reason_code, 'reasonLabel' => self::LABELS[$state->reason_code], 'note' => $state->note, 'status' => $state->status, 'markedBy' => $state->marker ? ['id' => (string) $state->marker->id, 'name' => $state->marker->name, 'inactive' => $state->marker->status !== 'active'] : null, 'markedAt' => $state->marked_at?->toISOString(), 'updatedAt' => $state->updated_at?->toISOString(), 'resourceLifecycle' => app(ResourceLifecycleService::class)->state($state->resource_type, $state->resource_id), 'capabilities' => ['canUpdate' => $viewer->isPlatformAdmin(), 'canClear' => $viewer->isPlatformAdmin(), 'canAct' => $canAct], 'adminDeepLink' => $state->resource_type === 'device' ? "/admin/devices/{$resource->id}" : "/admin/templates/{$resource->id}", 'appDeepLink' => $state->resource_type === 'device' ? "/app/devices/{$resource->id}" : "/app/developer/templates/{$resource->id}"];
    }

    private function adminResource(User $admin, string $type, int $id)
    {
        abort_unless($admin->isPlatformAdmin(), 403);
        if (! in_array($type, self::TYPES, true)) {
            throw ValidationException::withMessages(['resource_type' => ['Unsupported attention resource type.']]);
        }$definition = $this->registry->definition($type);
        abort_unless(($definition->capabilities['needsAttention'] ?? false) === true, 422);

        return $this->registry->resolve($admin, new CollaborationResourceReference($type, $id), 'administer')->resource;
    }

    private function validate(string $reason, string $note): void
    {
        if (! in_array($reason, self::REASONS, true)) {
            throw ValidationException::withMessages(['reason_code' => ['Unsupported attention reason.']]);
        }if (trim($note) === '' || mb_strlen($note) > 1000) {
            throw ValidationException::withMessages(['note' => ['Attention note is required and may not exceed 1000 characters.']]);
        }
    }

    private function validateFilters(array $filters): void
    {
        if (isset($filters['resource_type']) && ! in_array($filters['resource_type'], self::TYPES, true)) {
            throw ValidationException::withMessages(['resource_type' => ['Unsupported attention resource type.']]);
        }if (isset($filters['reason']) && ! in_array($filters['reason'], self::REASONS, true)) {
            throw ValidationException::withMessages(['reason' => ['Unsupported attention reason.']]);
        }
    }

    private function resourceFilters($query, array $filters): void
    {
        if (! isset($filters['organization_id']) && ! trim((string) ($filters['search'] ?? ''))) {
            return;
        }$query->where(function ($outer) use ($filters) {
            foreach (self::TYPES as $type) {
                $model = $type === 'device' ? Device::query() : DeviceTemplate::query();
                if (isset($filters['organization_id'])) {
                    $model->where('organization_id', $filters['organization_id']);
                }if ($search = trim((string) ($filters['search'] ?? ''))) {
                    $model->where('name', 'like', "%{$search}%");
                }$outer->orWhere(fn ($part) => $part->where('resource_type', $type)->whereIn('resource_id', $model->select('id')));
            }
        });
    }

    private function notifyOpen(ResourceAttentionState $state, $resource, User $admin): void
    {
        $users = $state->resource_type === 'device' ? User::whereIn('id', DeviceAccessAssignment::where(['device_id' => $state->resource_id, 'access_level' => 'full_access'])->select('user_id'))->where('status', 'active')->whereNull('platform_role')->get() : User::whereIn('id', ResourceCollaborator::where(['resource_type' => 'device_template', 'resource_id' => $state->resource_id, 'permission' => 'edit'])->select('user_id'))->where('status', 'active')->whereNull('platform_role')->get();
        foreach ($users as $user) {
            app(NotificationOutboxService::class)->recordNotification($user, 'resource.needs_attention', "attention:{$state->resource_type}:{$state->resource_id}:{$state->updated_at?->getTimestamp()}", ['organization_id' => $resource->organization_id, 'actor_id' => $admin->id, 'resource_type' => $state->resource_type, 'resource_id' => $state->resource_id, 'action_url' => $state->resource_type === 'device' ? "/app/devices/{$state->resource_id}" : "/app/developer/templates/{$state->resource_id}", 'data' => ['resource_name' => $resource->name, 'reason_code' => $state->reason_code, 'reason_label' => self::LABELS[$state->reason_code]], 'requires_action' => true, 'action_state' => null, 'title' => 'Resource needs attention', 'message' => mb_substr($state->note, 0, 240), 'severity' => 'info']);
        }
    }
}
