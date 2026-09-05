<?php

namespace App\Collaboration;

final readonly class CollaborationResourceReference
{
    public function __construct(public string $type, public int|string $id) {}
}
