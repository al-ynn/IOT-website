<?php

namespace App\Lifecycle;

final readonly class ResourceLifecyclePolicy
{
    public function __construct(public string $type,public bool $archive,public bool $viewDisabled=true,public bool $commentDisabled=true){}
}
