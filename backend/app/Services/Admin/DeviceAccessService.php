<?php

namespace App\Services\Admin;

use App\Models\Automation;
use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\ResourceRevisionState;
use App\Models\User;
use App\Services\NotificationOutboxService;
use App\Services\ResourceLifecycleService;
use App\Services\ResourceRevisionStateService;
use App\Services\RevisionReminderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeviceAccessService
{
    public const ACCESS_LEVELS = ['viewer', 'full_access'];

    public function list(array $filters = []): Builder
    {
        return DeviceAccessAssignment::query()
            ->with(['device.organization', 'user.organization', 'assignedBy'])
            ->when($filters['device_id'] ?? null, fn (Builder $q, $deviceId) => $q->where('device_id', $deviceId))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $userId) => $q->where('user_id', $userId))
            ->when($filters['access_level'] ?? null, fn (Builder $q, $level) => $q->where('access_level', $level))
            ->when($filters['organization_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('device', fn (Builder $device) => $device->where('organization_id', $id)))
            ->when($filters['search'] ?? null, function (Builder $q, $search) {
                $term = trim((string) $search);
                if ($term === '') {
                    return;
                }

                $q->where(function (Builder $nested) use ($term) {
                    $nested->whereHas('device', fn (Builder $device) => $device->where('name', 'like', "%{$term}%")->orWhere('external_id', 'like', "%{$term}%")->orWhereHas('organization', fn (Builder $organization) => $organization->where('name', 'like', "%{$term}%")))
                        ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
                });
            })
            ->orderByDesc('created_at');
    }

    public function create(User $admin, int|string $deviceId, int|string $userId, string $accessLevel, bool $notify = true): DeviceAccessAssignment
    {
        $this->validateAccessLevel($accessLevel);

        return DB::transaction(function () use ($admin, $deviceId, $userId, $accessLevel, $notify) {
            $device = Device::where('organization_id', $admin->organization_id)->with('organization')->findOrFail($deviceId);
            $user = User::where('organization_id', $admin->organization_id)->with('organization')->findOrFail($userId);
            app(ResourceLifecycleService::class)->assertActive('device', $device->id, 'Restore the Device before granting new access.');

            $this->assertAssignable($device, $user);

            $existing = DeviceAccessAssignment::query()
                ->where('device_id', $device->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'user_id' => ['This device is already assigned to that staff member.'],
                ]);
            }

            $assignment = DeviceAccessAssignment::create([
                'device_id' => $device->id,
                'user_id' => $user->id,
                'access_level' => $accessLevel,
                'assigned_by' => $admin->id,
            ]);
            app(ResourceRevisionStateService::class)->initializeGrant($user, $device);
            if ($notify) {
                $this->notifyAccessChange($user, $admin, $device, 'device.access_granted', $accessLevel, null);
            }

            return $assignment;
        });
    }

    public function update(DeviceAccessAssignment $assignment, string $accessLevel, ?User $actor = null, bool $notify = true): DeviceAccessAssignment
    {
        $this->validateAccessLevel($accessLevel);

        return DB::transaction(function () use ($assignment, $accessLevel, $actor, $notify) {
            $locked = DeviceAccessAssignment::query()->with(['device', 'user'])->lockForUpdate()->findOrFail($assignment->id);
            if ($actor) {
                abort_unless((int) $locked->device->organization_id === (int) $actor->organization_id, 404);
            }
            $old = $locked->access_level;
            if ($old === 'viewer' && $accessLevel === 'full_access') {
                app(ResourceLifecycleService::class)->assertActive('device', $locked->device_id, 'Restore the Device before upgrading access.');
            }
            if ($old === $accessLevel) {
                return $locked->load(['device.organization', 'user.organization', 'assignedBy']);
            }
            $locked->update(['access_level' => $accessLevel, 'assigned_by' => $actor?->id ?? $locked->assigned_by]);
            if ($actor && $notify) {
                $this->notifyAccessChange($locked->user, $actor, $locked->device, $accessLevel === 'full_access' ? 'device.access_upgraded' : 'device.access_downgraded', $accessLevel, $old);
            }

            return $locked->refresh()->load(['device.organization', 'user.organization', 'assignedBy']);
        });
    }

    public function delete(DeviceAccessAssignment $assignment, ?User $actor = null): void
    {
        DB::transaction(function () use ($assignment, $actor) {
            $locked = DeviceAccessAssignment::query()->with(['device', 'user'])->lockForUpdate()->findOrFail($assignment->id);
            if ($actor) {
                abort_unless((int) $locked->device->organization_id === (int) $actor->organization_id, 404);
            }
            if ($actor) {
                $this->notifyAccessChange($locked->user, $actor, $locked->device, 'device.access_revoked', null, $locked->access_level);
            }
            $state = ResourceRevisionState::where(['resource_type' => 'device', 'resource_id' => $locked->device_id, 'user_id' => $locked->user_id])->first();
            if ($state) {
                app(RevisionReminderService::class)->cancelForState($state);
            }
            $locked->delete();
        });
    }

    public function createForCreator(User $user, Device $device): DeviceAccessAssignment
    {
        abort_unless($user->isActive() && $user->isStaff() && (int) $user->organization_id === (int) $device->organization_id, 403);
        $assignment = DeviceAccessAssignment::create(['device_id' => $device->id, 'user_id' => $user->id, 'access_level' => 'full_access', 'assigned_by' => $user->id]);
        app(ResourceRevisionStateService::class)->initializeGrant($user, $device);

        return $assignment;
    }

    public function deviceAssignments(Device $device): Builder
    {
        return DeviceAccessAssignment::query()
            ->with(['user.organization', 'assignedBy', 'device.organization'])
            ->where('device_id', $device->id);
    }

    public function userAssignments(User $user): Builder
    {
        return DeviceAccessAssignment::query()
            ->with(['device.organization', 'assignedBy', 'user.organization'])
            ->where('user_id', $user->id);
    }

    public function canViewDevice(User $user, Device $device): bool
    {
        return $this->getDeviceAccessLevel($user, $device) !== null;
    }

    public function hasFullDeviceAccess(User $user, Device $device): bool
    {
        return $this->getDeviceAccessLevel($user, $device) === 'full_access';
    }

    public function canManageDevice(User $user, Device $device): bool
    {
        return $this->hasFullDeviceAccess($user, $device);
    }

    public function getDeviceAccessLevel(User $user, Device $device): ?string
    {
        if ($user->status !== null && $user->status !== 'active') {
            return null;
        }
        if (! $user->organization_id || $device->organization_id !== $user->organization_id) {
            return null;
        }
        if ($user->isPlatformAdmin()) {
            return 'full_access';
        }
        if ($device->relationLoaded('accessAssignments')) {
            return $device->accessAssignments->firstWhere('user_id', $user->id)?->access_level;
        }

        return DeviceAccessAssignment::query()->where('device_id', $device->id)->where('user_id', $user->id)->value('access_level');
    }

    public function accessibleDevices(User $user): Builder
    {
        $query = Device::query()->where('organization_id', $user->organization_id)
            ->with([
                'creator:id,name',
                'template:id,name',
                'canonicalLocation:id,name',
                'latestRevision',
                'lifecycleState:id,resource_type,resource_id,state',
                'accessAssignments' => fn ($assignments) => $assignments->where('user_id', $user->id),
            ]);
        if ($user->status !== null && $user->status !== 'active') {
            return $query->whereRaw('1 = 0');
        }
        if ($user->isPlatformAdmin()) {
            return $query;
        }

        return $query->whereHas('accessAssignments', fn (Builder $assignment) => $assignment->where('user_id', $user->id));
    }

    public function findViewableDeviceOrFail(User $user, int|string $deviceId): Device
    {
        return $this->accessibleDevices($user)->findOrFail($deviceId);
    }

    public function findManageableDeviceOrFail(User $user, int|string $deviceId): Device
    {
        $device = $this->findViewableDeviceOrFail($user, $deviceId);
        abort_unless($this->canManageDevice($user, $device), 403, 'Full device access is required.');
        if (! $user->isPlatformAdmin()) {
            app(ResourceLifecycleService::class)->assertActive('device', $device->id, 'Disabled or Archived Devices are read-only.');
        }

        return $device;
    }

    public function accessPayload(User $user, Device $device): array
    {
        $level = $this->getDeviceAccessLevel($user, $device);
        $active = $device->relationLoaded('lifecycleState')
            ? ($device->lifecycleState?->state ?? 'active') === 'active'
            : app(ResourceLifecycleService::class)->state('device', $device->id) === 'active';

        return ['level' => $level, 'canManage' => $level === 'full_access' && ($user->isPlatformAdmin() || $active)];
    }

    public function accessibleAutomationIds(User $user): array
    {
        $automations = Automation::query()->where('organization_id', $user->organization_id)
            ->when(! $user->isPlatformAdmin(), fn (Builder $query) => $query->whereHas('collaborators', fn (Builder $grants) => $grants
                ->where('user_id', $user->id)
                ->whereIn('permission', ['view', 'edit'])))
            ->with('triggers')->get();
        if ($user->isPlatformAdmin()) {
            return $automations->pluck('id')->all();
        }
        $deviceIds = $this->accessibleDevices($user)->pluck('id')->map(fn ($id) => (string) $id)->all();

        return $automations->filter(function (Automation $automation) use ($deviceIds) {
            $deviceId = $automation->triggers->first()?->configuration['deviceId'] ?? null;
            if ($deviceId === null) {
                return true;
            }
            $deviceStillExists = Device::query()->where('organization_id', $automation->organization_id)->whereKey($deviceId)->exists();

            return ! $deviceStillExists || in_array((string) $deviceId, $deviceIds, true);
        })->pluck('id')->all();
    }

    public function canManageAutomation(User $user, Automation $automation): bool
    {
        $deviceId = $automation->loadMissing('triggers')->triggers->first()?->configuration['deviceId'] ?? null;
        if ($deviceId === null) {
            return true;
        }
        $device = Device::query()->where('organization_id', $user->organization_id)->find($deviceId);

        return $device !== null && $this->canManageDevice($user, $device);
    }

    private function validateAccessLevel(string $accessLevel): void
    {
        abort_unless(in_array($accessLevel, self::ACCESS_LEVELS, true), 422, 'Invalid access level.');
    }

    private function assertAssignable(Device $device, User $user): void
    {
        abort_if($user->isPlatformAdmin(), 422, 'Admin accounts cannot be assignment targets.');
        abort_if($device->organization_id !== $user->organization_id, 422, 'Device and user must belong to the same organization.');
        abort_if(! $user->organization_id, 422, 'Staff user must belong to an organization.');
        abort_if(! $device->organization_id, 422, 'Device must belong to an organization.');
        abort_if($user->status !== 'active', 422, 'Staff user must be active.');
    }

    private function notifyAccessChange(User $recipient, User $actor, Device $device, string $type, ?string $level, ?string $oldLevel): void
    {
        $label = $level === 'full_access' ? 'Full Access' : ($level === 'viewer' ? 'Viewer' : null);
        $messages = [
            'device.access_granted' => "{$actor->name} granted you {$label} access to {$device->name}.",
            'device.access_upgraded' => "{$actor->name} upgraded your access to Full Access for {$device->name}.",
            'device.access_downgraded' => "{$actor->name} changed your access to Viewer for {$device->name}.",
            'device.access_revoked' => "{$actor->name} removed your access to {$device->name}.",
        ];
        app(NotificationOutboxService::class)->recordNotification($recipient, $type, "device:{$device->id}:access:{$recipient->id}:{$type}:{$level}:{$oldLevel}", ['organization_id' => $recipient->organization_id, 'actor_id' => $actor->id, 'resource_type' => 'device', 'resource_id' => $device->id, 'action_url' => $type === 'device.access_revoked' ? '/app/devices' : "/app/devices/{$device->id}", 'data' => ['access_level' => $level, 'previous_access_level' => $oldLevel], 'requires_action' => false, 'action_state' => 'resolved', 'title' => match ($type) {
            'device.access_granted' => 'Device access granted', 'device.access_upgraded' => 'Device access upgraded', 'device.access_downgraded' => 'Device access changed', default => 'Device access revoked'
        }, 'message' => $messages[$type], 'severity' => 'info']);
    }
}
