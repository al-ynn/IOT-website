<?php

namespace App\Services;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ResourcePublicationState;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReportPublicationService
{
    public function __construct(private CollaborationResourceRegistry $registry, private ReviewHistoryService $history, private ResourcePublicationVersionService $versions, private ReportPublicationValidator $validator) {}

    public function submit(User $actor, Report $report, ?int $revisionId = null): ResourcePublicationSubmission
    {
        $this->active($report);
        $this->registry->resolve($actor, new CollaborationResourceReference('report', $report->id), 'edit');
        $latest = $this->latest($report);
        if ($revisionId !== null && $revisionId !== (int) $latest->id) {
            throw ValidationException::withMessages(['revision_id' => ['Only the latest shared Report revision may be submitted.']]);
        }$this->validator->validate($report, $latest, $actor);
        $submission = DB::transaction(function () use ($actor, $report, $latest) {
            if (ResourcePublicationSubmission::where(['resource_type' => 'report', 'resource_id' => $report->id])->whereIn('status', ResourcePublicationSubmission::ACTIVE_STATUSES)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['report' => ['This Report already has an active submission.']]);
            }$s = ResourcePublicationSubmission::create(['resource_type' => 'report', 'resource_id' => $report->id, 'submitted_revision_id' => $latest->id, 'submitted_by' => $actor->id, 'status' => 'submitted', 'active_key' => "report:{$report->id}", 'submitted_at' => now()]);
            $this->history->append($s, ReviewHistoryService::SUBMITTED, $actor);

            return $s;
        });
        foreach (User::where('platform_role', 'platform_admin')->where('status', 'active')->get() as $admin) {
            $this->notify($admin, $actor, $submission, 'report.publication.submitted', 'Report submitted for review', true);
        }

