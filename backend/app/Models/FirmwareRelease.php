<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class FirmwareRelease extends Model
{
    protected $guarded=[];protected $casts=['approved_at'=>'datetime'];
    public function firmware(){return $this->belongsTo(FirmwareArtifact::class,'firmware_artifact_id');}
    public function revision(){return $this->belongsTo(ResourceRevision::class,'resource_revision_id');}
    public function submission(){return $this->belongsTo(ResourcePublicationSubmission::class,'review_submission_id');}
    public function approver(){return $this->belongsTo(User::class,'approved_by');}
}
