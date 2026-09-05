<?php

namespace App\Services;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Models\Automation;
use App\Models\Notification;
use App\Models\ResourcePublicationState;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AutomationPublicationService
{
    public function __construct(private CollaborationResourceRegistry $registry, private ReviewHistoryService $history, private ResourcePublicationVersionService $versions, private AutomationPublicationValidator $validator) {}

    public function submit(User $actor, Automation $a, ?int $revisionId = null): ResourcePublicationSubmission
    {
        $this->active($a);
        $this->registry->resolve($actor, new CollaborationResourceReference('automation', $a->id), 'edit');
        $latest = $this->latest($a);
        if ($revisionId !== null && $revisionId !== (int) $latest->id) {
            throw ValidationException::withMessages(['revision_id' => ['Only the latest shared Automation revision may be submitted.']]);
        }$this->validator->validate($a, $latest, $actor);
        $s = DB::transaction(function () use ($actor, $a, $latest) {
            if (ResourcePublicationSubmission::where(['resource_type' => 'automation', 'resource_id' => $a->id])->whereIn('status', ResourcePublicationSubmission::ACTIVE_STATUSES)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['automation' => ['This Automation already has an active submission.']]);
            }$s = ResourcePublicationSubmission::create(['resource_type' => 'automation', 'resource_id' => $a->id, 'submitted_revision_id' => $latest->id, 'submitted_by' => $actor->id, 'status' => 'submitted', 'active_key' => "automation:{$a->id}", 'submitted_at' => now()]);
            $this->history->append($s, ReviewHistoryService::SUBMITTED, $actor);

            return $s;
        });
        foreach (User::where('platform_role', 'platform_admin')->where('status', 'active')->get() as $admin) {
            $this->notify($admin, $actor, $s, 'automation.publication.submitted', 'Automation submitted for review', true);
        }

        return $this->load($s);
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
            && $submission->resource_type === 'automation'
            && Automation::whereKey($submission->resource_id)->where('organization_id', $admin->organization_id)->exists(), 404);
        $a = Automation::findOrFail($submission->resource_id);
        $this->active($a);
        $s = DB::transaction(function () use ($admin, $submission, $decision, $note, $confirm, $a) {
            $s = ResourcePublicationSubmission::lockForUpdate()->findOrFail($submission->id);
            if ($s->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => ['This submission already has a final decision.']]);
            }if ((int) $s->reviewer_id !== (int) $admin->id) {
                throw ValidationException::withMessages(['reviewer' => ['Start or take over this review before deciding.']]);
            }$revision = ResourceRevision::whereKey($s->submitted_revision_id)->where(['resource_type' => 'automation', 'resource_id' => $a->id])->firstOrFail();
            $latest = $this->latest($a);
            if ($decision === 'approved') {
                $submitter = User::findOrFail($s->submitted_by);
                $this->registry->resolve($submitter, new CollaborationResourceReference('automation', $a->id), 'edit');
                $this->validator->validate($a, $revision, $submitter);
                if ($latest->id !== $revision->id && ! $confirm) {
                    throw ValidationException::withMessages(['confirm_older_revision' => ['Confirm approval while newer private changes remain unpublished.']]);
                }
            }$previous = ResourcePublicationState::where(['resource_type' => 'automation', 'resource_id' => $a->id])->value('approved_revision_id');
            $version = $decision === 'approved' ? $this->versions->publish($s, $admin) : null;
            $s->update(['status' => $decision, 'active_key' => null, 'reviewer_id' => null, 'review_claimed_at' => null, 'decision_by' => $admin->id, 'decision_at' => now(), 'decision_note' => $note]);
            $event = match ($decision) {
                'approved' => ReviewHistoryService::APPROVED,'changes_requested' => ReviewHistoryService::CHANGES_REQUESTED,'rejected' => ReviewHistoryService::REJECTED
            };
            $facts = $version ? ['previous_approved_revision_id' => $previous, 'approved_revision_id' => $revision->id, 'metadata' => ['publication_version_id' => $version->id]] : [];
            $this->history->append($s, $event, $admin, $facts, at: $s->decision_at);

            return $s->refresh();
        });
        Notification::where('deduplication_key', 'like', "automation.publication.submitted:automation-submission:{$s->id}:%")->update(['requires_action' => false, 'action_state' => $decision]);
        $this->notify($s->submitter, $admin, $s, "automation.publication.{$decision}", 'Automation publication '.str_replace('_', ' ', $decision), false);

        return $this->load($s);
    }

    public function state(Automation $a): array
    {
        $latest = $this->latest($a);
        $state = ResourcePublicationState::where(['resource_type' => 'automation', 'resource_id' => $a->id])->with('currentVersion.revision')->first();
        $active = ResourcePublicationSubmission::where(['resource_type' => 'automation', 'resource_id' => $a->id, 'status' => 'submitted'])->with(['revision', 'reviewer:id,name'])->first();

        return ['latestRevision' => $this->revision($latest), 'currentPublicationVersion' => $state?->currentVersion ? ['id' => (string) $state->currentVersion->id, 'number' => $state->currentVersion->publication_number, 'revision' => $this->revision($state->currentVersion->revision), 'publishedAt' => $state->currentVersion->published_at?->toISOString()] : null, 'activeSubmission' => $active ? $this->submissionData($active, $latest) : null, 'hasDraftChanges' => (bool) ($state?->approved_revision_id && $latest->id !== $state->approved_revision_id)];
    }

    public function submissionData(ResourcePublicationSubmission $s, ?ResourceRevision $latest = null): array
    {
        return ['id' => (string) $s->id, 'status' => $s->status, 'reviewState' => $s->status === 'submitted' ? ($s->reviewer_id ? 'in_review' : 'available') : 'terminal', 'reviewer' => $s->reviewer ? ['id' => (string) $s->reviewer->id, 'name' => $s->reviewer->name] : null, 'submittedRevision' => $this->revision($s->revision), 'submittedBy' => $s->submitter ? ['id' => (string) $s->submitter->id, 'name' => $s->submitter->name] : null, 'submittedAt' => $s->submitted_at?->toISOString(), 'decisionNote' => $s->decision_note, 'newerDraftExists' => (bool) ($latest && $latest->id !== $s->submitted_revision_id)];
    }

    private function latest(Automation $a): ResourceRevision
    {
        return ResourceRevision::where(['resource_type' => 'automation', 'resource_id' => $a->id])->latest('revision_number')->firstOrFail();
    }

    private function active(Automation $a): void
    {
        app(ResourceLifecycleService::class)->assertActive('automation', $a->id, 'Disabled or Archived Automations cannot enter publication review.');
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
        $organizationId = Automation::whereKey($s->resource_id)->value('organization_id');
        app(NotificationOutboxService::class)->recordNotification($to, $type, "automation-submission:{$s->id}", ['organization_id' => $organizationId, 'actor_id' => $actor->id, 'resource_type' => 'automation', 'resource_id' => $s->resource_id, 'action_url' => $to->isPlatformAdmin() ? "/admin/automation-publication-submissions/{$s->id}" : "/app/automations/{$s->resource_id}", 'data' => ['submission_id' => $s->id, 'revision_id' => $s->submitted_revision_id], 'requires_action' => $action, 'action_state' => $s->status, 'title' => $title, 'message' => $title.'.', 'severity' => 'info']);
    }
}
