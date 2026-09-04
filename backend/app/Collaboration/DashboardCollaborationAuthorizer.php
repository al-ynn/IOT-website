<?php

namespace App\Collaboration;

use App\Models\Dashboard;
use App\Models\ResourceCollaborator;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class DashboardCollaborationAuthorizer implements CollaborationResourceAuthorizer
{
    private function personal(Model $resource): bool
    {
        return $resource instanceof Dashboard && $resource->scope_type === 'personal';
    }

    private function owner(User $user, Model $resource): bool
    {
        return $this->personal($resource) && ($user->status === null || $user->status === 'active') && (int) $resource->organization_id === (int) $user->organization_id && (int) $resource->owner_user_id === (int) $user->id;
    }

    private function permission(User $user, Model $resource): ?string
    {
        if (! $this->personal($resource) || ($user->status !== null && $user->status !== 'active') || (int) $resource->organization_id !== (int) $user->organization_id || ! $user->hasOrganizationPermission('dashboard.view')) return null;
        return ResourceCollaborator::query()->where([
            'resource_type' => 'dashboard',
            'resource_id' => $resource->getKey(),
            'user_id' => $user->id,
        ])->value('permission');
    }

    public function canView(User $user, Model $resource): bool { return $this->owner($user, $resource) || in_array($this->permission($user, $resource), ['view', 'edit'], true); }
    public function canEdit(User $user, Model $resource): bool { return $this->owner($user, $resource) || $this->permission($user, $resource) === 'edit'; }
    public function canShare(User $user, Model $resource): bool { return $this->owner($user, $resource); }
    public function canComment(User $user, Model $resource): bool { return $this->canView($user, $resource); }
    public function canReview(User $user, Model $resource): bool { return false; }
    public function canAdminister(User $user, Model $resource): bool { return false; }
}