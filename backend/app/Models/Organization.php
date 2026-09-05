<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = ['name', 'slug', 'settings', 'status'];
    protected $casts = ['settings'=>'array'];

    public function users() { return $this->hasMany(User::class); }
    public function devices() { return $this->hasMany(Device::class); }
    public function locations() { return $this->hasMany(Location::class); }
    public function dashboards() { return $this->hasMany(Dashboard::class); }
    public function invitations() { return $this->hasMany(Invitation::class); }
    public function automations() { return $this->hasMany(Automation::class); }
    public function deviceTemplates() { return $this->hasMany(DeviceTemplate::class); }
    public function operationalEvents() { return $this->hasMany(OperationalEvent::class); }
    public function provisioningSessions() { return $this->hasMany(ProvisioningSession::class); }
    public function firmwareArtifacts() { return $this->hasMany(FirmwareArtifact::class); }
    public function firmwareDeployments() { return $this->hasMany(FirmwareDeployment::class); }
    public function webhooks() { return $this->hasMany(Webhook::class); }
    public function crashReports() { return $this->hasMany(CrashReport::class); }
    public function reports() { return $this->hasMany(Report::class); }
}
