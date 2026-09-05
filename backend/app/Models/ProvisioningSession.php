<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProvisioningSession extends Model
{
    public const STATUSES = ['pending', 'completed', 'failed', 'expired', 'cancelled'];

    protected $fillable = ['organization_id', 'initiated_by', 'device_id', 'device_template_id', 'name', 'status', 'expires_at', 'started_at', 'completed_at', 'failed_at', 'failure_code', 'failure_message'];
    protected $casts = ['expires_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'failed_at' => 'datetime'];

    public function organization() { return $this->belongsTo(Organization::class); }
    public function initiator() { return $this->belongsTo(User::class, 'initiated_by'); }
    public function device() { return $this->belongsTo(Device::class); }
    public function template() { return $this->belongsTo(DeviceTemplate::class, 'device_template_id'); }
    public function operationalEvents() { return $this->hasMany(OperationalEvent::class); }
}
