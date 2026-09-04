<?php

namespace App\Services;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceReviewEvent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class ReviewHistoryService
{
    public const SUBMITTED = 'review.submitted';

    public const CLAIMED = 'review.claimed';

    public const RELEASED = 'review.released';

    public const TAKEN_OVER = 'review.taken_over';

    public const CHANGES_REQUESTED = 'review.changes_requested';

    public const REJECTED = 'review.rejected';

    public const APPROVED = 'review.approved';

    public const ACTIVE_TYPES = [self::SUBMITTED, self::CLAIMED, self::RELEASED, self::TAKEN_OVER, self::CHANGES_REQUESTED, self::REJECTED, self::APPROVED];

    public function __construct(private CollaborationResourceRegistry $registry) {}

    public function append(ResourcePublicationSubmission $submission, string $type, ?User $actor, array $facts = [], ?string $key = null, ?\DateTimeInterface $at = null): ResourceReviewEvent
    {
        if (! in_array($type, self::ACTIVE_TYPES, true)) {
            throw new \InvalidArgumentException('Unknown review event type.');
        }
        $allowed = array_intersect_key($facts, array_flip(['previous_reviewer_id', 'reviewer_id', 'previous_approved_revision_id', 'approved_revision_id', 'metadata']));

        return ResourceReviewEvent::firstOrCreate(
            ['event_key' => $key ?? $this->eventKey($submission, $type)],
            $allowed + [
                'submission_id' => $submission->id,
                'resource_type' => $submission->resource_type,
                'resource_id' => $submission->resource_id,
                'event_type' => $type,
                'actor_id' => $actor?->id,
                'resource_revision_id' => $submission->submitted_revision_id,
                'created_at' => $at ?? now(),
            ],
        );
    }

    public function forResource(User $user, string $type, int|string $id, int $perPage = 20): LengthAwarePaginator
    {
        $resolved = $this->registry->resolve($user, new CollaborationResourceReference($type, $id), 'view');

        return $this->query($type, (int) $resolved->resource->getKey(), $perPage);
    }

    public function forSubmission(User $admin, ResourcePublicationSubmission $submission): array
    {
        abort_unless($admin->isPlatformAdmin() && in_array($submission->resource_type, ['device_template', 'firmware', 'automation', 'report', 'dashboard', 'webhook'], true), 404);
        $this->registry->resolve($admin, new CollaborationResourceReference($submission->resource_type, $submission->resource_id), 'view');

        return $this->cycle($submission->load(['revision', 'submitter:id,name', 'decisionMaker:id,name', 'events' => fn ($query) => $query->with(['actor:id,name,status', 'previousReviewer:id,name,status', 'reviewer:id,name,status', 'previousApprovedRevision:id,revision_number', 'approvedRevision:id,revision_number', 'revision:id,revision_number'])->oldest('created_at')->oldest('id')]));
    }

    private function query(string $type, int $id, int $perPage): LengthAwarePaginator
    {
        return ResourcePublicationSubmission::where(['resource_type' => $type, 'resource_id' => $id])
            ->with(['revision', 'submitter:id,name', 'decisionMaker:id,name', 'events' => fn ($query) => $query->with(['actor:id,name,status', 'previousReviewer:id,name,status', 'reviewer:id,name,status', 'previousApprovedRevision:id,revision_number', 'approvedRevision:id,revision_number', 'revision:id,revision_number'])->oldest('created_at')->oldest('id')])
            ->latest('submitted_at')->paginate(min(max($perPage, 1), 50))->through(fn ($submission) => $this->cycle($submission));
    }

    private function cycle(ResourcePublicationSubmission $submission): array
    {
        return [
            'submissionId' => (string) $submission->id,
            'status' => $submission->status,
            'current' => $submission->status === 'submitted',
            'submittedRevision' => $this->revision($submission->revision),
            'submittedBy' => $this->actor($submission->submitter),
            'submittedAt' => $submission->submitted_at?->toISOString(),
            'events' => $submission->events->map(fn ($event) => $this->event($event, $submission))->values(),
        ];
    }

    private function event(ResourceReviewEvent $event, ResourcePublicationSubmission $submission): array
    {
        $decision = match ($event->event_type) {
            self::APPROVED => 'approved', self::CHANGES_REQUESTED => 'changes_requested', self::REJECTED => 'rejected', default => null
        };

        return [
            'id' => (string) $event->id,
            'eventType' => $event->event_type,
            'actor' => $this->actor($event->actor),
            'timestamp' => $event->created_at?->toISOString(),
            'revision' => $this->revision($event->revision),
            'previousReviewer' => $this->actor($event->previousReviewer),
            'reviewer' => $this->actor($event->reviewer),
            'decision' => $decision,
            'decisionNote' => $decision ? $submission->decision_note : null,
            'previousApprovedRevision' => $this->revision($event->previousApprovedRevision),
            'approvedRevision' => $this->revision($event->approvedRevision),
            'source' => $event->metadata['source'] ?? 'live',
        ];
    }

    private function actor(?User $user): ?array
    {
        return $user ? ['id' => (string) $user->id, 'name' => $user->name, 'inactive' => $user->status !== 'active'] : null;
    }

    private function revision($revision): ?array
    {
        return $revision ? ['id' => (string) $revision->id, 'number' => $revision->revision_number] : null;
    }

    private function eventKey(ResourcePublicationSubmission $submission, string $type): string
    {
        return in_array($type, [self::SUBMITTED, self::CHANGES_REQUESTED, self::REJECTED, self::APPROVED], true)
            ? "submission:{$submission->id}:{$type}"
            : "submission:{$submission->id}:{$type}:".Str::uuid();
    }
}
