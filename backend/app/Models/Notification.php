<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;class Notification extends Model{protected $fillable=['organization_id','user_id','title','message','severity','read_at'];protected $casts=['read_at'=>'datetime'];}
