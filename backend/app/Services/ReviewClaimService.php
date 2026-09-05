<?php

namespace App\Services;

use App\Models\ResourcePublicationSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ReviewClaimService
{
    public function __construct(
        private ReviewHistoryService $history,
        private ReviewDomainRegistry $domains,
    ) {}

    public function claim(User $admin, ResourcePublicationSubmission $submission): ResourcePublicationSubmission
    {
        $this->assertEligibleAdmin($admin);
        return DB::transaction(function () use ($admin, $submission) {
            $locked = ResourcePublicationSubmission::lockForUpdate()->findOrFail($submission->id);
            $this->assertActive($admin, $locked);
            if ($locked->reviewer_id) throw ValidationException::withMessages(['reviewer' => ['This submission is already in review.']]);
            $locked->update(['reviewer_id' => $admin->id, 'review_claimed_at' => now()]);
            $this->history->append($locked, ReviewHistoryService::CLAIMED, $admin, ['reviewer_id' => $admin->id], at: $locked->review_claimed_at);
            return $this->load($locked->refresh());
        });
    }

    public function release(User $admin, ResourcePublicationSubmission $submission): ResourcePublicationSubmission
    {
        $this->assertEligibleAdmin($admin);
        $expectedReviewerId = $submission->reviewer_id;
        return DB::transaction(function () use ($admin, $submission, $expectedReviewerId) {
            $locked = ResourcePublicationSubmission::lockForUpdate()->findOrFail($submission->id);
            $this->assertActive($admin, $locked);
            $this->assertExpectedReviewer($locked, $expectedReviewerId);
            if ((int) $locked->reviewer_id !== (int) $admin->id) throw ValidationException::withMessages(['reviewer' => ['Only the current reviewer may release this review.']]);
            $previousReviewerId = $locked->reviewer_id;
            $locked->update(['reviewer_id' => null, 'review_claimed_at' => null]);
            $this->history->append($locked, ReviewHistoryService::RELEASED, $admin, ['previous_reviewer_id' => $previousReviewerId]);
            return $this->load($locked->refresh());
        });
    }

    public function takeover(User $admin, ResourcePublicationSubmission $submission, bool $confirmed): ResourcePublicationSubmission
    {
        $this->assertEligibleAdmin($admin);
        if (! $confirmed) throw ValidationException::withMessages(['confirm' => ['Explicit takeover confirmation is required.']]);
        $expectedReviewerId = $submission->reviewer_id;
        return DB::transaction(function () use ($admin, $submission, $expectedReviewerId) {
            $locked = ResourcePublicationSubmission::lockForUpdate()->findOrFail($submission->id);
            $this->assertActive($admin, $locked);
            $this->assertExpectedReviewer($locked, $expectedReviewerId);
            if (! $locked->reviewer_id) throw ValidationException::withMessages(['reviewer' => ['Review is currently available; use Start Review.']]);
            if ((int) $locked->reviewer_id === (int) $admin->id) throw ValidationException::withMessages(['reviewer' => ['You already own this review.']]);
            $previousReviewerId = $locked->reviewer_id;
            $locked->update(['reviewer_id' => $admin->id, 'review_claimed_at' => now()]);
            $this->history->append($locked, ReviewHistoryService::TAKEN_OVER, $admin, ['previous_reviewer_id' => $previousReviewerId, 'reviewer_id' => $admin->id], at: $locked->review_claimed_at);
            return $this->load($locked->refresh());
        });
    }

    private function assertActive(User $admin, ResourcePublicationSubmission $submission): void
    {
        if ($submission->status !== 'submitted') throw ValidationException::withMessages(['status' => ['Only actionable submitted reviews may be claimed.']]);
        $definition = $this->domains->definition($submission->resource_type);
        abort_unless($definition['model']::query()
            ->whereKey($submission->resource_id)
            ->where('organization_id', $admin->organization_id)
            ->exists(), 404);
        app(ResourceLifecycleService::class)->assertActive($submission->resource_type, $submission->resource_id, 'Restore the resource before changing review ownership.');
        $revisionMatches = \App\Models\ResourceRevision::whereKey($submission->submitted_revision_id)->where(['resource_type' => $submission->resource_type, 'resource_id' => $submission->resource_id])->exists();
        if (! $revisionMatches) throw new ConflictHttpException('The submitted revision no longer matches this review resource.');
    }

    private function assertEligibleAdmin(User $admin): void
    {
        abort_unless($admin->isPlatformAdmin() && $admin->status === 'active', 403);
    }

    private function assertExpectedReviewer(ResourcePublicationSubmission $submission, mixed $expectedReviewerId): void
    {
        if ((string) ($submission->reviewer_id ?? '') !== (string) ($expectedReviewerId ?? '')) {
            throw new ConflictHttpException('Review ownership changed. Refresh and try again.');
        }
    }

    private function load(ResourcePublicationSubmission $submission): ResourcePublicationSubmission
    {
        return $submission->load(['reviewer:id,name,status,platform_role', 'revision', 'submitter:id,name', 'decisionMaker:id,name']);
    }
}
