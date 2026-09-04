<?php
namespace App\Services;
use App\Models\User;
final class ReminderSchedulingService{
 public function __construct(private ReminderEligibilityRegistry$eligibility,private RevisionReminderService$revisions){}
 public function status(User$user,string$targetType,string$resourceType,int|string$id):array{$this->eligibility->assertResource($targetType,$resourceType);return$this->revisions->status($user,$resourceType,$id)+['targetType'=>$targetType,'resourceType'=>$resourceType];}
 public function schedule(User$user,string$targetType,string$resourceType,int|string$id,string$preset):array{$definition=$this->eligibility->assertResource($targetType,$resourceType);if(!in_array($preset,$definition['presets'],true))throw \Illuminate\Validation\ValidationException::withMessages(['preset'=>['Unsupported reminder preset.']]);return$this->revisions->schedule($user,$resourceType,$id,$preset)+['targetType'=>$targetType,'resourceType'=>$resourceType];}
 public function cancel(User$user,string$targetType,string$resourceType,int|string$id):array{$this->eligibility->assertResource($targetType,$resourceType);$this->revisions->cancel($user,$resourceType,$id);return$this->revisions->status($user,$resourceType,$id)+['targetType'=>$targetType,'resourceType'=>$resourceType];}
}