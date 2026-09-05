<?php

namespace App\Collaboration;

use Illuminate\Database\Eloquent\Model;

final readonly class ResolvedCollaborationResource
{
    public function __construct(
        public CollaborationResourceDefinition $definition,
        public Model $resource,
    ) {}
}
