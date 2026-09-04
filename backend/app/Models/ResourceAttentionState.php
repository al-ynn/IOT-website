<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ResourceAttentionState extends Model
{
    protected $fillable = ['resource_type','resource_id','status','reason_code','note','marked_by','marked_at','updated_by','resolved_by','resolved_at'];
    protected $casts = ['marked_at'=>'datetime','resolved_at'=>'datetime'];
    public function marker() { return $this->belongsTo(User::class, 'marked_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
    public function resolver() { return $this->belongsTo(User::class, 'resolved_by'); }
}
