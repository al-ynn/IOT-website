<?php
namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;
final class DeviceEventFactResource extends JsonResource { public static $wrap=null; public function toArray($r):array{return ['id'=>(string)$this->id,'deviceId'=>(string)$this->device_id,'code'=>$this->event_code,'name'=>$this->event_name,'severity'=>$this->severity,'message'=>$this->message,'value'=>$this->value,'occurredAt'=>$this->occurred_at?->toISOString(),'recordedAt'=>$this->created_at?->toISOString()];} }
