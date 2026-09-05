<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ResourceCollaborator;
use App\Models\ResourcePublicationState;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceReviewEvent;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class ReviewHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $organization, bool $admin = false): User
    {
        return User::factory()->create(['organization_id' => $organization->id, 'role' => $admin ? 'staff' : 'owner', 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']);
    }

    private function submit(User $creator): array
    {
        $template = $this->actingAs($creator)->postJson('/api/device-templates', ['name' => 'Governed'])->assertCreated()->json('data.id');
        $submission = $this->actingAs($creator)->postJson("/api/device-templates/{$template}/publication-submissions")->assertCreated()->json('data.id');
        return [$template, (int) $submission];
    }

    public function test_governance_transitions_append_exact_revision_aware_events(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'review-history-events']);
        $creator = $this->user($organization);
        $adminOne = $this->user($organization, true);
        $adminTwo = $this->user($organization, true);
        [$template, $submission] = $this->submit($creator);
        $revisionCount = ResourceRevision::count();

        $this->actingAs($adminOne)->postJson("/api/admin/template-publication-submissions/{$submission}/claim")->assertOk();
        $this->actingAs($adminTwo)->postJson("/api/admin/template-publication-submissions/{$submission}/takeover", ['confirm' => true])->assertOk();
        $this->actingAs($adminTwo)->postJson("/api/admin/template-publication-submissions/{$submission}/release")->assertOk();
        $this->actingAs($adminOne)->postJson("/api/admin/template-publication-submissions/{$submission}/claim")->assertOk();
        $this->actingAs($adminOne)->postJson("/api/admin/template-publication-submissions/{$submission}/approve")->assertOk();

        $types = ResourceReviewEvent::where('submission_id', $submission)->orderBy('id')->pluck('event_type')->all();
        $this->assertSame(['review.submitted', 'review.claimed', 'review.taken_over', 'review.released', 'review.claimed', 'review.approved'], $types);
        $takeover = ResourceReviewEvent::where('event_type', 'review.taken_over')->firstOrFail();
        $this->assertSame($adminOne->id, $takeover->previous_reviewer_id);
        $this->assertSame($adminTwo->id, $takeover->reviewer_id);
        $approval = ResourceReviewEvent::where('event_type', 'review.approved')->firstOrFail();
        $this->assertSame($approval->resource_revision_id, $approval->approved_revision_id);
        $this->assertSame($revisionCount, ResourceRevision::count());
        $this->actingAs($creator)->getJson("/api/device-templates/{$template}/review-history")->assertOk()->assertJsonPath('data.0.status', 'approved')->assertJsonCount(6, 'data.0.events');
    }

    public function test_multiple_cycles_and_previous_approval_are_preserved(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'review-history-cycles']);
        $creator = $this->user($organization);
        $admin = $this->user($organization, true);
        [$template, $first] = $this->submit($creator);
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$first}/claim")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$first}/approve")->assertOk();
        $previous = ResourcePublicationState::where('resource_id', $template)->value('approved_revision_id');
        $this->actingAs($creator)->patchJson("/api/device-templates/{$template}", ['description' => 'second'])->assertOk();
        $second = $this->actingAs($creator)->postJson("/api/device-templates/{$template}/publication-submissions")->assertCreated()->json('data.id');
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$second}/claim")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$second}/approve")->assertOk();
        $event = ResourceReviewEvent::where(['submission_id' => $second, 'event_type' => 'review.approved'])->firstOrFail();
        $this->assertSame($previous, $event->previous_approved_revision_id);
        $this->assertNotSame($previous, $event->approved_revision_id);
        $this->actingAs($creator)->getJson("/api/device-templates/{$template}/review-history")->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.current', false);
    }

    public function test_current_authorization_controls_private_history_and_no_mutation_routes_exist(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'review-history-auth']);
        $creator = $this->user($organization);
        $viewer = $this->user($organization);
        $outsider = $this->user($organization);
        [$template, $submission] = $this->submit($creator);
        ResourceCollaborator::create(['resource_type' => 'device_template', 'resource_id' => $template, 'user_id' => $viewer->id, 'permission' => 'view', 'granted_by' => $creator->id]);
        $this->actingAs($viewer)->getJson("/api/device-templates/{$template}/review-history")->assertOk();
        ResourceCollaborator::where(['resource_type' => 'device_template', 'resource_id' => $template, 'user_id' => $viewer->id])->delete();
        $this->actingAs($viewer)->getJson("/api/device-templates/{$template}/review-history")->assertNotFound();
        $this->actingAs($outsider)->getJson("/api/device-templates/{$template}/review-history")->assertNotFound();
        $event = ResourceReviewEvent::where('submission_id', $submission)->firstOrFail();
        $this->actingAs($creator)->patchJson("/api/review-history/{$event->id}", ['event_type' => 'review.approved'])->assertNotFound();
        $this->actingAs($creator)->deleteJson("/api/review-history/{$event->id}")->assertNotFound();
        $this->expectException(LogicException::class);
        $event->update(['event_type' => 'review.approved']);
    }

    public function test_failed_action_and_backfill_retries_do_not_duplicate_events(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'review-history-idempotency']);
        $creator = $this->user($organization);
        $admin = $this->user($organization, true);
        [, $submission] = $this->submit($creator);
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$submission}/approve")->assertUnprocessable();
        $this->assertDatabaseCount('resource_review_events', 1);
        $this->artisan('review-history:backfill')->assertSuccessful();
        $this->artisan('review-history:backfill')->assertSuccessful();
        $this->assertDatabaseCount('resource_review_events', 1);
    }
}
