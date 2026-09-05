<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceAccessAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'accessLevel' => $this->access_level,
            'device' => [
                'id' => (string) $this->device_id,
                'name' => $this->device?->name ?? '',
                'identifier' => $this->device?->external_id,
                'organizationName' => $this->device?->organization?->name ?? '',
            ],
            'user' => [
                'id' => (string) $this->user_id,
                'name' => $this->user?->name ?? '',
                'email' => $this->user?->email ?? '',
                'organizationName' => $this->user?->organization?->name ?? '',
                'role' => $this->user?->isPlatformAdmin() ? 'admin' : 'staff',
            ],
            'assignedBy' => $this->assignedBy ? [
                'id' => (string) $this->assignedBy->id,
                'name' => $this->assignedBy->name,
            ] : null,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'capabilities' => [
                'canUpgrade' => $this->access_level === 'viewer',
                'canDowngrade' => $this->access_level === 'full_access',
                'canRevoke' => true,
            ],
        ];
    }
}
