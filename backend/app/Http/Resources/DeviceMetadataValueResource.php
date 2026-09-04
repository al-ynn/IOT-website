<?php
namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;
final class DeviceMetadataValueResource extends JsonResource {
    public static $wrap = null;
    public function toArray($request): array { $d=$this->definition; return ['id'=>(string)$this->id,'definitionId'=>(string)$this->metadata_definition_id,'key'=>$d?->key,'name'=>$d?->name,'type'=>$d?->data_type,'required'=>(bool)($d?->required),'value'=>$this->value,'updatedAt'=>$this->updated_at?->toISOString()]; }
}
