<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FirmwareDeploymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'status' => $this->status,
            'artifact' => $this->artifact ? ['id' => (string) $this->artifact->id, 'name' => $this->artifact->name, 'version' => $this->artifact->version] : null,
            'createdBy' => $this->creator ? ['id' => (string) $this->creator->id, 'name' => $this->creator->name] : null,
            'failedAt' => $this->failed_at?->toISOString(),
            'failureMessage' => $this->failure_message,
            'createdAt' => $this->created_at?->toISOString(),
            'targets' => $this->whenLoaded('targets', fn () => $this->targets->map(fn ($target) => [
                'id' => (string) $target->id,
                'status' => $target->status,
                'failureCode' => $target->failure_code,
                'failureMessage' => $target->failure_message,
                'device' => $target->device ? ['id' => (string) $target->device->id, 'name' => $target->device->name, 'identifier' => $target->device->external_id] : null,
                'updatedAt' => $target->updated_at?->toISOString(),
            ])),
        ];
    }
}
