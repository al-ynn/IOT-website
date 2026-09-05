<?php
namespace App\Revisions;
use App\Models\FirmwareArtifact;
final class FirmwareRevisionSnapshotBuilder
{
    public const SCHEMA_VERSION=1;
    public function build(FirmwareArtifact $artifact):array{return ['metadata'=>['name'=>$artifact->name,'version'=>$artifact->version,'description'=>$artifact->description,'device_type'=>$artifact->device_type,'protocol'=>$artifact->protocol,'device_template_id'=>$artifact->device_template_id],'artifact'=>['id'=>(int)$artifact->id,'filename'=>$artifact->original_filename,'mime_type'=>$artifact->mime_type,'size_bytes'=>(int)$artifact->size_bytes,'sha256'=>$artifact->sha256]];}
}
