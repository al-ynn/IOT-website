<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;

final class CurrentOrganization
{
    public function for(User $user): ?Organization
    {
        if (! $user->organization_id) {
            return null;
        }

        return $user->organization()->whereKey($user->organization_id)->first();
    }

    public function require(User $user): Organization
    {
        $organization = $this->for($user);

        abort_unless($organization && $organization->status === 'active', 403, 'An active organization context is required.');

        return $organization;
    }
}
