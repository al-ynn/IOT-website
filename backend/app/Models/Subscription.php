<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = ['organization_id','plan_id','status','provider_transaction_id','cancel_at_period_end','current_period_start','current_period_end'];
    protected $casts = ['cancel_at_period_end'=>'boolean','current_period_start'=>'datetime','current_period_end'=>'datetime'];
    public function organization() { return $this->belongsTo(Organization::class); }
    public function plan() { return $this->belongsTo(Plan::class); }
}
