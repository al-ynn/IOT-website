<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = ['organization_id', 'created_by', 'device_template_id', 'device_template_revision_id', 'device_template_publication_version_id', 'name', 'external_id', 'status', 'type', 'protocol', 'location', 'location_id', 'mac_address', 'last_seen', 'battery', 'firmware_version'];

    protected $casts = ['last_seen' => 'datetime', 'battery' => 'integer'];

    public function canonicalLocation(){return $this->belongsTo(Location::class,'location_id');}
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(){return $this->belongsTo(User::class, 'created_by');}

    public function telemetryRecords()
    {
        return $this->hasMany(TelemetryRecord::class);
    }

    public function accessAssignments()
    {
        return $this->hasMany(DeviceAccessAssignment::class);
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'device_access_assignments')->withPivot(['access_level', 'assigned_by'])->withTimestamps();
    }

    public function monitoringUsers()
    {
        return $this->belongsToMany(User::class, 'user_monitored_devices')->withTimestamps();
    }

    public function parameters()
    {
        return $this->hasMany(DeviceParameter::class);
    }

    public function metadataValues() { return $this->hasMany(DeviceMetadataValue::class); }
    public function eventFacts() { return $this->hasMany(DeviceEventFact::class); }

    public function template()
    {
        return $this->belongsTo(DeviceTemplate::class, 'device_template_id');
    }

    public function operationalEvents()
    {
        return $this->hasMany(OperationalEvent::class);
    }

    public function provisioningSessions()
    {
        return $this->hasMany(ProvisioningSession::class);
    }

    public function firmwareDeploymentTargets()
    {
        return $this->hasMany(FirmwareDeploymentDevice::class);
    }

    public function credentials()
    {
        return $this->hasMany(DeviceCredential::class);
    }

    public function crashReports()
    {
        return $this->hasMany(CrashReport::class);
    }

    public function snapshots()
    {
        return $this->hasMany(DeviceSnapshot::class);
    }

    public function dashboard()
    {
        return $this->hasOne(Dashboard::class);
    }

    public function revisions()
    {
        return $this->hasMany(ResourceRevision::class, 'resource_id')->where('resource_type', 'device')->orderBy('revision_number');
    }

    public function latestRevision()
    {
        return $this->hasOne(ResourceRevision::class, 'resource_id')
            ->where('resource_revisions.resource_type', 'device')
            ->latestOfMany();
    }

    public function lifecycleState()
    {
        return $this->hasOne(ResourceLifecycleState::class, 'resource_id')
            ->where('resource_type', 'device');
    }
}
