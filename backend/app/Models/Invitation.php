<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Invitation extends Model{protected $fillable=['organization_id','email','role','status','invited_by','token_hash','expires_at'];protected $casts=['expires_at'=>'datetime'];public function organization(){return $this->belongsTo(Organization::class);}public function inviter(){return $this->belongsTo(User::class,'invited_by');}}
