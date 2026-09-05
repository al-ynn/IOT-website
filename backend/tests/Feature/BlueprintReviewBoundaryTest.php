<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class BlueprintReviewBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_blueprint_review_or_decision_routes_are_active(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => strtolower($route->uri()));
        $this->assertFalse($uris->contains(fn ($uri) => str_contains($uri, 'blueprint')));
        foreach (['submit', 'approve', 'request-changes', 'reject', 'claim', 'release', 'takeover'] as $action) {
            $this->assertFalse($uris->contains(fn ($uri) => str_contains($uri, 'blueprint') && str_contains($uri, $action)));
        }
    }

    public function test_guessed_blueprint_review_endpoints_cannot_create_governance_state(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'blueprint-review-boundary']);
        $staff = User::factory()->create(['organization_id' => $organization->id, 'role' => 'owner', 'status' => 'active']);
        $admin = User::factory()->create(['organization_id' => $organization->id, 'role' => 'staff', 'platform_role' => 'platform_admin', 'status' => 'active']);

        $this->actingAs($staff)->postJson('/api/blueprints/1/review-submissions', ['revision_id' => 1])->assertNotFound();
        foreach (['approve', 'request-changes', 'reject'] as $action) {
            $this->actingAs($admin)->postJson("/api/admin/blueprint-review-submissions/1/{$action}", ['note' => 'guessed'])->assertNotFound();
        }
        $this->assertDatabaseCount('resource_publication_submissions', 0);
        $this->assertDatabaseCount('resource_publication_states', 0);
        $this->assertDatabaseCount('resource_revisions', 0);
    }

    public function test_phase_44_ownership_is_generic_without_blueprint_specific_storage(): void
    {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('resource_publication_submissions');
        $this->assertContains('reviewer_id', $columns);
        $this->assertContains('review_claimed_at', $columns);
        foreach (['claimed_by', 'claimed_at', 'released_at', 'taken_over_by'] as $column) {
            $this->assertNotContains($column, $columns);
        }
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('blueprint_reviewers'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('blueprint_review_claims'));
    }
}
