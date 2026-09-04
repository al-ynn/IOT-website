<?php

namespace App\Services\Dashboard;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Models\Dashboard;
use App\Models\Notification;
use App\Models\ResourcePublicationState;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceRevision;
use App\Models\User;
use App\Services\NotificationOutboxService;
use App\Services\ResourceLifecycleService;
use App\Services\ResourcePublicationVersionService;
use App\Services\ReviewHistoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DashboardPublicationService
{
    public function __construct(private CollaborationResourceRegistry $registry, private DashboardPublicationValidator $validator, private ResourcePublicationVersionService $versions, private ReviewHistoryService $history) {}

    public function submit(User $actor, Dashboard $dashboard, int $revisionId): ResourcePublicationSubmission
    {
        $this->active($dashboard);
        $this->registry->resolve($actor, new CollaborationResourceReference('dashboard', $dashboard->id), 'edit');
        $revision = ResourceRevision::whereKey($revisionId)->where(['resource_type' => 'dashboard', 'resource_id' => $dashboard->id])->firstOrFail();
        $this->validator->validate($dashboard, $revision, $actor);
        $submission = DB::transaction(function () use ($actor, $dashboard, $revision) {
            if (ResourcePublicationSubmission::where(['resource_type' => 'dashboard', 'resource_id' => $dashboard->id])->whereIn('status', ResourcePublicationSubmission::ACTIVE_STATUSES)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['dashboard' => ['This Dashboard already has an active publication submission.']]);
            }$s = ResourcePublicationSubmission::create(['resource_type' => 'dashboard', 'resource_id' => $dashboard->id, 'submitted_revision_id' => $revision->id, 'submitted_by' => $actor->id, 'status' => 'submitted', 'active_key' => "dashboard:{$dashboard->id}", 'submitted_at' => now()]);
            $this->history->append($s, ReviewHistoryService::SUBMITTED, $actor);
            if ($actor->isAdmin()) {
                $previous = ResourcePublicationState::where(['resource_type' => 'dashboard', 'resource_id' => $dashboard->id])->value('approved_revision_id');
                $version = $this->versions->publish($s, $actor);
                $s->update(['status' => 'approved', 'active_key' => null, 'decision_by' => $actor->id, 'decision_at' => now(), 'decision_note' => 'Published directly by an Organization Admin.']);
                $this->history->append($s, ReviewHistoryService::APPROVED, $actor, ['previous_approved_revision_id' => $previous, 'approved_revision_id' => $revision->id, 'metadata' => ['publication_version_id' => $version->id]], at: $s->decision_at);
            }

            return $s;
        });
        foreach (User::where('platform_role', 'platform_admin')->where('organization_id', $actor->organization_id)->where('status', 'active')->whereKeyNot($actor->id)->get() as $admin) {
            $this->notify($admin, $actor, $submission, $actor->isAdmin() ? 'dashboard.publication.published' : 'dashboard.publication.submitted', $actor->isAdmin() ? 'Dashboard published' : 'Dashboard submitted for review', ! $actor->isAdmin(), $actor->isAdmin() ? "{$actor->name} published {$dashboard->name}." : "{$actor->name} submitted {$dashboard->name} for review.");
        }

        return $this->load($submission);
    }

    public function approve(User $admin, ResourcePublicationSubmission $s, bool $confirm = false)
    {
        return $this->decide($admin, $s, 'approved', null, $confirm);
    }

    public function requestChanges(User $admin, ResourcePublicationSubmission $s, string $note)
    {
        return $this->decide($admin, $s, 'changes_requested', $this->note($note));
    }

    public function reject(User $admin, ResourcePublicationSubmission $s, string $note)
    {
        return $this->decide($admin, $s, 'rejected', $this->note($note));
    }

    private function decide(User $admin, ResourcePublicationSubmission $submission, string $decision, ?string $note, bool $confirm = false): ResourcePublicationSubmission
    {
        abort_unless($admin->isPlatformAdmin() && $admin->status === 'active'
            && $submission->resource_type === 'dashboard'
            && Dashboard::whereKey($submission->resource_id)->where('organization_id', $admin->organization_id)->exists(), 404);
        $dashboard = Dashboard::findOrFail($submission->resource_id);
        $this->active($dashboard);
        $result = DB::transaction(function () use ($admin, $submission, $decision, $note, $confirm, $dashboard) {
            $s = ResourcePublicationSubmission::lockForUpdate()->findOrFail($submission->id);
            if ($s->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => ['This submission already has a final decision.']]);
            }if ((int) $s->reviewer_id !== (int) $admin->id) {
                throw ValidationException::withMessages(['reviewer' => ['Start or take over this review before deciding.']]);
            }$revision = ResourceRevision::whereKey($s->submitted_revision_id)->where(['resource_type' => 'dashboard', 'resource_id' => $dashboard->id])->firstOrFail();
            $latest = ResourceRevision::where(['resource_type' => 'dashboard', 'resource_id' => $dashboard->id])->latest('revision_number')->firstOrFail();
            if ($decision === 'approved') {
                $submitter = User::findOrFail($s->submitted_by);
                $this->registry->resolve($submitter, new CollaborationResourceReference('dashboard', $dashboard->id), 'edit');
                $this->validator->validate($dashboard, $revision, $submitter);
                if ($latest->id !== $revision->id && ! $confirm) {
                    throw ValidationException::withMessages(['confirm_older_revision' => ['Confirm approval of the pinned revision while newer private changes remain unpublished.']]);
                }
            }$previous = ResourcePublicationState::where(['resource_type' => 'dashboard', 'resource_id' => $dashboard->id])->value('approved_revision_id');
            $version = $decision === 'approved' ? $this->versions->publish($s, $admin) : null;
            $s->update(['status' => $decision, 'active_key' => null, 'reviewer_id' => null, 'review_claimed_at' => null, 'decision_by' => $admin->id, 'decision_at' => now(), 'decision_note' => $note]);
            $event = match ($decision) {
                'approved' => ReviewHistoryService::APPROVED,'changes_requested' => ReviewHistoryService::CHANGES_REQUESTED,'rejected' => ReviewHistoryService::REJECTED
            };
            $facts = $version ? ['previous_approved_revision_id' => $previous, 'approved_revision_id' => $revision->id, 'metadata' => ['publication_version_id' => $version->id]] : [];
            $this->history->append($s, $event, $admin, $facts, at: $s->decision_at);

            return $s->refresh();
        });
        Notification::where('deduplication_key', 'like', "dashboard.publication.submitted:dashboard-submission:{$result->id}:%")->update(['requires_action' => false, 'action_state' => $decision]);
        $this->notify($result->submitter, $admin, $result, "dashboard.publication.{$decision}", 'Dashboard publication '.str_replace('_', ' ', $decision), false);

        return $this->load($result);
    }

    public function state(Dashboard $d): array
    {
        $latest = ResourceRevision::where(['resource_type' => 'dashboard', 'resource_id' => $d->id])->latest('revision_number')->first();
        $state = ResourcePublicationState::where(['resource_type' => 'dashboard', 'resource_id' => $d->id])->with('currentVersion.revision')->first();
        $active = ResourcePublicationSubmission::where(['resource_type' => 'dashboard', 'resource_id' => $d->id, 'status' => 'submitted'])->with(['revision', 'reviewer:id,name'])->first();

        return ['latestRevision' => $this->revision($latest), 'currentPublicationVersion' => $state?->currentVersion ? ['id' => (string) $state->currentVersion->id, 'number' => $state->currentVersion->publication_number, 'revision' => $this->revision($state->currentVersion->revision), 'publishedAt' => $state->currentVersion->published_at?->toISOString()] : null, 'activeSubmission' => $active ? $this->submissionData($active, $latest) : null, 'hasPrivateChanges' => (bool) ($state?->approved_revision_id && $latest && $latest->id !== $state->approved_revision_id)];
    }

    public function submissionData(ResourcePublicationSubmission $s, ?ResourceRevision $latest = null): array
    {
        return ['id' => (string) $s->id, 'status' => $s->status, 'reviewState' => $s->status === 'submitted' ? ($s->reviewer_id ? 'in_review' : 'available') : 'terminal', 'reviewer' => $s->reviewer ? ['id' => (string) $s->reviewer->id, 'name' => $s->reviewer->name] : null, 'submittedRevision' => $this->revision($s->revision), 'submittedBy' => $s->submitter ? ['id' => (string) $s->submitter->id, 'name' => $s->submitter->name] : null, 'submittedAt' => $s->submitted_at?->toISOString(), 'decisionNote' => $s->decision_note, 'newerPrivateRevisionExists' => (bool) ($latest && $latest->id !== $s->submitted_revision_id), 'publicationVersion' => $s->publicationVersion ? ['id' => (string) $s->publicationVersion->id, 'number' => $s->publicationVersion->publication_number] : null];
    }

    private function active(Dashboard $d): void
    {
        if ($d->scope_type !== 'personal') {
            throw ValidationException::withMessages(['dashboard' => ['Only personal Dashboards may be published.']]);
        }app(ResourceLifecycleService::class)->assertActive('dashboard', $d->id, 'This Dashboard is not active.');
    }

    private function note(string $v): string
    {
        $v = trim($v);
        if ($v === '' || mb_strlen($v) > 1000) {
            throw ValidationException::withMessages(['note' => ['A note between 1 and 1000 characters is required.']]);
        }if (preg_match('/(?:authorization\s*:|bearer\s+[a-z0-9._-]+|private key)/i', $v)) {
            throw ValidationException::withMessages(['note' => ['Decision notes must not contain secrets.']]);
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

    private function notify(User $to, User $actor, ResourcePublicationSubmission $s, string $type, string $title, bool $action, ?string $message = null): void
    {
        app(NotificationOutboxService::class)->recordNotification($to, $type, "dashboard-submission:{$s->id}", ['organization_id' => $to->organization_id, 'actor_id' => $actor->id, 'resource_type' => 'dashboard', 'resource_id' => $s->resource_id, 'action_url' => $action ? "/admin/dashboard-submissions/{$s->id}" : "/app/dashboard/{$s->resource_id}", 'data' => ['submission_id' => $s->id, 'revision_id' => $s->submitted_revision_id], 'requires_action' => $action, 'action_state' => $s->status, 'title' => $title, 'message' => $message ?? $title.'.', 'severity' => 'info']);
    }
}
