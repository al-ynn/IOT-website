<?php
namespace App\Http\Resources\Admin;use Illuminate\Http\Request;use Illuminate\Http\Resources\Json\JsonResource;
class MonitoredDeviceResource extends JsonResource{public function toArray(Request $request):array{return ['id'=>(string)$this->id,'name'=>$this->name,'identifier'=>$this->external_id,'status'=>$this->status==='online'?'online':'offline','lastActivity'=>$this->last_seen?->toISOString(),'organization'=>['id'=>(string)$this->organization_id,'name'=>$this->organization?->name??''],'assignedStaffCount'=>(int)($this->access_assignments_count??0)];}}
