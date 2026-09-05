<?php
namespace App\Models;use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;
final class ResourceEditingSession extends Model{use HasUuids;public $timestamps=false;protected $guarded=[];protected $casts=['started_at'=>'datetime','last_heartbeat_at'=>'datetime','expires_at'=>'datetime'];public function user(){return $this->belongsTo(User::class);}}
