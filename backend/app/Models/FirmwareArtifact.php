<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FirmwareArtifact extends Model
{
 protected $fillable=['organization_id','device_template_id','uploaded_by','name','version','description','device_type','protocol','storage_disk','storage_path','original_filename','mime_type','size_bytes','sha256'];
 public function organization(){return $this->belongsTo(Organization::class);}public function template(){return $this->belongsTo(DeviceTemplate::class,'device_template_id');}public function uploader(){return $this->belongsTo(User::class,'uploaded_by');}public function creator(){return $this->uploader();}public function deployments(){return $this->hasMany(FirmwareDeployment::class);}public function collaborators(){return $this->hasMany(ResourceCollaborator::class,'resource_id')->where('resource_type','firmware');}public function releases(){return $this->hasMany(FirmwareRelease::class);}public function currentRelease(){return $this->hasOne(FirmwareRelease::class)->where('is_current',true);}public function revisions(){return $this->hasMany(ResourceRevision::class,'resource_id')->where('resource_type','firmware');}
}
