<?php

namespace App\Collaboration;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

interface CollaborationResourceAuthorizer
{
    public function canView(User $user, Model $resource): bool;

    public function canEdit(User $user, Model $resource): bool;

    public function canShare(User $user, Model $resource): bool;

    public function canComment(User $user, Model $resource): bool;

    public function canReview(User $user, Model $resource): bool;

    public function canAdminister(User $user, Model $resource): bool;
}
