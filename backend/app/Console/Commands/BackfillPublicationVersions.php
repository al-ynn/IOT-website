<?php

namespace App\Console\Commands;

use App\Models\ResourcePublicationState;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourcePublicationVersion;
use App\Models\ResourceRevision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class BackfillPublicationVersions extends Command
{
    protected $signature = 'publication-versions:backfill {--chunk=200}';
    protected $description = 'Create deterministic current publication baselines from trusted approved revision pointers';

    public function handle(): int
    {
        $created = 0;
        ResourcePublicationState::whereNotNull('approved_revision_id')->orderBy('id')->chunkById(max(1, (int) $this->option('chunk')), function ($states) use (&$created): void {
            foreach ($states as $state) DB::transaction(function () use ($state, &$created): void {
                $locked = ResourcePublicationState::lockForUpdate()->findOrFail($state->id);
                if ($locked->current_publication_version_id) return;
                $revision = ResourceRevision::whereKey($locked->approved_revision_id)->where(['resource_type' => $locked->resource_type, 'resource_id' => $locked->resource_id])->firstOrFail();
                $submission = ResourcePublicationSubmission::where(['resource_type' => $locked->resource_type, 'resource_id' => $locked->resource_id, 'submitted_revision_id' => $revision->id, 'status' => 'approved'])->oldest('decision_at')->first();
                $version = ResourcePublicationVersion::firstOrCreate(
                    ['resource_type' => $locked->resource_type, 'resource_id' => $locked->resource_id, 'publication_number' => 1],
                    ['resource_revision_id' => $revision->id, 'review_submission_id' => $submission?->id, 'published_by' => $submission?->decision_by, 'published_at' => $submission?->decision_at ?? now(), 'previous_publication_version_id' => null, 'metadata' => ['source' => 'legacy_baseline'], 'created_at' => now()],
                );
                $locked->update(['current_publication_version_id' => $version->id]);
                $created++;
            });
        });
        $this->info("Publication version backfill complete; {$created} baselines created.");
        return self::SUCCESS;
    }
}
