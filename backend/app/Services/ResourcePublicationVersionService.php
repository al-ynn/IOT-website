<?php

namespace App\Services;

use App\Models\ResourcePublicationState;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourcePublicationVersion;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ResourcePublicationVersionService
{
    public function __construct(private ResourceLifecycleService $lifecycle) {}

    public function publish(ResourcePublicationSubmission $submission, User $admin): ResourcePublicationVersion
    {
        if (! in_array($submission->resource_type, ['device_template', 'automation', 'report', 'dashboard'], true)) {
            throw ValidationException::withMessages(['resource_type' => ['Versioned publication is not active for this resource type.']]);
        }
        $this->lifecycle->lockActiveAuthority($submission->resource_type, $submission->resource_id);
        $revision = ResourceRevision::whereKey($submission->submitted_revision_id)->where(['resource_type' => $submission->resource_type, 'resource_id' => $submission->resource_id])->firstOrFail();
        $existing = ResourcePublicationVersion::where('review_submission_id', $submission->id)->first();
        if ($existing) {
            return $existing;
        }

        $state = ResourcePublicationState::where(['resource_type' => $submission->resource_type, 'resource_id' => $submission->resource_id])->lockForUpdate()->first();
        $previous = $state?->current_publication_version_id ? ResourcePublicationVersion::lockForUpdate()->findOrFail($state->current_publication_version_id) : null;
        if ($previous && ($previous->resource_type !== $submission->resource_type || (int) $previous->resource_id !== (int) $submission->resource_id)) {
            throw ValidationException::withMessages(['publication' => ['Current publication pointer is invalid.']]);
        }
        // The publication state row is locked above, which serializes publication
        // numbers for this resource. PostgreSQL does not allow FOR UPDATE on an
        // aggregate query, so compute the max without applying a row lock here.
        $number = (int) ResourcePublicationVersion::where(['resource_type' => $submission->resource_type, 'resource_id' => $submission->resource_id])->max('publication_number') + 1;
        $version = ResourcePublicationVersion::create([
            'resource_type' => $submission->resource_type,
            'resource_id' => $submission->resource_id,
            'publication_number' => $number,
            'resource_revision_id' => $revision->id,
            'review_submission_id' => $submission->id,
            'published_by' => $admin->id,
            'published_at' => now(),
            'previous_publication_version_id' => $previous?->id,
            'metadata' => ['source' => 'approval'],
            'created_at' => now(),
        ]);
        ResourcePublicationState::updateOrCreate(
            ['resource_type' => $submission->resource_type, 'resource_id' => $submission->resource_id],
            ['approved_revision_id' => $revision->id, 'current_publication_version_id' => $version->id],
        );

        return $version;
    }
}
