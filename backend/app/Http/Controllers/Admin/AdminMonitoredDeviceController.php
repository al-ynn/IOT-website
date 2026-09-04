<?php
namespace App\Http\Controllers\Admin;use App\Http\Controllers\Controller;use App\Http\Resources\Admin\MonitoredDeviceResource;use App\Models\Device;use App\Services\Admin\AdminMonitoredDeviceService;use Illuminate\Http\Request;
class AdminMonitoredDeviceController extends Controller{
 public function index(Request $request,AdminMonitoredDeviceService $service){$data=$service->overview($request->user());return ['summary'=>$data['summary'],'devices'=>MonitoredDeviceResource::collection($data['devices'])->resolve($request),'displayLimit'=>AdminMonitoredDeviceService::DISPLAY_LIMIT];}
 public function store(Request $request,AdminMonitoredDeviceService $service){$data=$request->validate(['device_id'=>['required','integer','exists:devices,id']]);$device=Device::findOrFail($data['device_id']);$service->add($request->user(),$device);return response()->json(['monitored'=>true],201);}
 public function destroy(Request $request,Device $device,AdminMonitoredDeviceService $service){$service->remove($request->user(),$device);return response()->noContent();}
}
