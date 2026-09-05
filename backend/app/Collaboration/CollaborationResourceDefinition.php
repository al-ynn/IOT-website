<?php

namespace App\Collaboration;

use Illuminate\Database\Eloquent\Model;

final readonly class CollaborationResourceDefinition
{
    /** @param class-string<Model> $modelClass */
    public function __construct(
        public string $type,
        public string $modelClass,
        public CollaborationResourceAuthorizer $authorizer,
        public array $capabilities,
        public array $permissions,
        public bool $sectionComments = false,
    ) {}

    public function metadata(): array
    {
        return [
            'type' => $this->type,
            'capabilities' => $this->capabilities,
            'permissions' => $this->permissions,
            'sectionComments' => $this->sectionComments,
        ];
    }
}
