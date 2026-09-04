<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;class CommentAcknowledgment extends Model{protected $fillable=['thread_id','user_id','acknowledged_at'];protected $casts=['acknowledged_at'=>'datetime'];public function user(){return $this->belongsTo(User::class);}}
