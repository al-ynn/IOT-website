<?php

namespace App\Lifecycle;

use Illuminate\Validation\ValidationException;

final class ResourceLifecyclePolicyRegistry
{
    private array $policies;
    public function __construct(){ $this->policies=['device'=>new ResourceLifecyclePolicy('device',true,true,true),'device_template'=>new ResourceLifecyclePolicy('device_template',true,true,true),'firmware'=>new ResourceLifecyclePolicy('firmware',true,true,true),'automation'=>new ResourceLifecyclePolicy('automation',true,true,true),'report'=>new ResourceLifecyclePolicy('report',true,true,true),'location'=>new ResourceLifecyclePolicy('location',true,true,true),'dashboard'=>new ResourceLifecyclePolicy('dashboard',true,true,true),'webhook'=>new ResourceLifecyclePolicy('webhook',true,true,true)]; }
    public function get(string $type):ResourceLifecyclePolicy{if(!isset($this->policies[$type]))throw ValidationException::withMessages(['resource_type'=>['Unsupported lifecycle resource type.']]);return $this->policies[$type];}
    public function types():array{return array_keys($this->policies);}
}
