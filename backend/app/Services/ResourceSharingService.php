<?php

namespace App\Services;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Models\Automation;
use App\Models\Dashboard;
use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\DeviceTemplate;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ResourceCollaborator;
use App\Models\ResourceShareRequest;
use App\Models\User;
use App\Services\Admin\DeviceAccessService;
use App\Sharing\CanonicalGrantSourceRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResourceSharingService
{
    public const ACTIVE_TYPES = ['device', 'device_template', 'dashboard', 'automation', 'report', 'location'];

    public const DEVICE_PERMISSIONS = ['viewer', 'full_access'];

    public const TEMPLATE_PERMISSIONS = ['view', 'edit'];

    public function __construct(
        private CollaborationResourceRegistry $registry,
        private DeviceAccessService $deviceAccess,
        private CanonicalGrantSourceRegistry $grantSources,
        private ResourceLifecycleService $lifecycle,
    ) {}

    public function create(User $actor, array $data): ResourceShareRequest
    {
        $type = (string) $data['resource_type'];
        if (! in_array($type, self::ACTIVE_TYPES, true)) {
            throw ValidationException::withMessages(['resource_type' => ['This registered resource type does not have an active sharing workflow.']]);
        }
        $resolved = $this->registry->resolve($actor, new CollaborationResourceReference($type, $data['resource_id']), 'share');
        $permission = (string) $data['permission'];
        $this->grantSources->assertPermission($type, $permission);
        if (in_array($type, ['device_template', 'dashboard', 'automation', 'report', 'location'], true)) {
            return $this->createDocumentShare($actor, $resolved->resource, $data, $permission, $type);
        }
        if (! in_array($permission, self::DEVICE_PERMISSIONS, true)) {
            throw ValidationException::withMessages(['permission' => ['Device permission must be viewer or full_access.']]);
        }
        $note = isset($data['note']) ? trim((string) $data['note']) : null;
        if ($note !== null && preg_match('/(?:authorization\s*:|bearer\s+[a-z0-9._-]+|-----begin [^-]*private key-----)/i', $note)) {
            throw ValidationException::withMessages(['note' => ['Share notes must not contain credentials or authorization secrets.']]);
        }
        /** @var Device $device */
        $device = $resolved->resource;
        $recipient = User::query()->whereKey($data['recipient_id'])->where('status', 'active')->first();
        if (! $recipient || $recipient->isPlatformAdmin() || ! $device->organization_id || (int) $recipient->organization_id !== (int) $device->organization_id) {
            throw ValidationException::withMessages(['recipient_id' => ['Recipient is not eligible for this resource.']]);
        }
        abort_if($actor->is($recipient), 422, 'A resource cannot be shared with yourself.');
        $existing = DeviceAccessAssignment::query()->where('device_id', $device->id)->where('user_id', $recipient->id)->first();
        if ($existing && ($existing->access_level === 'full_access' || $existing->access_level === $permission)) {
            throw ValidationException::withMessages(['recipient_id' => ['Recipient already has equal or greater Device access.']]);
        }

        try {
            return DB::transaction(function () use ($actor, $recipient, $device, $permission, $note) {
                $device = $this->lifecycle->lockActiveResource($actor, 'device', $device->id, 'share');
                $active = ResourceShareRequest::query()->where('resource_type', 'device')->where('resource_id', $device->id)
                    ->where('recipient_user_id', $recipient->id)->whereIn('status', ResourceShareRequest::ACTIVE_STATUSES)->lockForUpdate()->first();
                if ($active) {
                    throw ValidationException::withMessages(['recipient_id' => ['An active share request already exists for this recipient.']]);
                }

                if ($actor->isPlatformAdmin()) {
                    $assignment = DeviceAccessAssignment::query()->where('device_id', $device->id)->where('user_id', $recipient->id)->lockForUpdate()->first();
                    $assignment ? $this->deviceAccess->update($assignment, $permission, $actor, false) : $this->deviceAccess->create($actor, $device->id, $recipient->id, $permission, false);
                    $share = ResourceShareRequest::create(['resource_type' => 'device', 'resource_id' => $device->id, 'sender_user_id' => $actor->id, 'recipient_user_id' => $recipient->id, 'requested_permission' => $permission, 'final_permission' => $permission, 'status' => 'approved', 'note' => $note, 'approved_by' => $actor->id, 'approved_at' => now()]);
                    $this->notify($recipient, $actor, $share, 'access.granted', 'Device access granted', "{$actor->name} granted you {$permission} access to {$device->name}.");

                    return $share->load(['sender:id,name', 'recipient:id,name,email', 'approver:id,name']);
                }

                $share = ResourceShareRequest::create(['resource_type' => 'device', 'resource_id' => $device->id, 'sender_user_id' => $actor->id, 'recipient_user_id' => $recipient->id, 'requested_permission' => $permission, 'status' => 'pending_recipient', 'active_key' => "device:{$device->id}:{$recipient->id}", 'note' => $note]);
                $this->notify($recipient, $actor, $share, 'access.requested', 'Device access requested', "{$actor->name} invited you to request {$permission} access to {$device->name}.");

                return $share->load(['sender:id,name', 'recipient:id,name,email']);
            });
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'active_key')) {
                throw ValidationException::withMessages(['recipient_id' => ['An active share request already exists for this recipient.']]);
            }
            throw $exception;
        }
    }

    public function visibleTo(User $user): Builder
    {
        return ResourceShareRequest::query()->with(['sender:id,name', 'recipient:id,name,email', 'approver:id,name', 'rejector:id,name'])
            ->where(fn (Builder $q) => $q->where('sender_user_id', $user->id)->orWhere('recipient_user_id', $user->id));
    }

    public function accept(User $actor, ResourceShareRequest $share): ResourceShareRequest
    {
        return DB::transaction(function () use ($actor, $share) {
            $locked = ResourceShareRequest::query()->lockForUpdate()->findOrFail($share->id);
            $this->assertRecipient($actor, $locked);
            if (in_array($locked->resource_type, ['device_template', 'dashboard', 'automation', 'report', 'location'], true)) {
                if ($locked->status === 'approved') {
                    return $locked;
                }
                $this->assertDocumentTransitionEligible($locked);
                if ($locked->status !== 'pending_recipient') {
                    throw ValidationException::withMessages(['status' => ['This request can no longer be accepted.']]);
                }
                $collaborator = ResourceCollaborator::query()->where(['resource_type' => $locked->resource_type, 'resource_id' => $locked->resource_id, 'user_id' => $actor->id])->lockForUpdate()->first();
                $finalPermission = $collaborator?->permission === 'edit' ? 'edit' : $locked->requested_permission;
                if ($collaborator) {
                    if ($collaborator->permission !== $finalPermission) {
                        $collaborator->update(['permission' => $finalPermission, 'granted_by' => $locked->sender_user_id]);
                    }
                } else {
                    ResourceCollaborator::create(['resource_type' => $locked->resource_type, 'resource_id' => $locked->resource_id, 'user_id' => $actor->id, 'permission' => $finalPermission, 'granted_by' => $locked->sender_user_id]);
                }
                $locked->update(['status' => 'approved', 'active_key' => null, 'final_permission' => $finalPermission, 'accepted_at' => now(), 'approved_at' => now()]);
                $label = match ($locked->resource_type) {
                    'automation' => 'Automation','report' => 'Report','location' => 'Location','dashboard' => 'Dashboard',default => 'Template'
                };
                $this->notify($actor, $locked->sender, $locked, 'access.granted', "{$label} access granted", "You now have {$locked->requested_permission} access to the shared {$label}.");
                $this->notify($locked->sender, $actor, $locked, 'access.accepted', "{$label} share accepted", "{$actor->name} accepted the {$label} share.");

                return $locked->refresh()->load(['sender:id,name', 'recipient:id,name,email']);
            }
            if ($locked->status === 'awaiting_admin_approval') {
                return $locked;
            }
            $this->assertDeviceTransitionEligible($locked);
            if ($locked->status !== 'pending_recipient') {
                throw ValidationException::withMessages(['status' => ['This request can no longer be accepted.']]);
            }
            $locked->update(['status' => 'awaiting_admin_approval', 'accepted_at' => now()]);
            $sender = $locked->sender;
            $this->notify($sender, $actor, $locked, 'access.accepted', 'Share request accepted', "{$actor->name} accepted the request; Device access still awaits Admin approval.");
            foreach (User::query()->where('platform_role', 'platform_admin')->where('organization_id', $locked->sender->organization_id)->where('status', 'active')->get() as $admin) {
                $this->notify($admin, $actor, $locked, 'access.awaiting_approval', 'Device access awaiting approval', "{$actor->name} accepted a Device share request.");
            }

            return $locked->refresh()->load(['sender:id,name', 'recipient:id,name,email']);
        });
    }

    public function decline(User $actor, ResourceShareRequest $share): ResourceShareRequest
    {
        return DB::transaction(function () use ($actor, $share) {
            $locked = ResourceShareRequest::query()->lockForUpdate()->findOrFail($share->id);
            $this->assertRecipient($actor, $locked);
            if ($locked->status === 'declined') {
                return $locked;
            }
            if ($locked->status !== 'pending_recipient') {
                throw ValidationException::withMessages(['status' => ['This request can no longer be declined.']]);
            }
            $locked->update(['status' => 'declined', 'active_key' => null, 'declined_at' => now()]);
            $this->notify($locked->sender, $actor, $locked, 'access.declined', 'Share request declined', "{$actor->name} declined the Device share request.");

            return $locked->refresh();
        });
    }

    public function cancel(User $actor, ResourceShareRequest $share): ResourceShareRequest
    {
        return DB::transaction(function () use ($actor, $share) {
            $locked = ResourceShareRequest::query()->lockForUpdate()->findOrFail($share->id);
            abort_unless((int) $locked->sender_user_id === (int) $actor->id, 404);
            if ($locked->status === 'cancelled') {
                return $locked;
            }
            if (! in_array($locked->status, ResourceShareRequest::ACTIVE_STATUSES, true)) {
                throw ValidationException::withMessages(['status' => ['Finalized requests cannot be cancelled. Revoke access through Device Access management.']]);
            }
            $locked->update(['status' => 'cancelled', 'active_key' => null, 'cancelled_at' => now()]);
            $this->notify($locked->recipient, $actor, $locked, 'access.cancelled', 'Share request cancelled', "{$actor->name} cancelled the Device share request.");

            return $locked->refresh();
        });
    }

    public function approve(User $admin, ResourceShareRequest $share, string $permission): ResourceShareRequest
    {
        abort_unless($admin->isPlatformAdmin(), 403);
        abort_unless($share->sender()->where('organization_id',$admin->organization_id)->exists(),404);
        if (! in_array($permission, self::DEVICE_PERMISSIONS, true)) {
            throw ValidationException::withMessages(['permission' => ['Final Device permission must be viewer or full_access.']]);
        }
        if ($share->requested_permission === 'viewer' && $permission === 'full_access') {
            throw ValidationException::withMessages(['permission' => ['Approval cannot escalate beyond the requested permission.']]);
        }

        return DB::transaction(function () use ($admin, $share, $permission) {
            $locked = ResourceShareRequest::query()->lockForUpdate()->findOrFail($share->id);
            if ($locked->status === 'approved') {
                return $locked;
            }
            if ($locked->status !== 'awaiting_admin_approval') {
                throw ValidationException::withMessages(['status' => ['Only accepted requests awaiting Admin approval may be approved.']]);
            }
            /** @var Device $device */
            $device = Device::findOrFail($locked->resource_id);
            $recipient = User::findOrFail($locked->recipient_user_id);
            $this->assertDeviceTransitionEligible($locked, $device, $recipient);
            $assignment = DeviceAccessAssignment::query()->where('device_id', $device->id)->where('user_id', $recipient->id)->lockForUpdate()->first();
            $finalPermission = $assignment?->access_level === 'full_access' ? 'full_access' : $permission;
            if (! $assignment || $assignment->access_level !== $finalPermission) {
                $assignment ? $this->deviceAccess->update($assignment, $finalPermission, $admin, false) : $this->deviceAccess->create($admin, $device->id, $recipient->id, $finalPermission, false);
            }
            $locked->update(['status' => 'approved', 'active_key' => null, 'final_permission' => $finalPermission, 'approved_by' => $admin->id, 'approved_at' => now()]);
            $this->notify($recipient, $admin, $locked, 'access.granted', 'Device access approved', "{$admin->name} approved {$finalPermission} access to {$device->name}.");
            $this->notify($locked->sender, $admin, $locked, 'access.granted', 'Device share approved', "{$admin->name} approved the Device share request.");

            return $locked->refresh()->load(['sender:id,name', 'recipient:id,name,email', 'approver:id,name']);
        });
    }

    public function reject(User $admin, ResourceShareRequest $share): ResourceShareRequest
    {
        abort_unless($admin->isPlatformAdmin(), 403);
        abort_unless($share->sender()->where('organization_id',$admin->organization_id)->exists(),404);

        return DB::transaction(function () use ($admin, $share) {
            $locked = ResourceShareRequest::query()->lockForUpdate()->findOrFail($share->id);
            if ($locked->status === 'rejected') {
                return $locked;
            }
            if ($locked->status !== 'awaiting_admin_approval') {
                throw ValidationException::withMessages(['status' => ['Only accepted requests awaiting Admin approval may be rejected.']]);
            }
            $locked->update(['status' => 'rejected', 'active_key' => null, 'rejected_by' => $admin->id, 'rejected_at' => now()]);
            foreach ([$locked->sender, $locked->recipient] as $recipient) {
                $this->notify($recipient, $admin, $locked, 'access.rejected', 'Device share rejected', "{$admin->name} rejected the Device share request.");
            }

            return $locked->refresh();
        });
    }

    private function assertRecipient(User $actor, ResourceShareRequest $share): void
    {
        abort_unless((int) $share->recipient_user_id === (int) $actor->id, 404);
    }

    public function revoke(User $actor, ResourceShareRequest $share): ResourceShareRequest
    {
        return DB::transaction(function () use ($actor, $share) {
            $locked = ResourceShareRequest::query()->lockForUpdate()->findOrFail($share->id);
            if ($locked->resource_type !== 'dashboard') {
                abort(404);
            }
            $this->registry->resolve($actor, new CollaborationResourceReference('dashboard', $locked->resource_id), 'share');
            if ($locked->status === 'revoked') {
                return $locked;
            }
            if ($locked->status !== 'approved') {
                throw ValidationException::withMessages(['status' => ['Only an accepted Dashboard share can be revoked.']]);
            }
            ResourceCollaborator::query()->where(['resource_type' => 'dashboard', 'resource_id' => $locked->resource_id, 'user_id' => $locked->recipient_user_id])->delete();
            $locked->update(['status' => 'revoked', 'active_key' => null]);
            $this->notify($locked->recipient, $actor, $locked, 'access.revoked', 'Dashboard access revoked', 'Your view access to the shared Dashboard was revoked.');

            return $locked->refresh()->load(['sender:id,name', 'recipient:id,name,email']);
        });
    }

    public function changeDashboardPermission(User $actor, ResourceShareRequest $share, string $permission): ResourceShareRequest
    {
        if (! in_array($permission, ['view', 'edit'], true)) {
            throw ValidationException::withMessages(['permission' => ['Dashboard permission must be view or edit.']]);
        }

        return DB::transaction(function () use ($actor, $share, $permission) {
            $locked = ResourceShareRequest::query()->lockForUpdate()->findOrFail($share->id);
            if ($locked->resource_type !== 'dashboard' || $locked->status !== 'approved') {
                abort(404);
            }
            $this->registry->resolve($actor, new CollaborationResourceReference('dashboard', $locked->resource_id), 'share');
            $collaborator = ResourceCollaborator::query()->where(['resource_type' => 'dashboard', 'resource_id' => $locked->resource_id, 'user_id' => $locked->recipient_user_id])->lockForUpdate()->firstOrFail();
            if ($collaborator->permission === $permission) {
                return $locked->load(['sender:id,name', 'recipient:id,name,email']);
            }
            $collaborator->update(['permission' => $permission, 'granted_by' => $actor->id]);
            $locked->update(['requested_permission' => $permission, 'final_permission' => $permission]);
            $this->notify($locked->recipient, $actor, $locked, 'access.changed', 'Dashboard access changed', "Your Dashboard access is now {$permission}.");

            return $locked->refresh()->load(['sender:id,name', 'recipient:id,name,email']);
        });
    }

    private function createDocumentShare(User $actor, DeviceTemplate|Dashboard|Automation|Report|Location $resource, array $data, string $permission, string $type): ResourceShareRequest
    {
        if ($type === 'dashboard' && ! in_array($permission, ['view', 'edit'], true)) {
            throw ValidationException::withMessages(['permission' => ['Dashboard permission must be view or edit.']]);
        }
        if ($type !== 'dashboard' && ! in_array($permission, self::TEMPLATE_PERMISSIONS, true)) {
            throw ValidationException::withMessages(['permission' => ['Collaboration permission must be view or edit.']]);
        }
        $recipient = User::query()->whereKey($data['recipient_id'])->where('status', 'active')->first();
        if (! $recipient || $recipient->isPlatformAdmin() || (int) $recipient->organization_id !== (int) $resource->organization_id || ($type === 'dashboard' && ! $recipient->hasOrganizationPermission('dashboard.view'))) {
            throw ValidationException::withMessages(['recipient_id' => ['Recipient is not eligible for this resource.']]);
        }
        abort_if($actor->is($recipient), 422, 'A resource cannot be shared with yourself.');
        $existing = ResourceCollaborator::where(['resource_type' => $type, 'resource_id' => $resource->id, 'user_id' => $recipient->id])->value('permission');
        if ($existing === 'edit' || $existing === $permission) {
            throw ValidationException::withMessages(['recipient_id' => ['Recipient already has equal or greater collaboration access.']]);
        }
        $note = isset($data['note']) ? trim((string) $data['note']) : null;
        if ($note !== null && preg_match('/(?:authorization\s*:|bearer\s+[a-z0-9._-]+|-----begin [^-]*private key-----)/i', $note)) {
            throw ValidationException::withMessages(['note' => ['Share notes must not contain credentials or authorization secrets.']]);
        }

        return DB::transaction(function () use ($actor, $recipient, $resource, $permission, $note, $type) {
            $resource = $this->lifecycle->lockActiveResource($actor, $type, $resource->id, 'share');
            $active = ResourceShareRequest::where('resource_type', $type)->where('resource_id', $resource->id)->where('recipient_user_id', $recipient->id)->whereIn('status', ResourceShareRequest::ACTIVE_STATUSES)->lockForUpdate()->first();
            if ($active) {
                throw ValidationException::withMessages(['recipient_id' => ['An active share request already exists for this recipient.']]);
            }
            $share = ResourceShareRequest::create(['resource_type' => $type, 'resource_id' => $resource->id, 'sender_user_id' => $actor->id, 'recipient_user_id' => $recipient->id, 'requested_permission' => $permission, 'status' => 'pending_recipient', 'active_key' => "{$type}:{$resource->id}:{$recipient->id}", 'note' => $note]);
            $label = match ($type) {
                'automation' => 'Automation','report' => 'Report','location' => 'Location','dashboard' => 'Dashboard',default => 'Template'
            };
            $this->notify($recipient, $actor, $share, 'access.requested', "{$label} collaboration invitation", "{$actor->name} invited you to collaborate on {$resource->name}.");

            return $share->load(['sender:id,name', 'recipient:id,name,email']);
        });
    }

    private function assertDocumentTransitionEligible(ResourceShareRequest $share): void
    {
        if (! in_array($share->resource_type, ['device_template', 'dashboard', 'automation', 'report', 'location'], true) || ! in_array($share->requested_permission, ['view', 'edit'], true)) {
            throw ValidationException::withMessages(['resource' => ['This share request is not valid for collaboration.']]);
        }
        $sender = User::query()->whereKey($share->sender_user_id)->where('status', 'active')->first();
        $recipient = User::query()->whereKey($share->recipient_user_id)->where('status', 'active')->first();
        if (! $sender) {
            throw ValidationException::withMessages(['sender' => ['The sender is no longer eligible to create this grant.']]);
        }
        if (! $recipient) {
            throw ValidationException::withMessages(['recipient' => ['The recipient is no longer eligible.']]);
        }
        $resource = $this->lifecycle->lockActiveResource($sender, $share->resource_type, $share->resource_id, 'share');
        if ((int) $recipient->organization_id !== (int) $resource->getAttribute('organization_id')) {
            throw ValidationException::withMessages(['recipient' => ['The recipient no longer belongs to the resource Organization.']]);
        }
        if ($share->resource_type === 'dashboard' && ($resource->scope_type !== 'personal' || ! $recipient->hasOrganizationPermission('dashboard.view'))) {
            throw ValidationException::withMessages(['resource' => ['This Dashboard is no longer shareable with the recipient.']]);
        }
    }

    private function assertDeviceTransitionEligible(ResourceShareRequest $share, ?Device $device = null, ?User $recipient = null): void
    {
        if ($share->resource_type !== 'device' || ! in_array($share->requested_permission, self::DEVICE_PERMISSIONS, true)) {
            throw ValidationException::withMessages(['resource' => ['This Device share request is invalid.']]);
        }
        $device ??= Device::find($share->resource_id);
        $recipient ??= User::find($share->recipient_user_id);
        if (! $device) {
            throw ValidationException::withMessages(['resource' => ['The Device no longer exists.']]);
        }
        if (! $recipient || $recipient->status !== 'active' || $recipient->isPlatformAdmin() || (int) $recipient->organization_id !== (int) $device->organization_id) {
            throw ValidationException::withMessages(['recipient' => ['The recipient is no longer eligible.']]);
        }
        $sender = User::query()->whereKey($share->sender_user_id)->where('status', 'active')->first();
        if (! $sender) {
            throw ValidationException::withMessages(['sender' => ['The sender is no longer eligible to create this grant.']]);
        }
        $this->lifecycle->lockActiveResource($sender, 'device', $device->id, 'share');
    }

    private function notify(User $recipient, User $actor, ResourceShareRequest $share, string $type, string $title, string $message): void
    {
        app(NotificationOutboxService::class)->recordNotification($recipient,$type,"share:{$share->id}:{$type}",['organization_id'=>$recipient->organization_id,'actor_id'=>$actor->id,'resource_type'=>$share->resource_type,'resource_id'=>$share->resource_id,'action_url'=>"/app/shares/{$share->id}",'data'=>['share_request_id'=>$share->id,'status'=>$share->status],'requires_action'=>in_array($type,['access.requested','access.awaiting_approval'],true),'action_state'=>$share->status,'title'=>$title,'message'=>$message,'severity'=>'info']);
    }
}
