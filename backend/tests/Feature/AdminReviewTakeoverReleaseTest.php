<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceReviewEvent;
use App\Models\User;
use App\Services\ReviewClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

final class AdminReviewTakeoverReleaseTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $organization, bool $admin = false): User
    {
        return User::factory()->create(['organization_id' => $organization->id, 'role' => $admin ? 'staff' : 'owner', 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']);
    }

    private function submission(User $creator): ResourcePublicationSubmission
    {
        $template = $this->actingAs($creator)->postJson('/api/device-templates', ['name' => 'Phase 86 Review'])->assertCreated()->json('data.id');
        $id = $this->actingAs($creator)->postJson("/api/device-templates/{$template}/publication-submissions")->assertCreated()->json('data.id');
        return ResourcePublicationSubmission::findOrFail($id);
    }

    public function test_stale_takeover_cannot_overwrite_a_newer_takeover_or_duplicate_history(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'phase-86-stale']);
        $creator = $this->user($org);
        $owner = $this->user($org, true);
        $adminTwo = $this->user($org, true);
        $adminThree = $this->user($org, true);
        $submission = $this->submission($creator);
        $service = app(ReviewClaimService::class);
        $service->claim($owner, $submission);
        $staleTwo = ResourcePublicationSubmission::findOrFail($submission->id);
        $staleThree = ResourcePublicationSubmission::findOrFail($submission->id);

        $service->takeover($adminTwo, $staleTwo, true);
        try {
            $service->takeover($adminThree, $staleThree, true);
            $this->fail('A stale takeover must conflict.');
        } catch (ConflictHttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertDatabaseHas('resource_publication_submissions', ['id' => $submission->id, 'status' => 'submitted', 'reviewer_id' => $adminTwo->id]);
        $this->assertSame(1, ResourceReviewEvent::where(['submission_id' => $submission->id, 'event_type' => 'review.taken_over'])->count());
    }

    public function test_invalid_reviewer_requires_explicit_takeover_and_records_factual_recovery(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'phase-86-recovery']);
        $creator = $this->user($org);
        $formerAdmin = $this->user($org, true);
        $recoveringAdmin = $this->user($org, true);
        $submission = $this->submission($creator);
        $service = app(ReviewClaimService::class);
        $service->claim($formerAdmin, $submission);
        $formerAdmin->update(['platform_role' => null, 'status' => 'inactive']);
        $staleClaimedAt = $submission->fresh()->review_claimed_at;

        $this->actingAs($recoveringAdmin)->postJson("/api/admin/template-publication-submissions/{$submission->id}/takeover", ['confirm' => false])->assertUnprocessable();
        $this->assertEquals($staleClaimedAt, $submission->fresh()->review_claimed_at);
        $this->actingAs($recoveringAdmin)->postJson("/api/admin/template-publication-submissions/{$submission->id}/takeover", ['confirm' => true])->assertOk()->assertJsonPath('data.reviewer.id', (string) $recoveringAdmin->id);
        $this->assertDatabaseHas('resource_review_events', ['submission_id' => $submission->id, 'event_type' => 'review.taken_over', 'previous_reviewer_id' => $formerAdmin->id, 'reviewer_id' => $recoveringAdmin->id, 'actor_id' => $recoveringAdmin->id]);
    }

    public function test_release_is_owner_only_and_changes_no_decision_or_resource_fact(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'phase-86-release']);
        $creator = $this->user($org);
        $owner = $this->user($org, true);
        $other = $this->user($org, true);
        $submission = $this->submission($creator);
        $service = app(ReviewClaimService::class);
        $service->claim($owner, $submission);
        $revisionId = $submission->submitted_revision_id;

        $this->actingAs($other)->postJson("/api/admin/template-publication-submissions/{$submission->id}/release")->assertUnprocessable();
        $this->actingAs($owner)->postJson("/api/admin/template-publication-submissions/{$submission->id}/release")->assertOk()->assertJsonPath('data.reviewState', 'available');
        $current = $submission->fresh();
        $this->assertSame('submitted', $current->status);
        $this->assertSame($revisionId, $current->submitted_revision_id);
        $this->assertNull($current->reviewer_id);
        $this->assertNull($current->review_claimed_at);
        $this->assertNull($current->decision_by);
        $this->assertSame(1, ResourceReviewEvent::where(['submission_id' => $submission->id, 'event_type' => 'review.released'])->count());
    }

    public function test_inactive_or_demoted_actor_cannot_mutate_ownership_via_service(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'phase-86-actor']);
        $creator = $this->user($org);
        $inactiveAdmin = $this->user($org, true);
        $submission = $this->submission($creator);
        $inactiveAdmin->update(['status' => 'inactive']);

        try {
            app(ReviewClaimService::class)->claim($inactiveAdmin->fresh(), $submission);
            $this->fail('Inactive Admin must be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertNull($submission->fresh()->reviewer_id);
    }
}
