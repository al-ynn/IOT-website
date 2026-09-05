<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;class CollaborationThreadTransition extends Model{public $timestamps=false;protected $fillable=['thread_id','from_status','to_status','actor_id','created_at'];protected $casts=['created_at'=>'datetime'];public function thread(){return $this->belongsTo(CollaborationThread::class,'thread_id');}}
