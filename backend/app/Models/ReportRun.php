<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ReportRun extends Model
{
    use HasUuids;

    public const STATUSES = ['pending', 'running', 'completed', 'failed', 'cancelled'];

    protected $fillable = ['report_id', 'report_revision_id', 'publication_version_id', 'organization_id', 'requested_by', 'status', 'resolved_configuration', 'started_at', 'completed_at', 'failed_at', 'failure_code', 'failure_message', 'row_count', 'artifact_disk', 'artifact_path', 'artifact_format', 'artifact_size'];

    protected $casts = ['resolved_configuration' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'failed_at' => 'datetime'];

    public function report()
    {
        return $this->belongsTo(Report::class)->withTrashed();
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function revision()
    {
        return $this->belongsTo(ResourceRevision::class, 'report_revision_id');
    }

    public function publicationVersion()
    {
        return $this->belongsTo(ResourcePublicationVersion::class, 'publication_version_id');
    }
}
