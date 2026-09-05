<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GlobalDeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'canonicalId' => (string) $this->id,
            'name' => $this->name,
            'identifier' => $this->external_id,
            'status' => $this->status === 'online' ? 'online' : 'offline',
            'lastActivity' => $this->last_seen?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'organization' => [
                'id' => (string) $this->organization_id,
                'name' => $this->organization?->name ?? '',
            ],
            'creator' => $this->creator ? ['id' => (string) $this->creator->id, 'name' => $this->creator->name] : null,
            'template' => $this->template ? ['id' => (string) $this->template->id, 'name' => $this->template->name] : null,
            'assignedStaffCount' => (int) ($this->access_assignments_count ?? 0),
            'type' => $this->type,
            'protocol' => $this->protocol,
            'location' => $this->canonicalLocation ? ['id'=>(string)$this->canonicalLocation->id,'name'=>$this->canonicalLocation->name,'lifecycle'=>app(\App\Services\ResourceLifecycleService::class)->state('location',$this->canonicalLocation->id),'isAssignable'=>app(\App\Services\ResourceLifecycleService::class)->state('location',$this->canonicalLocation->id)==='active'] : null,
            'macAddress' => $this->mac_address,
            'battery' => $this->battery,
            'firmwareVersion' => $this->firmware_version,
            'capabilities' => ['canView' => true, 'canEdit' => true, 'canShare' => false, 'canEditDashboard' => true, 'canEditParameters' => true, 'canManageCredentials' => true, 'canManageAccess' => true, 'canViewAdminMetadata' => true],
        ];
    }
}