        return $this->load($submission);
    }

    public function approve(User $u, ResourcePublicationSubmission $s, bool $confirm = false)
    {
        return $this->decide($u, $s, 'approved', null, $confirm);
    }

    public function requestChanges(User $u, ResourcePublicationSubmission $s, string $note)
    {
        return $this->decide($u, $s, 'changes_requested', $this->note($note));
    }

    public function reject(User $u, ResourcePublicationSubmission $s, string $note)
    {
        return $this->decide($u, $s, 'rejected', $this->note($note));
    }

    private function decide(User $admin, ResourcePublicationSubmission $submission, string $decision, ?string $note, bool $confirm = false): ResourcePublicationSubmission
    {
        abort_unless($admin->isPlatformAdmin() && $admin->status === 'active'
            && $submission->resource_type === 'report'
            && Report::whereKey($submission->resource_id)->where('organization_id', $admin->organization_id)->exists(), 404);
        $report = Report::findOrFail($submission->resource_id);
        $this->active($report);
        $s = DB::transaction(function () use ($admin, $submission, $decision, $note, $confirm, $report) {
            $s = ResourcePublicationSubmission::lockForUpdate()->findOrFail($submission->id);
            if ($s->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => ['This submission already has a final decision.']]);
            }if ((int) $s->reviewer_id !== (int) $admin->id) {
                throw ValidationException::withMessages(['reviewer' => ['Start or take over this review before deciding.']]);
            }$revision = ResourceRevision::whereKey($s->submitted_revision_id)->where(['resource_type' => 'report', 'resource_id' => $report->id])->firstOrFail();
            $latest = $this->latest($report);
            if ($decision === 'approved') {
                $submitter = User::findOrFail($s->submitted_by);
                $this->registry->resolve($submitter, new CollaborationResourceReference('report', $report->id), 'edit');
                $this->validator->validate($report, $revision, $submitter);
                if ($latest->id !== $revision->id && ! $confirm) {
                    throw ValidationException::withMessages(['confirm_older_revision' => ['Confirm approval while newer private changes remain unpublished.']]);
                }
            }$previous = ResourcePublicationState::where(['resource_type' => 'report', 'resource_id' => $report->id])->value('approved_revision_id');
            $version = $decision === 'approved' ? $this->versions->publish($s, $admin) : null;
            $s->update(['status' => $decision, 'active_key' => null, 'reviewer_id' => null, 'review_claimed_at' => null, 'decision_by' => $admin->id, 'decision_at' => now(), 'decision_note' => $note]);
            $event = match ($decision) {
                'approved' => ReviewHistoryService::APPROVED,'changes_requested' => ReviewHistoryService::CHANGES_REQUESTED,'rejected' => ReviewHistoryService::REJECTED
            };
            $facts = $version ? ['previous_approved_revision_id' => $previous, 'approved_revision_id' => $revision->id, 'metadata' => ['publication_version_id' => $version->id]] : [];
            $this->history->append($s, $event, $admin, $facts, at: $s->decision_at);

            return $s->refresh();
        });
        Notification::where('deduplication_key', 'like', "report.publication.submitted:report-submission:{$s->id}:%")->update(['requires_action' => false, 'action_state' => $decision]);
        $this->notify($s->submitter, $admin, $s, "report.publication.{$decision}", 'Report publication '.str_replace('_', ' ', $decision), false);

        return $this->load($s);
    }

    public function state(Report $report): array
    {
        $latest = $this->latest($report);
        $state = ResourcePublicationState::where(['resource_type' => 'report', 'resource_id' => $report->id])->with('currentVersion.revision')->first();
        $active = ResourcePublicationSubmission::where(['resource_type' => 'report', 'resource_id' => $report->id, 'status' => 'submitted'])->with(['revision', 'reviewer:id,name'])->first();

        return ['latestRevision' => $this->revision($latest), 'currentPublicationVersion' => $state?->currentVersion ? ['id' => (string) $state->currentVersion->id, 'number' => $state->currentVersion->publication_number, 'revision' => $this->revision($state->currentVersion->revision), 'publishedAt' => $state->currentVersion->published_at?->toISOString()] : null, 'activeSubmission' => $active ? $this->submissionData($active, $latest) : null, 'hasDraftChanges' => (bool) ($state?->approved_revision_id && $latest->id !== $state->approved_revision_id)];
    }

    public function submissionData(ResourcePublicationSubmission $s, ?ResourceRevision $latest = null): array
    {
        return ['id' => (string) $s->id, 'status' => $s->status, 'reviewState' => $s->status === 'submitted' ? ($s->reviewer_id ? 'in_review' : 'available') : 'terminal', 'reviewer' => $s->reviewer ? ['id' => (string) $s->reviewer->id, 'name' => $s->reviewer->name] : null, 'submittedRevision' => $this->revision($s->revision), 'submittedBy' => $s->submitter ? ['id' => (string) $s->submitter->id, 'name' => $s->submitter->name] : null, 'submittedAt' => $s->submitted_at?->toISOString(), 'decisionNote' => $s->decision_note, 'newerDraftExists' => (bool) ($latest && $latest->id !== $s->submitted_revision_id)];
    }

    private function latest(Report $r): ResourceRevision
    {
        return ResourceRevision::where(['resource_type' => 'report', 'resource_id' => $r->id])->latest('revision_number')->firstOrFail();
    }

    private function active(Report $r): void
    {
        app(ResourceLifecycleService::class)->assertActive('report', $r->id, 'Disabled or Archived Reports cannot enter publication review.');
    }

    private function note(string $v): string
    {
        $v = trim($v);
        if ($v === '' || mb_strlen($v) > 1000) {
            throw ValidationException::withMessages(['note' => ['A note between 1 and 1000 characters is required.']]);
        }

        return $v;
    }

    private function revision($r): ?array
    {
        return $r ? ['id' => (string) $r->id, 'number' => $r->revision_number] : null;
    }

    private function load($s)
    {
        return $s->load(['revision', 'submitter:id,name', 'decisionMaker:id,name', 'reviewer:id,name', 'publicationVersion']);
    }

    private function notify(User $to, User $actor, $s, string $type, string $title, bool $action): void
    {
        $organizationId = Report::whereKey($s->resource_id)->value('organization_id');
        app(NotificationOutboxService::class)->recordNotification($to, $type, "report-submission:{$s->id}", ['organization_id' => $organizationId, 'actor_id' => $actor->id, 'resource_type' => 'report', 'resource_id' => $s->resource_id, 'action_url' => $to->isPlatformAdmin() ? "/admin/report-publication-submissions/{$s->id}" : "/app/reports/{$s->resource_id}", 'data' => ['submission_id' => $s->id, 'revision_id' => $s->submitted_revision_id], 'requires_action' => $action, 'action_state' => $s->status, 'title' => $title, 'message' => $title.'.', 'severity' => 'info']);
    }
}
