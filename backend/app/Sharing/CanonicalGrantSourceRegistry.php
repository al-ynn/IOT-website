<?php
namespace App\Sharing;
use Illuminate\Validation\ValidationException;
final class CanonicalGrantSourceRegistry
{
 private const DEFINITIONS=[
  'device'=>['source'=>'device_access_assignments','permissions'=>['viewer','full_access'],'request'=>true,'approval'=>'admin_required','transitive'=>false],
  'device_template'=>['source'=>'resource_collaborators','permissions'=>['view','edit'],'request'=>true,'approval'=>'recipient_acceptance','transitive'=>false],
  'dashboard'=>['source'=>'resource_collaborators','permissions'=>['view','edit'],'request'=>true,'approval'=>'recipient_acceptance','transitive'=>false],
  'automation'=>['source'=>'resource_collaborators','permissions'=>['view','edit'],'request'=>true,'approval'=>'recipient_acceptance','transitive'=>false],
  'report'=>['source'=>'resource_collaborators','permissions'=>['view','edit'],'request'=>true,'approval'=>'recipient_acceptance','transitive'=>false],
  'location'=>['source'=>'resource_collaborators','permissions'=>['view','edit'],'request'=>true,'approval'=>'recipient_acceptance','transitive'=>false],
 ];
 public function definition(string$type):array{if(!isset(self::DEFINITIONS[$type]))throw ValidationException::withMessages(['resource_type'=>['This resource type has no active sharing grant policy.']]);return['resourceType'=>$type,...self::DEFINITIONS[$type]];}
 public function types():array{return array_keys(self::DEFINITIONS);}
 public function assertPermission(string$type,string$permission):void{if(!in_array($permission,$this->definition($type)['permissions'],true))throw ValidationException::withMessages(['permission'=>['Unsupported permission for this resource type.']]);}
}
