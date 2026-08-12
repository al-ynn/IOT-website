<?php
namespace App\Http\Controllers;
use App\Models\Device;use App\Services\Billing\BillingEntitlementService;use App\Services\Billing\BillingLimitService;use Illuminate\Http\Request;
class DeviceController extends Controller
{
 public function index(Request $r){$this->view($r);return $r->user()->organization->devices()->get()->map(fn($d)=>$this->resource($d));}
 public function show(Request $r,string $device){$this->view($r);return $this->resource($r->user()->organization->devices()->findOrFail($device));}
 public function store(Request $r,BillingEntitlementService $e,BillingLimitService $l){$org=$this->manage($r);$e->requireFeature($org,'devices.management');$l->ensureDeviceAvailable($org);$data=$r->validate(['name'=>'required|string|max:255','type'=>'required|string|max:100','serialNumber'=>'required|string|max:255|unique:devices,external_id','protocol'=>'required|string|max:50','location'=>'nullable|string|max:255','macAddress'=>'nullable|string|max:50']);$d=$org->devices()->create(['name'=>$data['name'],'type'=>$data['type'],'external_id'=>$data['serialNumber'],'protocol'=>$data['protocol'],'location'=>$data['location']??null,'mac_address'=>$data['macAddress']??null]);return response()->json($this->resource($d),201);}
 public function destroy(Request $r,string $device){$org=$this->manage($r);$org->devices()->findOrFail($device)->delete();return response()->noContent();}
 private function view(Request $r){abort_unless($r->user()->organization&&$r->user()->hasOrganizationPermission('device.view'),403);}
 private function manage(Request $r){abort_unless($r->user()->organization&&$r->user()->hasOrganizationPermission('device.manage'),403);return $r->user()->organization;}
 private function resource(Device $d):array{return ['id'=>(string)$d->id,'name'=>$d->name,'type'=>$d->type,'serialNumber'=>$d->external_id,'status'=>$d->status,'location'=>$d->location,'lastSeen'=>$d->last_seen?->toISOString(),'battery'=>$d->battery,'firmwareVersion'=>$d->firmware_version,'protocol'=>$d->protocol,'macAddress'=>$d->mac_address];}
}
