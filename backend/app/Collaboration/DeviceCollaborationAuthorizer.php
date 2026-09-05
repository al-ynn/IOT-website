<?php

namespace App\Collaboration;

use App\Models\Device;
use App\Models\User;
use App\Services\Admin\DeviceAccessService;
use Illuminate\Database\Eloquent\Model;

final class DeviceCollaborationAuthorizer implements CollaborationResourceAuthorizer
{
    public function __construct(private DeviceAccessService $access) {}

    public function canView(User $user, Model $resource): bool
    {
        return $resource instanceof Device && $this->access->canViewDevice($user, $resource);
    }

    public function canEdit(User $user, Model $resource): bool
    {
        return $resource instanceof Device && $this->access->canManageDevice($user, $resource);
    }

    public function canShare(User $user, Model $resource): bool
    {
        return $resource instanceof Device && $this->access->hasFullDeviceAccess($user, $resource);
    }

    public function canComment(User $user, Model $resource): bool
    {
        return $this->canView($user, $resource);
    }

    public function canReview(User $user, Model $resource): bool
    {
        return false;
    }

    public function canAdminister(User $user, Model $resource): bool
    {
        return $resource instanceof Device && $user->isPlatformAdmin() && (int) $user->organization_id === (int) $resource->organization_id;
    }
}
