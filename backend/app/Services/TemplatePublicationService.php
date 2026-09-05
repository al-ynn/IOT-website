<?php

namespace App\Services;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Models\DeviceTemplate;
use App\Models\Notification;
use App\Models\ResourceLifecycleState;
use App\Models\ResourcePublicationState;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TemplatePublicationService
{
    public function __construct(private CollaborationResourceRegistry $registry, private ReviewHistoryService $history, private ResourcePublicationVersionService $versions) {}

    public function submit(User $actor, DeviceTemplate $template, ?int $revisionId = null): ResourcePublicationSubmission
    {
        app(ResourceLifecycleService::class)->assertActive('device_template', $template->id, 'Disabled or Archived Templates cannot be submitted.');
        $this->registry->resolve($actor, new CollaborationResourceReference('device_template', $template->id), 'edit');
        $latest = ResourceRevision::where('resource_type', 'device_template')->where('resource_id', $template->id)->latest('revision_number')->firstOrFail();
        if ($revisionId !== null && $revisionId !== (int) $latest->id) {
            throw ValidationException::withMessages(['revision_id' => ['Only the latest shared Template revision may be submitted.']]);
        }

        try {
            $submission = DB::transaction(function () use ($actor, $template, $latest) {
                $active = ResourcePublicationSubmission::where('resource_type', 'device_template')->where('resource_id', $template->id)->whereIn('status', ResourcePublicationSubmission::ACTIVE_STATUSES)->lockForUpdate()->first();
                if ($active) {
                    throw ValidationException::withMessages(['template' => ['This Template already has an active publication submission.']]);
                }
                $submission = ResourcePublicationSubmission::create([
                    'resource_type' => 'device_template', 'resource_id' => $template->id,
                    'submitted_revision_id' => $latest->id, 'submitted_by' => $actor->id,
                    'status' => 'submitted', 'active_key' => "device_template:{$template->id}", 'submitted_at' => now(),
                ]);
                $this->history->append($submission, ReviewHistoryService::SUBMITTED, $actor);

                return $submission;
            });
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'active_key')) {
                throw ValidationException::withMessages(['template' => ['This Template already has an active publication submission.']]);
            }
            throw $exception;
        }
        foreach (User::where('platform_role', 'platform_admin')->where('status', 'active')->get() as $admin) {
            $this->notify($admin, $actor, $submission, 'template.publication.submitted', 'Template submitted for approval', "{$template->name} revision {$latest->revision_number} is ready for review.", true);
        }

        return $this->load($submission);
    }

    public function approve(User $admin, ResourcePublicationSubmission $submission, bool $confirmOlder = false): ResourcePublicationSubmission
    {
        return $this->decide($admin, $submission, 'approved', null, $confirmOlder);
    }

    public function requestChanges(User $admin, ResourcePublicationSubmission $submission, string $note): ResourcePublicationSubmission
    {
        return $this->decide($admin, $submission, 'changes_requested', $this->safeNote($note));
    }

    public function reject(User $admin, ResourcePublicationSubmission $submission, string $note): ResourcePublicationSubmission
    {
        return $this->decide($admin, $submission, 'rejected', $this->safeNote($note));
    }

    public function state(DeviceTemplate $template): array
    {
        $latest = ResourceRevision::where('resource_type', 'device_template')->where('resource_id', $template->id)->latest('revision_number')->first();
        $publication = ResourcePublicationState::where(['resource_type' => 'device_template', 'resource_id' => $template->id])->with(['approvedRevision', 'currentVersion.revision'])->first();
        $active = ResourcePublicationSubmission::where(['resource_type' => 'device_template', 'resource_id' => $template->id, 'status' => 'submitted'])->with(['revision', 'reviewer:id,name'])->first();
        $last = ResourcePublicationSubmission::where(['resource_type' => 'device_template', 'resource_id' => $template->id])->latest('id')->first();
        $approved = $publication?->currentVersion?->revision;
        $display = $active ? 'submitted' : ($last?->status === 'changes_requested' ? 'changes_requested' : ($approved ? (($latest?->id === $approved->id) ? 'approved' : 'approved_with_draft_changes') : ($last?->status === 'rejected' ? 'rejected' : 'draft')));

        return ['displayState' => $display, 'latestRevision' => $this->revisionData($latest), 'approvedRevision' => $this->revisionData($approved), 'currentPublicationVersion' => $publication?->currentVersion ? $this->versionData($publication->currentVersion) : null, 'activeSubmission' => $active ? $this->submissionData($active, $latest) : null];
    }

    public function publishedForOrganization(int $organizationId)
    {
        $unavailable = ResourceLifecycleState::where('resource_type', 'device_template')->whereIn('state', ['disabled', 'archived'])->select('resource_id');

        return DeviceTemplate::query()->where('organization_id', $organizationId)->whereNotIn('id', $unavailable)->whereHas('publicationState', fn ($query) => $query->whereNotNull('current_publication_version_id'));
    }

    private function decide(User $admin, ResourcePublicationSubmission $submission, string $decision, ?string $note, bool $confirmOlder = false): ResourcePublicationSubmission
    {
        abort_unless($admin->isPlatformAdmin() && $admin->status === 'active'
            && $submission->resource_type === 'device_template'
            && DeviceTemplate::whereKey($submission->resource_id)->where('organization_id', $admin->organization_id)->exists(), 404);
        app(ResourceLifecycleService::class)->assertActive('device_template', $submission->resource_id, 'Restore the Template before making a review decision.');
        $decided = DB::transaction(function () use ($admin, $submission, $decision, $note, $confirmOlder) {
            $locked = ResourcePublicationSubmission::lockForUpdate()->findOrFail($submission->id);
            if ($locked->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => ['This submission already has a final decision.']]);
            }
            if ((int) $locked->reviewer_id !== (int) $admin->id) {
                throw ValidationException::withMessages(['reviewer' => ['Start or take over this review before making a decision.']]);
            }
            $revision = ResourceRevision::whereKey($locked->submitted_revision_id)->where(['resource_type' => 'device_template', 'resource_id' => $locked->resource_id])->firstOrFail();
            $latest = ResourceRevision::where(['resource_type' => 'device_template', 'resource_id' => $locked->resource_id])->latest('revision_number')->firstOrFail();
            if ($decision === 'approved' && $latest->id !== $revision->id && ! $confirmOlder) {
                throw ValidationException::withMessages(['confirm_older_revision' => ['Confirm approval of the submitted revision while newer draft changes remain unpublished.']]);
            }
            $previousApprovedRevisionId = ResourcePublicationState::where(['resource_type' => 'device_template', 'resource_id' => $locked->resource_id])->value('approved_revision_id');
            $publicationVersion = $decision === 'approved' ? $this->versions->publish($locked, $admin) : null;
            $locked->update(['status' => $decision, 'active_key' => null, 'reviewer_id' => null, 'review_claimed_at' => null, 'decision_by' => $admin->id, 'decision_at' => now(), 'decision_note' => $note]);
            $type = match ($decision) {
                'approved' => ReviewHistoryService::APPROVED,
                'changes_requested' => ReviewHistoryService::CHANGES_REQUESTED,
                'rejected' => ReviewHistoryService::REJECTED,
            };
            $facts = $decision === 'approved' ? ['previous_approved_revision_id' => $previousApprovedRevisionId, 'approved_revision_id' => $revision->id, 'metadata' => ['publication_version_id' => $publicationVersion->id]] : [];
            $this->history->append($locked, $type, $admin, $facts, at: $locked->decision_at);

            return $locked->refresh();
        });
        Notification::where('deduplication_key', 'like', "template.publication.submitted:template-submission:{$decided->id}:%")
            ->update(['requires_action' => false, 'action_state' => $decision]);
        $titles = ['approved' => 'Template approved', 'changes_requested' => 'Template changes requested', 'rejected' => 'Template submission rejected'];
        $version = $decided->publicationVersion;
        $message = $decision === 'approved' && $version ? "Template Version {$version->publication_number} is now available." : ($note ?: $titles[$decision].'.');
        $this->notify($decided->submitter, $admin, $decided, "template.publication.{$decision}", $titles[$decision], $message, false);

        return $this->load($decided);
    }

    private function safeNote(string $note): string
    {
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > 1000) {
            throw ValidationException::withMessages(['note' => ['A decision note between 1 and 1000 characters is required.']]);
        }
        if (preg_match('/(?:authorization\s*:|bearer\s+[a-z0-9._-]+|-----begin [^-]*private key-----)/i', $note)) {
            throw ValidationException::withMessages(['note' => ['Decision notes must not contain secrets.']]);
        }

        return $note;
    }

    private function notify(User $recipient, User $actor, ResourcePublicationSubmission $submission, string $type, string $title, string $message, bool $action): void
    {
        app(NotificationOutboxService::class)->recordNotification($recipient, $type, "template-submission:{$submission->id}", ['organization_id' => $recipient->organization_id, 'actor_id' => $actor->id, 'resource_type' => 'device_template', 'resource_id' => $submission->resource_id, 'action_url' => $recipient->isPlatformAdmin() ? "/admin/template-submissions/{$submission->id}" : "/app/developer/templates/{$submission->resource_id}", 'data' => ['submission_id' => $submission->id, 'revision_id' => $submission->submitted_revision_id], 'requires_action' => $action, 'action_state' => $submission->status, 'title' => $title, 'message' => $message, 'severity' => 'info']);
    }

    private function load(ResourcePublicationSubmission $submission): ResourcePublicationSubmission
    {
        return $submission->load(['revision', 'submitter:id,name', 'decisionMaker:id,name', 'reviewer:id,name', 'publicationVersion']);
    }

    private function revisionData(?ResourceRevision $revision): ?array
    {
        return $revision ? ['id' => (string) $revision->id, 'number' => $revision->revision_number] : null;
    }

    public function submissionData(ResourcePublicationSubmission $submission, ?ResourceRevision $latest = null): array
    {
        return ['id' => (string) $submission->id, 'status' => $submission->status, 'reviewState' => $submission->status === 'submitted' ? ($submission->reviewer_id ? 'in_review' : 'available') : 'terminal', 'reviewer' => $submission->reviewer ? ['id' => (string) $submission->reviewer->id, 'name' => $submission->reviewer->name] : null, 'reviewClaimedAt' => $submission->review_claimed_at?->toISOString(), 'submittedRevision' => $this->revisionData($submission->revision), 'publicationVersion' => $submission->publicationVersion ? $this->versionData($submission->publicationVersion) : null, 'submittedBy' => $submission->submitter ? ['id' => (string) $submission->submitter->id, 'name' => $submission->submitter->name] : null, 'submittedAt' => $submission->submitted_at?->toISOString(), 'decisionBy' => $submission->decisionMaker ? ['id' => (string) $submission->decisionMaker->id, 'name' => $submission->decisionMaker->name] : null, 'decisionAt' => $submission->decision_at?->toISOString(), 'decisionNote' => $submission->decision_note, 'newerDraftExists' => $latest ? $latest->id !== $submission->submitted_revision_id : false];
    }

    private function versionData($version): array
    {
        return ['id' => (string) $version->id, 'number' => $version->publication_number, 'revision' => $this->revisionData($version->revision), 'publishedAt' => $version->published_at?->toISOString()];
    }
}
