<?php
namespace App\Services;use App\Models\Device;use App\Models\DeviceCredential;use App\Models\User;use Illuminate\Support\Str;
class DeviceCredentialService {
 public function create(User $actor,Device $device,array $data):array{app(ResourceLifecycleService::class)->assertActive('device',$device->id,'Restore the Device before issuing credentials.');$public=(string)Str::uuid();$secret=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$token="iotd_{$public}_{$secret}";$credential=$device->credentials()->create(['public_id'=>$public,'name'=>$data['name'],'token_prefix'=>'iotd_'.substr($public,0,8).'…','token_hash'=>hash('sha256',$secret),'scopes'=>$data['scopes']??['telemetry:write'],'expires_at'=>$data['expires_at']??null,'created_by'=>$actor->id]);return [$credential->load('device:id,name,external_id'),$token];}
 public function revoke(DeviceCredential $credential):DeviceCredential{if(!$credential->revoked_at)$credential->update(['revoked_at'=>now()]);return $credential->refresh()->load('device:id,name,external_id');}
}
