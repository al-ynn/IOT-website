<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id','name','description','price','currency','interval','features','device_limit','user_limit','dashboard_limit','automation_limit','active','is_default'];
    protected $casts = ['features'=>'array','price'=>'decimal:2','active'=>'boolean','is_default'=>'boolean'];
    public function subscriptions() { return $this->hasMany(Subscription::class); }
}
