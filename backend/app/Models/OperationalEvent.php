<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalEvent extends Model
{
    public const SEVERITIES = ['info', 'warning', 'error'];

    public const SOURCES = ['automation', 'provisioning', 'firmware', 'webhook', 'device'];

    public const TYPES = ['automation_execution_failed', 'provisioning_session_failed', 'provisioning_session_expired', 'firmware_delivery_unavailable', 'webhook_delivery_failed', 'device_crash_reported'];

    protected $fillable = ['organization_id', 'device_id', 'device_bound', 'automation_id', 'automation_execution_id', 'provisioning_session_id', 'firmware_deployment_id', 'source', 'event_type', 'severity', 'title', 'message', 'context', 'occurred_at'];

    protected $casts = ['context' => 'array', 'device_bound' => 'boolean', 'occurred_at' => 'datetime'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function automation()
    {
        return $this->belongsTo(Automation::class);
    }

    public function automationExecution()
    {
        return $this->belongsTo(AutomationExecution::class);
    }

    public function provisioningSession()
    {
        return $this->belongsTo(ProvisioningSession::class);
    }

    public function firmwareDeployment()
    {
        return $this->belongsTo(FirmwareDeployment::class);
    }
}
