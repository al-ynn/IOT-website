<?php
namespace App\Services;
use App\Revisions\ResourceUpdatePolicyRegistry;
use Illuminate\Validation\ValidationException;
final class ReminderEligibilityRegistry{
 private const TARGETS=['resource_update'=>['workflow'=>'pull_managed_update','presets'=>['one_hour','three_hours','tomorrow','none'],'customTime'=>false,'deepLink'=>'resource_update']];
 public function __construct(private ResourceUpdatePolicyRegistry $policies){}
 public function definition(string$type):array{if(!isset(self::TARGETS[$type]))throw ValidationException::withMessages(['target_type'=>['Unsupported reminder target.']]);return['targetType'=>$type,...self::TARGETS[$type]];}
 public function assertResource(string$targetType,string$resourceType):array{$d=$this->definition($targetType);if(!$this->policies->isPullManaged($resourceType))throw ValidationException::withMessages(['resource_type'=>['This resource does not support reminders.']]);return$d;}
 public function metadata():array{return self::TARGETS;}
}
