<?php
namespace App\Http\Controllers\Automation;
use App\Http\Controllers\Controller;use App\Services\Admin\DeviceAccessService;use App\Services\Automation\AutomationTriggerEngine;use Illuminate\Http\Request;
class AutomationEventController extends Controller{
 public function __construct(private DeviceAccessService $deviceAccess){}
 public function store(Request $r,AutomationTriggerEngine $engine,DeviceAccessService $access){$org=$r->user()->organization;abort_unless($org&&$r->user()->hasOrganizationPermission('automation.execute'),403);$data=$r->validate(['type'=>'required|in:telemetry,device_status','deviceId'=>'required|integer','field'=>'required|string|max:100','value'=>'nullable','timestamp'=>'required|date','correlationId'=>'nullable|uuid']);$access->findManageableDeviceOrFail($r->user(),$data['deviceId']);$context=['deviceId'=>(string)$data['deviceId'],$data['field']=>$data['value'],'field'=>$data['field'],'value'=>$data['value'],'timestamp'=>$data['timestamp']];$executions=$engine->dispatch($org,$data['type'],$context,$data['correlationId']??null);return response()->json(['matched'=>count($executions),'executionIds'=>array_map(fn($e)=>(string)$e->id,$executions)]);}
 public function index(Request $r){$org=$r->user()->organization;abort_unless($org&&$r->user()->hasOrganizationPermission('automation.view'),403);return \App\Models\AutomationExecution::where('organization_id',$org->id)->whereIn('automation_id',$this->deviceAccess->accessibleAutomationIds($r->user()))->latest()->paginate(20);}
}
