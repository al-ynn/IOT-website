<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrashReport extends Model
{
    protected $fillable = ['organization_id', 'device_id', 'operational_event_id', 'client_report_id', 'crash_type', 'reason', 'message', 'firmware_version', 'runtime_version', 'uptime_seconds', 'reboot_reason', 'stack_trace', 'context', 'reported_at', 'received_at'];

    protected $casts = ['context' => 'array', 'reported_at' => 'datetime', 'received_at' => 'datetime'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function operationalEvent()
    {
        return $this->belongsTo(OperationalEvent::class);
    }
}
