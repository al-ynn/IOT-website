<?php
namespace App\Http\Controllers;
use App\Http\Resources\DeviceEventFactResource;
use App\Models\DeviceEventFact;
use App\Services\Admin\DeviceAccessService;
use App\Models\DeviceTemplateEventDefinition;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
final class DeviceEventController {
 public function index(Request $r,string $device,DeviceAccessService $a){$d=$a->findViewableDeviceOrFail($r->user(),$device);$f=$r->validate(['severity'=>['nullable',Rule::in(DeviceTemplateEventDefinition::SEVERITIES)],'code'=>'nullable|string|max:80','from'=>'nullable|date','to'=>'nullable|date','per_page'=>'nullable|integer|min:1|max:100']);$q=DeviceEventFact::where('device_id',$d->id)->when($f['severity']??null,fn($x,$v)=>$x->where('severity',$v))->when($f['code']??null,fn($x,$v)=>$x->where('event_code',$v))->when($f['from']??null,fn($x,$v)=>$x->where('occurred_at','>=',$v))->when($f['to']??null,fn($x,$v)=>$x->where('occurred_at','<=',$v));return DeviceEventFactResource::collection($q->orderByDesc('occurred_at')->orderByDesc('id')->paginate($f['per_page']??25)->withQueryString());}
}
