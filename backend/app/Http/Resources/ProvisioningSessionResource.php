<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProvisioningSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'organization' => ['id' => (string) $this->organization_id, 'name' => $this->organization?->name],
            'initiatedBy' => $this->initiator ? ['id' => (string) $this->initiator->id, 'name' => $this->initiator->name, 'email' => $this->initiator->email] : null,
            'device' => $this->device ? ['id' => (string) $this->device->id, 'name' => $this->device->name, 'identifier' => $this->device->external_id] : null,
            'template' => $this->template ? ['id' => (string) $this->template->id, 'name' => $this->template->name] : null,
            'startedAt' => $this->started_at?->toISOString(),
            'expiresAt' => $this->expires_at?->toISOString(),
            'completedAt' => $this->completed_at?->toISOString(),
            'failedAt' => $this->failed_at?->toISOString(),
            'failureCode' => $this->failure_code,
            'failureMessage' => $this->failure_message,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
