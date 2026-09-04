<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ResourcePublicationSubmission;
use App\Models\User;
use App\Services\ReviewDomainRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminReviewCenterTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $organization, bool $admin = false): User
    {
        return User::factory()->create(['organization_id' => $organization->id, 'role' => $admin ? 'staff' : 'owner', 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']);
    }

    private function submit(User $user, string $name): int
    {
        $template = $this->actingAs($user)->postJson('/api/device-templates', ['name' => $name])->assertCreated()->json('data.id');
        return (int) $this->actingAs($user)->postJson("/api/device-templates/{$template}/publication-submissions")->assertCreated()->json('data.id');
    }

    public function test_admin_sees_current_organization_active_queue_and_staff_is_denied(): void
    {
        $a = Organization::create(['name' => 'A', 'slug' => 'review-center-a']);
        $b = Organization::create(['name' => 'B', 'slug' => 'review-center-b']);
        $staffA = $this->user($a);
        $staffB = $this->user($b);
        $admin = $this->user($a, true);
        $this->submit($staffA, 'Alpha Template');
        $this->submit($staffB, 'Beta Template');

        $this->actingAs($admin)->getJson('/api/admin/reviews')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.resourceType', 'device_template')->assertJsonMissing(['resourceLabel' => 'Beta Template']);
        $this->actingAs($admin)->getJson('/api/admin/reviews/summary')->assertOk()->assertJsonPath('data.active', 1)->assertJsonPath('data.available', 1)->assertJsonPath('data.inReview', 0);
        $this->actingAs($staffA)->getJson('/api/admin/reviews')->assertForbidden();
        $this->actingAs($staffA)->getJson('/api/admin/reviews/summary')->assertForbidden();
    }

    public function test_available_in_review_mine_search_and_terminal_exclusion(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'review-center-filters']);
        $staff = $this->user($org);
        $adminOne = $this->user($org, true);
        $adminTwo = $this->user($org, true);
        $available = $this->submit($staff, 'Available Template');
        $mine = $this->submit($staff, 'Mine Template');
        $other = $this->submit($staff, 'Other Template');
        $terminal = $this->submit($staff, 'Terminal Template');
        $this->actingAs($adminOne)->postJson("/api/admin/template-publication-submissions/{$mine}/claim")->assertOk();
        $this->actingAs($adminTwo)->postJson("/api/admin/template-publication-submissions/{$other}/claim")->assertOk();
        $this->actingAs($adminOne)->postJson("/api/admin/template-publication-submissions/{$terminal}/claim")->assertOk();
        $this->actingAs($adminOne)->postJson("/api/admin/template-publication-submissions/{$terminal}/reject", ['note' => 'No'])->assertOk();

        $this->actingAs($adminOne)->getJson('/api/admin/reviews?state=available')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.submissionId', (string) $available);
        $this->actingAs($adminOne)->getJson('/api/admin/reviews?state=in_review')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reviewState', 'in_review');
        $this->actingAs($adminOne)->getJson('/api/admin/reviews?state=mine')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.submissionId', (string) $mine)->assertJsonPath('data.0.reviewState', 'mine');
        $this->actingAs($adminOne)->getJson('/api/admin/reviews?search=Other')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reviewer.id', (string) $adminTwo->id);
        $this->actingAs($adminOne)->getJson('/api/admin/reviews/summary')->assertOk()->assertJsonPath('data.active', 3)->assertJsonPath('data.available', 1)->assertJsonPath('data.inReview', 1)->assertJsonPath('data.mine', 1)->assertJsonPath('data.reviewerUnavailable', 0);
    }

    public function test_unavailable_reviewer_is_explicit_private_and_not_actionable(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'review-center-unavailable']);
        $staff = $this->user($org);
        $viewerAdmin = $this->user($org, true);
        $formerAdmin = $this->user($org, true);
        $submission = $this->submit($staff, 'Unavailable Reviewer Template');
        $this->actingAs($formerAdmin)->postJson("/api/admin/template-publication-submissions/{$submission}/claim")->assertOk();
        $formerAdmin->update(['platform_role' => null, 'status' => 'inactive']);

        $this->actingAs($viewerAdmin)->getJson('/api/admin/reviews?state=reviewer_unavailable')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reviewState', 'reviewer_unavailable')->assertJsonPath('data.0.reviewer', null)
            ->assertJsonPath('data.0.capabilities.canClaim', false)->assertJsonPath('data.0.capabilities.canRelease', false)->assertJsonPath('data.0.capabilities.canTakeOver', true);
    }

    public function test_queue_preserves_submitted_revision_and_publication_context(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'review-center-context']);
        $staff = $this->user($org);
        $admin = $this->user($org, true);
        $first = $this->submit($staff, 'Context Template');
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$first}/claim")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$first}/approve")->assertOk();
        $template = ResourcePublicationSubmission::findOrFail($first)->resource_id;
        $this->actingAs($staff)->patchJson("/api/device-templates/{$template}", ['description' => 'submitted'])->assertOk();
        $second = $this->actingAs($staff)->postJson("/api/device-templates/{$template}/publication-submissions")->assertCreated()->json('data.id');
        $submittedNumber = ResourcePublicationSubmission::findOrFail($second)->revision->revision_number;
        $this->actingAs($staff)->patchJson("/api/device-templates/{$template}", ['description' => 'newer'])->assertOk();

        $this->actingAs($admin)->getJson('/api/admin/reviews')->assertOk()->assertJsonPath('data.0.submittedRevision.number', $submittedNumber)->assertJsonPath('data.0.hasNewerDraftRevision', true)->assertJsonPath('data.0.currentPublication.number', 1)->assertJsonPath('data.0.currentPublication.revisionNumber', 1);
    }

    public function test_unsupported_domains_and_removed_admin_role_fail_safely(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'review-center-boundary']);
        $admin = $this->user($org, true);
        $this->actingAs($admin)->getJson('/api/admin/reviews?resource_type=blueprint')->assertUnprocessable();
        $admin->update(['platform_role' => null]);
        $this->actingAs($admin->fresh())->getJson('/api/admin/reviews')->assertForbidden();
    }

    public function test_registry_contains_exactly_the_six_real_review_domains(): void
    {
        $registry = $this->app->make(ReviewDomainRegistry::class);

        $this->assertSame([
            'device_template',
            'dashboard',
            'automation',
            'report',
            'webhook',
            'firmware',
        ], $registry->types());
        $this->assertSame('publication', $registry->definition('dashboard')['kind']);
        $this->assertSame('activation', $registry->definition('webhook')['kind']);
        $this->assertSame('release', $registry->definition('firmware')['kind']);
        $this->assertSame(
            '/admin/reviews/webhook/42',
            $registry->deepLink('webhook', 42),
        );
    }

    public function test_normalized_detail_is_read_only_and_decisions_require_ownership(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'review-center-detail']);
        $staff = $this->user($org);
        $admin = $this->user($org, true);
        $submission = $this->submit($staff, 'Detail Template');

        $this->actingAs($admin)
            ->getJson("/api/admin/reviews/device_template/{$submission}")
            ->assertOk()
            ->assertJsonPath('data.reviewKind', 'publication')
            ->assertJsonPath('data.capabilities.canClaim', true)
            ->assertJsonPath('data.capabilities.canApprove', false);

        $this->actingAs($admin)
            ->postJson("/api/admin/template-publication-submissions/{$submission}/claim")
            ->assertOk();

        $this->actingAs($admin)
            ->getJson("/api/admin/reviews/device_template/{$submission}")
            ->assertOk()
            ->assertJsonPath('data.reviewState', 'mine')
            ->assertJsonPath('data.capabilities.canApprove', true)
            ->assertJsonPath('data.capabilities.canRequestChanges', true)
            ->assertJsonPath('data.capabilities.canReject', true);

        $this->actingAs($admin)
            ->getJson("/api/admin/reviews/webhook/{$submission}")
            ->assertNotFound();
        $this->assertSame('submitted', ResourcePublicationSubmission::findOrFail($submission)->status);
    }
}
