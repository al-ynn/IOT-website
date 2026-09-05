<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'reportId' => (string) $this->report_id, 'reportRevisionId' => $this->report_revision_id ? (string) $this->report_revision_id : null, 'publicationVersionId' => $this->publication_version_id ? (string) $this->publication_version_id : null, 'status' => $this->status, 'resolvedConfiguration' => $this->resolved_configuration, 'requestedBy' => $this->requester ? ['id' => (string) $this->requester->id, 'name' => $this->requester->name] : null, 'startedAt' => $this->started_at?->toISOString(), 'completedAt' => $this->completed_at?->toISOString(), 'failedAt' => $this->failed_at?->toISOString(), 'failureCode' => $this->failure_code, 'failureMessage' => $this->failure_message, 'rowCount' => $this->row_count, 'artifactFormat' => $this->artifact_format, 'artifactSize' => $this->artifact_size, 'downloadAvailable' => $this->status === 'completed' && $this->artifact_path !== null, 'createdAt' => $this->created_at?->toISOString()];
    }
}
