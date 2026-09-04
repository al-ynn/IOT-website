<?php
namespace App\Http\Resources;
use App\Services\OperationalEventService;use Illuminate\Http\Request;use Illuminate\Http\Resources\Json\JsonResource;
class OperationalEventResource extends JsonResource { public function toArray(Request $request):array { $safe=app(OperationalEventService::class);return [
 'id'=>(string)$this->id,'source'=>$this->source,'eventType'=>$this->event_type,'severity'=>$this->severity,
 'title'=>$safe->safeText($this->title,120),'message'=>$safe->safeText($this->message),'context'=>$safe->sanitize($this->context??[]),'occurredAt'=>$this->occurred_at?->toISOString(),
 'device'=>$this->device_id?['id'=>(string)$this->device_id,'name'=>$this->device?->name,'available'=>$this->device!==null,'template'=>$this->device?->template?['id'=>(string)$this->device->template->id,'name'=>$this->device->template->name]:null]:null,
 'organization'=>['id'=>(string)$this->organization_id,'name'=>$this->organization?->name??''],
 'automation'=>$this->automation_id?['id'=>(string)$this->automation_id,'name'=>$this->automation?->name,'available'=>$this->automation!==null]:null,
 ];}}
