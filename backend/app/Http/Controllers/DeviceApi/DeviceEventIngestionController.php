<?php
namespace App\Http\Controllers\DeviceApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceEventFactResource;
use App\Models\DeviceEventFact;
use App\Models\DeviceTemplateEventDefinition;
use App\Models\User;
use App\Services\NotificationOutboxService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
final class DeviceEventIngestionController extends Controller {
 public function store(Request $r){$device=$r->attributes->get('device');$unknown=array_diff(array_keys($r->all()),['code','message','value']);if($unknown)throw ValidationException::withMessages(['payload'=>['Unknown event fields are not allowed.']]);$d=$r->validate(['code'=>'required|string|max:80|regex:/^[a-z][a-z0-9_]*$/','message'=>'nullable|string|max:1000','value'=>'nullable']);if(array_key_exists('value',$d)&&strlen(json_encode($d['value']))>2048)throw ValidationException::withMessages(['value'=>['Event value is too large.']]);$def=DeviceTemplateEventDefinition::where('device_template_id',$device->device_template_id)->where('code',$d['code'])->where('enabled',true)->first();if(!$def)throw ValidationException::withMessages(['code'=>['Unknown or disabled event definition.']]);$fact=DeviceEventFact::create(['device_id'=>$device->id,'event_definition_id'=>$def->id,'event_code'=>$def->code,'severity'=>$def->severity,'event_name'=>$def->name,'message'=>$d['message']??null,'value'=>$d['value']??null,'occurred_at'=>now('UTC')]);$device->accessAssignments()->with('user')->get()->each(function($grant)use($device,$fact){$recipient=$grant->user;if(!$recipient||$recipient->status!=='active')return;app(NotificationOutboxService::class)->recordNotification($recipient,'device.event',"device-event:{$fact->id}",['organization_id'=>$device->organization_id,'resource_type'=>'device','resource_id'=>$device->id,'action_url'=>"/app/devices/{$device->id}?tab=events",'data'=>['device_name'=>$device->name,'event_name'=>$fact->event_name,'severity'=>$fact->severity],'title'=>"{$fact->event_name} on {$device->name}",'message'=>$fact->message??"{$fact->event_name} event received.",'severity'=>$fact->severity,'requires_action'=>false]);});return (new DeviceEventFactResource($fact))->response()->setStatusCode(201);}
}
