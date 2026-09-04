<?php

namespace App\Console\Commands;

use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceReviewEvent;
use App\Services\ReviewHistoryService;
use Illuminate\Console\Command;

final class BackfillReviewHistory extends Command
{
    protected $signature = 'review-history:backfill {--chunk=200}';
    protected $description = 'Backfill only review facts explicitly preserved by legacy submission rows';

    public function handle(ReviewHistoryService $history): int
    {
        $count = 0;
        ResourcePublicationSubmission::with(['submitter', 'decisionMaker'])->orderBy('id')->chunkById(max(1, (int) $this->option('chunk')), function ($submissions) use ($history, &$count): void {
            foreach ($submissions as $submission) {
                if (! ResourceReviewEvent::where(['submission_id' => $submission->id, 'event_type' => ReviewHistoryService::SUBMITTED])->exists()) {
                    $history->append($submission, ReviewHistoryService::SUBMITTED, $submission->submitter, ['metadata' => ['source' => 'legacy_backfill']], "legacy:submission:{$submission->id}:submitted", $submission->submitted_at);
                    $count++;
                }
                $type = match ($submission->status) {
                    'approved' => ReviewHistoryService::APPROVED,
                    'changes_requested' => ReviewHistoryService::CHANGES_REQUESTED,
                    'rejected' => ReviewHistoryService::REJECTED,
                    default => null,
                };
                if ($type && $submission->decision_at && ! ResourceReviewEvent::where(['submission_id' => $submission->id, 'event_type' => $type])->exists()) {
                    $facts = ['metadata' => ['source' => 'legacy_backfill']];
                    if ($type === ReviewHistoryService::APPROVED) $facts['approved_revision_id'] = $submission->submitted_revision_id;
                    $history->append($submission, $type, $submission->decisionMaker, $facts, "legacy:submission:{$submission->id}:{$submission->status}", $submission->decision_at);
                    $count++;
                }
            }
        });
        $this->info("Review history backfill complete; {$count} facts inspected.");
        return self::SUCCESS;
    }
}
