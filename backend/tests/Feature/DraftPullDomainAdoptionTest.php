<?php

namespace Tests\Feature;

use App\Models\{Organization,ResourceDraft,ResourceRevision,User};
use App\Revisions\{DraftSnapshotPolicyRegistry,ResourceUpdatePolicyRegistry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DraftPullDomainAdoptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_real_domain_has_one_explicit_presentation_policy(): void
    {
        $policies = app(ResourceUpdatePolicyRegistry::class);

        $this->assertSame([
            'device' => ResourceUpdatePolicyRegistry::PULL_MANAGED,
            'dashboard' => ResourceUpdatePolicyRegistry::IMMEDIATE,
            'device_template' => ResourceUpdatePolicyRegistry::IMMEDIATE,
            'automation' => ResourceUpdatePolicyRegistry::IMMEDIATE,
            'report' => ResourceUpdatePolicyRegistry::IMMEDIATE,
            'webhook' => ResourceUpdatePolicyRegistry::IMMEDIATE,
            'location' => ResourceUpdatePolicyRegistry::UNSAFE_TO_PIN,
            'firmware' => ResourceUpdatePolicyRegistry::LIVE_ONLY,
        ], collect($policies->all())->map(fn (array $policy) => $policy['mode'])->all());
        $this->assertSame(['dashboard'], $policies->pullableSections('device'));
        $this->assertFalse($policies->isPullManaged('dashboard'));
        $this->expectException(ValidationException::class);
        $policies->get(\App\Models\Location::class);
    }

    public function test_draft_policy_rejects_secret_runtime_and_artifact_state(): void
    {
        $drafts = app(DraftSnapshotPolicyRegistry::class);
        $this->assertSame(
            ['configuration' => ['name' => 'Safe', 'url' => 'https://example.com', 'event_types' => ['automation.failed']]],
            $drafts->sanitize('webhook', ['configuration' => ['name' => 'Safe', 'url' => 'https://example.com', 'event_types' => ['automation.failed']]]),
        );

        foreach ([
            ['configuration' => ['signing_secret' => 'secret']],
            ['configuration' => ['delivery' => ['status' => 'queued']]],
            ['artifact' => ['storage_path' => 'private.bin']],
        ] as $unsafe) {
            try {
                $drafts->sanitize('webhook', $unsafe);
                $this->fail('Unsafe Draft state was accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_location_draft_apply_uses_safe_save_and_stale_apply_is_409(): void
    {
        $org = Organization::create(['name' => 'Plant', 'slug' => 'r07-location']);
        $user = User::factory()->create([
            'organization_id' => $org->id,
            'role' => 'staff',
            'status' => 'active',
        ]);
        $id = $this->actingAs($user)->postJson('/api/locations', ['name' => 'Lab'])
            ->assertCreated()->json('data.id');
        $base = ResourceRevision::where(['resource_type' => 'location', 'resource_id' => $id])
            ->latest('revision_number')->firstOrFail();
        $draftUrl = "/api/collaboration/resources/location/{$id}/draft";

        $this->actingAs($user)->putJson($draftUrl, [
            'base_revision_id' => $base->id,
            'snapshot' => ['metadata' => ['name' => 'Lab Draft', 'description' => 'Safe']],
        ])->assertOk();
        $before = ResourceRevision::count();
        $this->actingAs($user)->postJson("{$draftUrl}/apply", ['force' => true])
            ->assertUnprocessable();
        $this->assertSame($before, ResourceRevision::count());

        $this->actingAs($user)->postJson("{$draftUrl}/apply")
            ->assertOk()->assertJsonPath('draftCleared', true);
        $this->assertSame($before + 1, ResourceRevision::count());
        $this->assertDatabaseMissing('resource_drafts', ['resource_type' => 'location', 'resource_id' => $id, 'user_id' => $user->id]);

        $current = ResourceRevision::where(['resource_type' => 'location', 'resource_id' => $id])
            ->latest('revision_number')->firstOrFail();
        $this->actingAs($user)->putJson($draftUrl, [
            'base_revision_id' => $current->id,
            'snapshot' => ['metadata' => ['name' => 'Stale Draft', 'description' => null]],
        ])->assertOk();
        $this->actingAs($user)->patchJson("/api/locations/{$id}", ['name' => 'New Canonical'])
            ->assertOk();
        $this->assertDatabaseMissing('resource_drafts', [
            'resource_type' => 'location',
            'resource_id' => $id,
            'user_id' => $user->id,
        ]);
        $this->actingAs($user)->putJson($draftUrl, [
            'base_revision_id' => $current->id,
            'snapshot' => ['metadata' => ['name' => 'Stale Draft', 'description' => null]],
        ])->assertOk();
        $this->actingAs($user)->postJson("{$draftUrl}/apply")->assertConflict();
        $this->assertDatabaseHas('resource_drafts', ['resource_type' => 'location', 'resource_id' => $id, 'user_id' => $user->id]);
        $this->assertSame('New Canonical', \App\Models\Location::findOrFail($id)->name);
    }
}
