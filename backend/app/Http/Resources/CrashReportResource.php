<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrashReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'clientReportId' => $this->client_report_id, 'crashType' => $this->crash_type, 'reason' => $this->reason, 'message' => $this->message, 'firmwareVersion' => $this->firmware_version, 'runtimeVersion' => $this->runtime_version, 'uptimeSeconds' => $this->uptime_seconds, 'rebootReason' => $this->reboot_reason, 'stackTrace' => $this->stack_trace, 'context' => $this->context ?? [], 'reportedAt' => $this->reported_at?->toISOString(), 'receivedAt' => $this->received_at?->toISOString(), 'device' => $this->device ? ['id' => (string) $this->device->id, 'name' => $this->device->name, 'identifier' => $this->device->external_id] : null, 'organization' => $this->organization ? ['id' => (string) $this->organization->id, 'name' => $this->organization->name] : null, 'operationalEventId' => $this->operational_event_id ? (string) $this->operational_event_id : null, 'createdAt' => $this->created_at?->toISOString()];
    }
}
