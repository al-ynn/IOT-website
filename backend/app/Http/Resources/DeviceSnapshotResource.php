<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'deviceId' => (string) $this->device_id, 'name' => $this->name, 'description' => $this->description, 'payload' => $this->payload, 'capturedAt' => $this->captured_at?->toISOString(), 'createdBy' => $this->creator ? ['id' => (string) $this->creator->id, 'name' => $this->creator->name] : null];
    }
}
