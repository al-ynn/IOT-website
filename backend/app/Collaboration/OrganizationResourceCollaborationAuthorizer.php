<?php

namespace App\Collaboration;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final readonly class OrganizationResourceCollaborationAuthorizer implements CollaborationResourceAuthorizer
{
    public function __construct(
        private string $viewPermission,
        private string $editPermission,
        private bool $reviewable = false,
    ) {}

    private function sameOrganization(User $user, Model $resource): bool
    {
        return $user->organization_id !== null
            && (int) $resource->getAttribute('organization_id') === (int) $user->organization_id;
    }

    public function canView(User $user, Model $resource): bool
    {
        return $this->sameOrganization($user, $resource) && ($user->isPlatformAdmin() || $user->hasOrganizationPermission($this->viewPermission));
    }

    public function canEdit(User $user, Model $resource): bool
    {
        return $this->sameOrganization($user, $resource) && ($user->isPlatformAdmin() || $user->hasOrganizationPermission($this->editPermission));
    }

    public function canShare(User $user, Model $resource): bool
    {
        return $this->canEdit($user, $resource);
    }

    public function canComment(User $user, Model $resource): bool
    {
        return $this->canView($user, $resource);
    }

    public function canReview(User $user, Model $resource): bool
    {
        return $this->reviewable && $this->sameOrganization($user, $resource) && $user->isPlatformAdmin();
    }

    public function canAdminister(User $user, Model $resource): bool
    {
        return $this->sameOrganization($user, $resource) && $user->isPlatformAdmin();
    }
}
