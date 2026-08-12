<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = ['name', 'slug', 'settings', 'status'];
    protected $casts = ['settings'=>'array'];

    public function users() { return $this->hasMany(User::class); }
    public function devices() { return $this->hasMany(Device::class); }
    public function dashboards() { return $this->hasMany(Dashboard::class); }
    public function subscription() { return $this->hasOne(Subscription::class)->latestOfMany(); }
    public function subscriptions() { return $this->hasMany(Subscription::class); }
    public function invitations() { return $this->hasMany(Invitation::class); }
    public function automations() { return $this->hasMany(Automation::class); }
}
