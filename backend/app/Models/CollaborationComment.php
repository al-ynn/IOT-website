<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class CollaborationComment extends Model{protected $fillable=['thread_id','parent_comment_id','body','created_by','edited_at'];protected $casts=['edited_at'=>'datetime'];public function thread(){return $this->belongsTo(CollaborationThread::class,'thread_id');}public function author(){return $this->belongsTo(User::class,'created_by');}public function mentions(){return $this->belongsToMany(User::class,'comment_mentions','comment_id','user_id')->withTimestamps();}}
