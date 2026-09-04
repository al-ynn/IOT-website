<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FirmwareArtifactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'name' => $this->name, 'version' => $this->version, 'description' => $this->description, 'deviceType' => $this->device_type, 'protocol' => $this->protocol, 'template' => $this->template ? ['id' => (string) $this->template->id, 'name' => $this->template->name] : null, 'file' => ['name' => $this->original_filename, 'mimeType' => $this->mime_type, 'sizeBytes' => $this->size_bytes, 'sha256' => $this->sha256], 'uploadedBy' => $this->uploader ? ['id' => (string) $this->uploader->id, 'name' => $this->uploader->name] : null, 'deploymentCount' => (int) ($this->deployments_count ?? 0), 'releaseEligible' => (bool) $this->currentRelease, 'currentRelease' => $this->currentRelease ? ['id' => (string) $this->currentRelease->id, 'version' => $this->currentRelease->domain_version, 'approvedAt' => $this->currentRelease->approved_at?->toISOString()] : null, 'createdAt' => $this->created_at?->toISOString(), 'updatedAt' => $this->updated_at?->toISOString()];
    }
}
