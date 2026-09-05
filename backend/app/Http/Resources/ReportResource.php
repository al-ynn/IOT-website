<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $last = $this->relationLoaded('runs') ? $this->runs->first() : null;

        return ['id' => (string) $this->id, 'name' => $this->name, 'description' => $this->description, 'reportType' => $this->report_type, 'configuration' => $this->configuration, 'saveOutcome' => $this->getAttribute('save_idempotency_outcome'), 'createdBy' => $this->creator ? ['id' => (string) $this->creator->id, 'name' => $this->creator->name] : null, 'updatedBy' => $this->updater ? ['id' => (string) $this->updater->id, 'name' => $this->updater->name] : null, 'lastRun' => $last ? ['id' => (string) $last->id, 'status' => $last->status, 'createdAt' => $last->created_at?->toISOString()] : null, 'createdAt' => $this->created_at?->toISOString(), 'updatedAt' => $this->updated_at?->toISOString()];
    }
}
