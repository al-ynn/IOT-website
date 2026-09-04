<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
final class ResourceCollaborator extends Model{protected $fillable=['resource_type','resource_id','user_id','permission','granted_by'];public function user(){return $this->belongsTo(User::class);}public function granter(){return $this->belongsTo(User::class,'granted_by');}}
