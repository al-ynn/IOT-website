<?php
namespace App\Http\Controllers;
use App\Http\Resources\DeviceEventFactResource;
use App\Models\DeviceEventFact;
use App\Services\Admin\DeviceAccessService;
use Illuminate\Http\Request;
final class DeviceEventFeedController {
 public function index(Request $r,DeviceAccessService $access){$ids=$access->accessibleDevices($r->user())->pluck('id');$page=DeviceEventFact::whereIn('device_id',$ids)->with('device:id,name,device_template_id')->orderByDesc('occurred_at')->orderByDesc('id')->paginate(min((int)$r->input('per_page',100),100));return DeviceEventFactResource::collection($page);}
}
