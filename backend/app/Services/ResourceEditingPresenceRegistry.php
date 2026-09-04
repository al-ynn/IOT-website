<?php
namespace App\Services;use Illuminate\Validation\ValidationException;
final class ResourceEditingPresenceRegistry{private const TYPES=['device','device_template','dashboard','automation','report','webhook','location','firmware'];public function assertSupported(string $type):void{if(!in_array($type,self::TYPES,true))throw ValidationException::withMessages(['resource_type'=>['Editing presence is not supported for this resource type.']]);}public function types():array{return self::TYPES;}}
