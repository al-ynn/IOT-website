<?php

namespace Tests\Feature;

use App\Collaboration\CollaborationResourceRegistry;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class BlueprintDomainBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_blueprint_domain_is_not_fabricated_or_registered(): void
    {
        $this->assertFalse(class_exists(\App\Models\Blueprint::class));
        $this->assertFalse(Schema::hasTable('blueprints'));
        $this->assertArrayNotHasKey('blueprint', app(CollaborationResourceRegistry::class)->metadata());

        try {
            app(CollaborationResourceRegistry::class)->definition('blueprint');
            $this->fail('Absent Blueprint domain must not be registered.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('resource_type', $exception->errors());
        }
    }

    public function test_blueprint_routes_and_review_actions_do_not_exist(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri());
        $this->assertFalse($uris->contains(fn ($uri) => str_contains($uri, 'blueprint')));
        $this->assertFalse($uris->contains(fn ($uri) => str_contains($uri, 'blueprints')));
    }

    public function test_generic_share_endpoint_rejects_blueprint_type_safely(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'blueprint-boundary']);
        $actor = User::factory()->create(['organization_id' => $organization->id, 'role' => 'owner', 'status' => 'active']);
        $recipient = User::factory()->create(['organization_id' => $organization->id, 'role' => 'staff', 'status' => 'active']);

        $this->actingAs($actor)->postJson('/api/shares', ['resource_type' => 'blueprint', 'resource_id' => 1, 'recipient_id' => $recipient->id, 'permission' => 'edit'])
            ->assertUnprocessable()->assertJsonValidationErrors('resource_type');
        $this->assertDatabaseCount('resource_share_requests', 0);
        $this->assertDatabaseCount('resource_collaborators', 0);
        $this->assertDatabaseCount('resource_revisions', 0);
    }
}
