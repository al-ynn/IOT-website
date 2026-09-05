<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
final class ResourceDraft extends Model{protected $guarded=[];protected $casts=['snapshot'=>'array','draft_schema_version'=>'integer'];public function user(){return $this->belongsTo(User::class);}public function baseRevision(){return $this->belongsTo(ResourceRevision::class,'base_revision_id');}}
