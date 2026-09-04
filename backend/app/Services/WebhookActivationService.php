<?php

namespace App\Services;

use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceRevision;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WebhookActivationService
{
    public function __construct(private WebhookUrlGuard $guard, private ReviewHistoryService $history) {}

    public function submit(User $actor, Webhook $webhook, int $revisionId): ResourcePublicationSubmission
    {
        app(ResourceLifecycleService::class)->assertActive('webhook', $webhook->id, 'Restore the Webhook before submitting activation.');
        $revision = ResourceRevision::whereKey($revisionId)->where(['resource_type' => 'webhook', 'resource_id' => $webhook->id])->firstOrFail();
        $this->validateSnapshot($revision);

        return DB::transaction(function () use ($actor, $webhook, $revision) {
            $key = "webhook:{$webhook->id}";
            if (ResourcePublicationSubmission::where('active_key', $key)->exists()) {
                throw ValidationException::withMessages(['submission' => ['An activation review is already active.']]);
            }$s = ResourcePublicationSubmission::create(['resource_type' => 'webhook', 'resource_id' => $webhook->id, 'submitted_revision_id' => $revision->id, 'submitted_by' => $actor->id, 'status' => 'submitted', 'active_key' => $key, 'submitted_at' => now()]);
            $this->history->append($s, ReviewHistoryService::SUBMITTED, $actor, ['revision_id' => $revision->id], at: $s->submitted_at);

            return $s->load('revision');
        });
    }

    public function approve(User $admin, ResourcePublicationSubmission $submission, bool $confirmActive): ResourcePublicationSubmission
    {
        abort_unless($admin->isPlatformAdmin() && $admin->status === 'active', 403);

        return DB::transaction(function () use ($admin, $submission, $confirmActive) {
            $s = ResourcePublicationSubmission::lockForUpdate()->findOrFail($submission->id);
            $this->assertReview($admin, $s);
            $w = Webhook::lockForUpdate()->findOrFail($s->resource_id);
            if ($w->enabled && ! $confirmActive) {
                throw ValidationException::withMessages(['confirm_active_change' => ['Explicit confirmation is required to change an active Webhook runtime definition.']]);
            }$this->validateSnapshot($s->revision);
            $s->update(['status' => 'approved', 'active_key' => null, 'reviewer_id' => null, 'review_claimed_at' => null, 'decision_by' => $admin->id, 'decision_at' => now()]);
            $w->update(['approved_revision_id' => $s->submitted_revision_id, 'approved_submission_id' => $s->id]);
            $this->history->append($s, ReviewHistoryService::APPROVED, $admin, ['revision_id' => $s->submitted_revision_id], at: $s->decision_at);

            return $s->refresh();
        });
    }

    public function decide(User $admin, ResourcePublicationSubmission $submission, string $status, string $note): ResourcePublicationSubmission
    {
        abort_unless($admin->isPlatformAdmin() && $admin->status === 'active', 403);

        return DB::transaction(function () use ($admin, $submission, $status, $note) {
            $s = ResourcePublicationSubmission::lockForUpdate()->findOrFail($submission->id);
            $this->assertReview($admin, $s);
            $s->update(['status' => $status, 'active_key' => null, 'reviewer_id' => null, 'review_claimed_at' => null, 'decision_by' => $admin->id, 'decision_at' => now(), 'decision_note' => $note]);
            $this->history->append($s, $status === 'changes_requested' ? ReviewHistoryService::CHANGES_REQUESTED : ReviewHistoryService::REJECTED, $admin, ['note' => $note], at: $s->decision_at);

            return $s->refresh();
        });
    }

    private function assertReview(User $admin, ResourcePublicationSubmission $s): void
    {
        abort_unless($s->resource_type === 'webhook', 404);
        abort_unless(Webhook::whereKey($s->resource_id)->where('organization_id', $admin->organization_id)->exists(), 404);
        app(ResourceLifecycleService::class)->assertActive('webhook', $s->resource_id, 'Restore the Webhook before making an activation decision.');
        if ($s->status !== 'submitted' || (int) $s->reviewer_id !== (int) $admin->id) {
            throw ValidationException::withMessages(['reviewer' => ['Claim this active review before deciding it.']]);
        }
    }

    private function validateSnapshot(ResourceRevision $r): void
    {
        $c = $r->snapshot['configuration'] ?? [];
        $this->guard->assertSafe((string) ($c['url'] ?? ''));
        foreach (($c['event_types'] ?? []) as $type) {
            if (! in_array($type, Webhook::EVENT_TYPES, true)) {
                throw ValidationException::withMessages(['event_types' => ['The submitted revision contains an unsupported event type.']]);
            }
        }
    }
}
