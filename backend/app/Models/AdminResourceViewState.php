<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
final class AdminResourceViewState extends Model{public$timestamps=false;protected$guarded=[];protected$casts=['first_viewed_at'=>'datetime','last_viewed_at'=>'datetime'];public function revision(){return$this->belongsTo(ResourceRevision::class,'last_viewed_meaningful_revision_id');}}