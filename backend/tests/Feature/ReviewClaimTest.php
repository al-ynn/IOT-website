<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReviewClaimTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $organization, bool $admin = false): User
    {
        return User::factory()->create(['organization_id' => $organization->id, 'role' => $admin ? 'staff' : 'owner', 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']);
    }

    private function submission(User $creator): int
    {
        $template = $this->actingAs($creator)->postJson('/api/device-templates', ['name' => 'Reviewable'])->assertCreated()->json('data.id');
        return (int) $this->actingAs($creator)->postJson("/api/device-templates/{$template}/publication-submissions")->assertCreated()->json('data.id');
    }

    public function test_start_release_and_takeover_preserve_submission_and_revision(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'review-claim']);
        $creator = $this->user($organization);
        $adminOne = $this->user($organization, true);
        $adminTwo = $this->user($organization, true);
        $id = $this->submission($creator);
        $submission = ResourcePublicationSubmission::findOrFail($id);
        $revisionId = $submission->submitted_revision_id;
        $revisionCount = ResourceRevision::count();

        $this->actingAs($adminOne)->postJson("/api/admin/template-publication-submissions/{$id}/claim")->assertOk()->assertJsonPath('data.reviewState', 'in_review')->assertJsonPath('data.reviewer.id', (string) $adminOne->id);
        $this->actingAs($adminTwo)->postJson("/api/admin/template-publication-submissions/{$id}/claim")->assertUnprocessable();
        $this->actingAs($adminOne)->postJson("/api/admin/template-publication-submissions/{$id}/release")->assertOk()->assertJsonPath('data.reviewState', 'available')->assertJsonPath('data.reviewer', null);
        $this->actingAs($adminTwo)->postJson("/api/admin/template-publication-submissions/{$id}/claim")->assertOk();
        $this->actingAs($adminOne)->postJson("/api/admin/template-publication-submissions/{$id}/takeover", ['confirm' => false])->assertUnprocessable();
        $this->actingAs($adminOne)->postJson("/api/admin/template-publication-submissions/{$id}/takeover", ['confirm' => true])->assertOk()->assertJsonPath('data.reviewer.id', (string) $adminOne->id);

        $submission->refresh();
        $this->assertSame($revisionId, $submission->submitted_revision_id);
        $this->assertSame('submitted', $submission->status);
        $this->assertSame($revisionCount, ResourceRevision::count());
    }

    public function test_only_current_reviewer_can_decide_and_final_decision_clears_claim(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'review-decision']);
        $creator = $this->user($organization);
        $adminOne = $this->user($organization, true);
        $adminTwo = $this->user($organization, true);
        $id = $this->submission($creator);

        $this->actingAs($adminOne)->postJson("/api/admin/template-publication-submissions/{$id}/approve")->assertUnprocessable()->assertJsonValidationErrors('reviewer');
        $this->actingAs($adminOne)->postJson("/api/admin/template-publication-submissions/{$id}/claim")->assertOk();
        $this->actingAs($adminTwo)->postJson("/api/admin/template-publication-submissions/{$id}/reject", ['note' => 'No'])->assertUnprocessable()->assertJsonValidationErrors('reviewer');
        $this->actingAs($adminOne)->postJson("/api/admin/template-publication-submissions/{$id}/approve")->assertOk()->assertJsonPath('data.reviewState', 'terminal')->assertJsonPath('data.reviewer', null);
        $this->assertDatabaseHas('resource_publication_submissions', ['id' => $id, 'status' => 'approved', 'reviewer_id' => null, 'decision_by' => $adminOne->id]);
        $this->actingAs($adminOne)->postJson("/api/admin/template-publication-submissions/{$id}/claim")->assertUnprocessable();
    }

    public function test_staff_cannot_claim_release_or_takeover(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'review-staff']);
        $creator = $this->user($organization);
        $id = $this->submission($creator);
        foreach (['claim', 'release', 'takeover'] as $action) {
            $this->actingAs($creator)->postJson("/api/admin/template-publication-submissions/{$id}/{$action}", ['confirm' => true])->assertForbidden();
        }
        $this->assertDatabaseHas('resource_publication_submissions', ['id' => $id, 'status' => 'submitted', 'reviewer_id' => null]);
    }

    public function test_admin_cannot_claim_a_foreign_organization_submission(): void
    {
        $organizationA = Organization::create(['name' => 'Org A', 'slug' => 'review-org-a']);
        $organizationB = Organization::create(['name' => 'Org B', 'slug' => 'review-org-b']);
        $foreignCreator = $this->user($organizationB);
        $adminA = $this->user($organizationA, true);
        $id = $this->submission($foreignCreator);

        $this->actingAs($adminA)
            ->getJson("/api/admin/template-publication-submissions/{$id}")
            ->assertNotFound();

        $this->actingAs($adminA)
            ->postJson("/api/admin/template-publication-submissions/{$id}/claim")
            ->assertNotFound();

        ResourcePublicationSubmission::whereKey($id)->update(['reviewer_id' => $adminA->id]);
        $this->actingAs($adminA)
            ->postJson("/api/admin/template-publication-submissions/{$id}/approve")
            ->assertNotFound();

        $this->assertDatabaseHas('resource_publication_submissions', [
            'id' => $id,
            'status' => 'submitted',
            'reviewer_id' => $adminA->id,
        ]);
    }
}
