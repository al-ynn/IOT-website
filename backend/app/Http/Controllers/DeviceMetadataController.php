<?php
namespace App\Http\Controllers;
use App\Http\Resources\DeviceMetadataValueResource;
use App\Services\Admin\DeviceAccessService;
use App\Services\DeviceMetadataService;
use Illuminate\Http\Request;
final class DeviceMetadataController {
 public function index(Request $r,string $device,DeviceAccessService $a,DeviceMetadataService $s){$d=$a->findViewableDeviceOrFail($r->user(),$device);$vals=$s->values($d);return response()->json(['data'=>$s->definitions($d)->map(fn($def)=>['definitionId'=>(string)$def->id,'key'=>$def->key,'name'=>$def->name,'type'=>$def->data_type,'description'=>$def->description,'required'=>(bool)$def->required,'configuration'=>$def->configuration,'value'=>$vals->get($def->id)?->value])]);}
 public function store(Request $r,string $device,DeviceAccessService $a,DeviceMetadataService $s){$d=$a->findManageableDeviceOrFail($r->user(),$device);$keys=array_keys($r->all());$unknown=array_diff($keys,['definitionId','key','value']);abort_if($unknown,422,'Unknown metadata fields are not allowed.');$data=$r->validate(['definitionId'=>'nullable|integer','key'=>'nullable|string|max:80','value'=>'present']);abort_unless(isset($data['definitionId'])||isset($data['key']),422,'A metadata definition is required.');return (new DeviceMetadataValueResource($s->set($d,$data)))->response()->setStatusCode(201);}
}
