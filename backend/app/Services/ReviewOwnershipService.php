<?php

namespace App\Services;

use App\Models\ResourcePublicationSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ReviewOwnershipService
{
    public const AVAILABLE = 'available';
    public const MINE = 'mine';
    public const IN_REVIEW = 'in_review';
    public const REVIEWER_UNAVAILABLE = 'reviewer_unavailable';
    public const NOT_APPLICABLE = 'not_applicable';

    public function applyFilter(Builder $query, string $state, User $admin): void
    {
        match ($state) {
            self::AVAILABLE => $query->whereNull('reviewer_id'),
            self::MINE => $query->where('reviewer_id', $admin->id),
            self::IN_REVIEW => $query->whereNotNull('reviewer_id')->where('reviewer_id', '!=', $admin->id)->whereHas('reviewer', $this->eligibleReviewer(...)),
            self::REVIEWER_UNAVAILABLE => $query->whereNotNull('reviewer_id')->whereDoesntHave('reviewer', $this->eligibleReviewer(...)),
            default => null,
        };
    }

    public function counts(Builder $base, User $admin): array
    {
        $queries = ['available' => clone $base, 'mine' => clone $base, 'inReview' => clone $base, 'reviewerUnavailable' => clone $base];
        $this->applyFilter($queries['available'], self::AVAILABLE, $admin);
        $this->applyFilter($queries['mine'], self::MINE, $admin);
        $this->applyFilter($queries['inReview'], self::IN_REVIEW, $admin);
        $this->applyFilter($queries['reviewerUnavailable'], self::REVIEWER_UNAVAILABLE, $admin);
        return array_map(fn (Builder $query) => $query->count(), $queries);
    }

    public function present(ResourcePublicationSubmission $submission, User $admin): array
    {
        if (! in_array($submission->status, ResourcePublicationSubmission::ACTIVE_STATUSES, true)) return $this->result(self::NOT_APPLICABLE, null, false, false, false);
        if (! $submission->reviewer_id) return $this->result(self::AVAILABLE, null, true, false, false);
        $reviewer = $submission->reviewer;
        if (! $reviewer || $reviewer->status !== 'active' || ! $reviewer->isPlatformAdmin()) return $this->result(self::REVIEWER_UNAVAILABLE, null, false, false, true);
        $safe = ['id' => (string) $reviewer->id, 'name' => $reviewer->name];
        if ((int) $reviewer->id === (int) $admin->id) return $this->result(self::MINE, $safe, false, true, false);
        return $this->result(self::IN_REVIEW, $safe, false, false, true);
    }

    private function eligibleReviewer(Builder $query): Builder { return $query->where('status', 'active')->where('platform_role', 'platform_admin'); }
    private function result(string $state, ?array $reviewer, bool $canClaim, bool $canRelease, bool $canTakeOver): array { $canDecide=$state===self::MINE;return ['state' => $state, 'reviewer' => $reviewer, 'capabilities' => compact('canClaim', 'canRelease', 'canTakeOver')+['canApprove'=>$canDecide,'canRequestChanges'=>$canDecide,'canReject'=>$canDecide]]; }
}
