<?php

namespace App\Services;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Models\FirmwareArtifact;
use App\Models\FirmwareRelease;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class FirmwareReleaseService
{
    public function __construct(private CollaborationResourceRegistry $registry, private ReviewHistoryService $history) {}

    public function submit(User $actor, FirmwareArtifact $firmware): ResourcePublicationSubmission
    {
        $this->registry->resolve($actor, new CollaborationResourceReference('firmware', $firmware->id), 'edit');
        app(ResourceLifecycleService::class)->assertActive('firmware', $firmware->id, 'Disabled or Archived Firmware cannot be submitted.');
        $revision = ResourceRevision::where(['resource_type' => 'firmware', 'resource_id' => $firmware->id])->latest('revision_number')->firstOrFail();
        $this->integrity($firmware);
        $submission = DB::transaction(function () use ($actor, $firmware, $revision) {
            if (ResourcePublicationSubmission::where(['resource_type' => 'firmware', 'resource_id' => $firmware->id])->whereIn('status', ResourcePublicationSubmission::ACTIVE_STATUSES)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['firmware' => ['This Firmware already has an active release submission.']]);
            }$row = ResourcePublicationSubmission::create(['resource_type' => 'firmware', 'resource_id' => $firmware->id, 'submitted_revision_id' => $revision->id, 'submitted_by' => $actor->id, 'status' => 'submitted', 'active_key' => "firmware:{$firmware->id}", 'submitted_at' => now()]);
            $this->history->append($row, ReviewHistoryService::SUBMITTED, $actor, ['metadata' => ['artifact_id' => $firmware->id, 'artifact_sha256' => $firmware->sha256, 'domain_version' => $firmware->version]]);

            return $row;
        });
        foreach (User::where('platform_role', 'platform_admin')->where('organization_id', $firmware->organization_id)->where('status', 'active')->get() as $admin) {
            $this->notify($admin, $actor, $submission, 'firmware.release_submitted', 'Firmware release submitted', true);
        }

        return $this->load($submission);
    }

    public function decide(User $admin, ResourcePublicationSubmission $submission, string $decision, ?string $note = null): ResourcePublicationSubmission
    {
        abort_unless($admin->isPlatformAdmin() && $admin->status === 'active' && $submission->resource_type === 'firmware', 404);
        if (in_array($decision, ['changes_requested', 'rejected'], true)) {
            $note = $this->safeNote($note);
        }$result = DB::transaction(function () use ($admin, $submission, $decision, $note) {
            $locked = ResourcePublicationSubmission::lockForUpdate()->findOrFail($submission->id);
            abort_unless($locked->resource_type === 'firmware'
                && FirmwareArtifact::whereKey($locked->resource_id)->where('organization_id', $admin->organization_id)->exists(), 404);
            if ($locked->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => ['This submission already has a final decision.']]);
            }if ((int) $locked->reviewer_id !== (int) $admin->id) {
                throw ValidationException::withMessages(['reviewer' => ['Start or take over this review before making a decision.']]);
            }app(ResourceLifecycleService::class)->assertActive('firmware', $locked->resource_id, 'Restore the Firmware before making a release decision.');
            $firmware = FirmwareArtifact::lockForUpdate()->findOrFail($locked->resource_id);
            $revision = ResourceRevision::whereKey($locked->submitted_revision_id)->where(['resource_type' => 'firmware', 'resource_id' => $firmware->id])->firstOrFail();
            $candidate = $revision->snapshot['artifact'] ?? [];
            if ((int) ($candidate['id'] ?? 0) !== $firmware->id || ! hash_equals((string) ($candidate['sha256'] ?? ''), $firmware->sha256)) {
                throw ValidationException::withMessages(['artifact' => ['Submitted artifact identity no longer matches canonical immutable metadata.']]);
            }$this->integrity($firmware);
            $release = null;
            if ($decision === 'approved') {
                FirmwareRelease::where('firmware_artifact_id', $firmware->id)->where('is_current', true)->update(['is_current' => false]);
                $release = FirmwareRelease::firstOrCreate(['review_submission_id' => $locked->id], ['firmware_artifact_id' => $firmware->id, 'resource_revision_id' => $revision->id, 'domain_version' => $revision->snapshot['metadata']['version'], 'artifact_sha256' => $candidate['sha256'], 'approved_by' => $admin->id, 'approved_at' => now(), 'is_current' => true]);
            }$locked->update(['status' => $decision, 'active_key' => null, 'reviewer_id' => null, 'review_claimed_at' => null, 'decision_by' => $admin->id, 'decision_at' => now(), 'decision_note' => $note]);
            $event = match ($decision) {
                'approved' => ReviewHistoryService::APPROVED,'changes_requested' => ReviewHistoryService::CHANGES_REQUESTED,'rejected' => ReviewHistoryService::REJECTED
            };
            $this->history->append($locked, $event, $admin, ['approved_revision_id' => $decision === 'approved' ? $revision->id : null, 'metadata' => $release ? ['firmware_release_id' => $release->id, 'artifact_id' => $firmware->id, 'artifact_sha256' => $firmware->sha256] : []]);

            return $locked;
        });
        $this->notify($result->submitter, $admin, $result, "firmware.release_{$decision}", 'Firmware release '.str_replace('_', ' ', $decision), $decision === 'changes_requested');

        return $this->load($result);
    }

    public function state(User $viewer, FirmwareArtifact $firmware): array
    {
        $this->registry->resolve($viewer, new CollaborationResourceReference('firmware', $firmware->id), 'view');
        $latest = ResourceRevision::where(['resource_type' => 'firmware', 'resource_id' => $firmware->id])->latest('revision_number')->first();
        $active = ResourcePublicationSubmission::where(['resource_type' => 'firmware', 'resource_id' => $firmware->id, 'status' => 'submitted'])->with(['revision', 'reviewer:id,name', 'submitter:id,name'])->first();
        $current = FirmwareRelease::where(['firmware_artifact_id' => $firmware->id, 'is_current' => true])->with(['revision', 'approver:id,name'])->first();

        return ['latestRevision' => $latest ? ['id' => (string) $latest->id, 'number' => $latest->revision_number] : null, 'activeSubmission' => $active ? $this->data($active) : null, 'currentRelease' => $current ? ['id' => (string) $current->id, 'domainVersion' => $current->domain_version, 'artifactId' => (string) $current->firmware_artifact_id, 'sha256' => $current->artifact_sha256, 'revision' => ['id' => (string) $current->revision->id, 'number' => $current->revision->revision_number], 'approvedBy' => $current->approver ? ['id' => (string) $current->approver->id, 'name' => $current->approver->name] : null, 'approvedAt' => $current->approved_at?->toISOString()] : null];
    }

    public function history(User $viewer, FirmwareArtifact $firmware)
    {
        $this->registry->resolve($viewer, new CollaborationResourceReference('firmware', $firmware->id), 'view');

        return FirmwareRelease::where('firmware_artifact_id', $firmware->id)->with(['revision:id,revision_number', 'approver:id,name'])->latest('approved_at')->paginate(20);
    }

    public function data(ResourcePublicationSubmission $s): array
    {
        $firmware = FirmwareArtifact::find($s->resource_id);
        $revision = $s->revision;

        return ['submissionId' => (string) $s->id, 'resourceType' => 'firmware', 'resourceId' => (string) $s->resource_id, 'resourceLabel' => $firmware?->name ?? 'Firmware unavailable', 'status' => $s->status, 'submittedRevision' => $revision ? ['id' => (string) $revision->id, 'number' => $revision->revision_number] : null, 'candidate' => data_get($revision?->snapshot, 'artifact'), 'domainVersion' => data_get($revision?->snapshot, 'metadata.version'), 'submittedBy' => $s->submitter ? ['id' => (string) $s->submitter->id, 'name' => $s->submitter->name] : null, 'submittedAt' => $s->submitted_at?->toISOString(), 'reviewer' => $s->reviewer ? ['id' => (string) $s->reviewer->id, 'name' => $s->reviewer->name] : null, 'decisionNote' => $s->decision_note];
    }

    private function integrity(FirmwareArtifact $f): void
    {
        if (! Storage::disk($f->storage_disk)->exists($f->storage_path)) {
            throw ValidationException::withMessages(['artifact' => ['Firmware artifact bytes are missing.']]);
        }if (! hash_equals($f->sha256, hash_file('sha256', Storage::disk($f->storage_disk)->path($f->storage_path)))) {
            throw ValidationException::withMessages(['artifact' => ['Firmware artifact checksum verification failed.']]);
        }
    }

    private function safeNote(?string $note): string
    {
        $note = trim((string) $note);
        if ($note === '' || mb_strlen($note) > 1000) {
            throw ValidationException::withMessages(['note' => ['A decision note between 1 and 1000 characters is required.']]);
        }

        return $note;
    }

    private function notify(?User $recipient, User $actor, ResourcePublicationSubmission $s, string $type, string $title, bool $action): void
    {
        if (! $recipient) {
            return;
        }
        app(NotificationOutboxService::class)->recordNotification($recipient, $type, "firmware-submission:{$s->id}", ['organization_id' => $recipient->organization_id, 'actor_id' => $actor->id, 'resource_type' => 'firmware', 'resource_id' => $s->resource_id, 'action_url' => $recipient->isPlatformAdmin() ? "/admin/firmware-submissions/{$s->id}" : "/app/developer/firmware/{$s->resource_id}", 'data' => ['submission_id' => $s->id, 'revision_id' => $s->submitted_revision_id], 'requires_action' => $action, 'action_state' => $s->status, 'title' => $title, 'message' => $title.'.', 'severity' => 'info']);
    }

    private function load(ResourcePublicationSubmission $s): ResourcePublicationSubmission
    {
        return $s->load(['revision', 'submitter:id,name', 'reviewer:id,name', 'decisionMaker:id,name']);
    }
}
