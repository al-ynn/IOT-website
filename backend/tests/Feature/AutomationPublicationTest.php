<?php

namespace Tests\Feature;

use App\Models\AutomationExecution;
use App\Models\Organization;
use App\Models\ResourcePublicationState;
use App\Models\ResourcePublicationVersion;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationPublicationTest extends TestCase
{
    use RefreshDatabase;

    private function definition(string $message = 'Version one'): array
    {
        return ['name' => 'Published rule', 'enabled' => false, 'trigger' => ['type' => 'manual', 'field' => 'temperature'], 'conditions' => ['logic' => 'AND', 'conditions' => []], 'actions' => [['type' => 'notification', 'target' => 'organization', 'payload' => ['message' => $message]]]];
    }

    private function setupActors(): array
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'automation-publication']);
        $staff = User::factory()->create(['organization_id' => $org->id, 'role' => 'owner']);
        $member = User::factory()->create(['organization_id' => $org->id, 'role' => 'viewer']);
        $admin = User::factory()->create(['organization_id' => $org->id, 'platform_role' => 'platform_admin', 'role' => 'owner']);

        return [$org, $staff, $member, $admin];
    }

    public function test_submission_claim_and_approval_publish_exact_revision_without_enabling(): void
    {
        [, $staff,, $admin] = $this->setupActors();
        $created = $this->actingAs($staff)->postJson('/api/automations', $this->definition())->assertCreated();
        $id = $created->json('id');
        $revision = ResourceRevision::where(['resource_type' => 'automation', 'resource_id' => $id])->firstOrFail();
        $submission = $this->actingAs($staff)->postJson("/api/automations/{$id}/publication-submissions", ['revision_id' => $revision->id])->assertCreated()->json('data.id');
        $this->actingAs($admin)->postJson("/api/admin/automation-publication-submissions/{$submission}/claim")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/automation-publication-submissions/{$submission}/approve")->assertOk();
        $state = ResourcePublicationState::where(['resource_type' => 'automation', 'resource_id' => $id])->firstOrFail();
        $this->assertSame($revision->id, $state->approved_revision_id);
        $this->assertSame(1, ResourcePublicationVersion::count());
        $this->assertFalse((bool) $created->json('enabled'));
        $this->assertDatabaseHas('automations', ['id' => $id, 'enabled' => false]);
    }

    public function test_published_catalog_uses_pinned_metadata_without_granting_private_access(): void
    {
        [, $staff,$member,$admin] = $this->setupActors();
        $id = $this->actingAs($staff)->postJson('/api/automations', $this->definition())->json('id');
        $submission = $this->actingAs($staff)->postJson("/api/automations/{$id}/publication-submissions")->json('data.id');
        $this->actingAs($admin)->postJson("/api/admin/automation-publication-submissions/{$submission}/claim");
        $this->actingAs($admin)->postJson("/api/admin/automation-publication-submissions/{$submission}/approve");
        $this->actingAs($staff)->patchJson("/api/automations/{$id}", ['name' => 'Private new name', 'baseRevisionId' => ResourceRevision::where(['resource_type' => 'automation', 'resource_id' => $id])->latest('revision_number')->value('id')])->assertOk();
        $this->actingAs($member)->getJson("/api/automations/{$id}")->assertNotFound();
        $this->actingAs($member)->getJson('/api/automations/published/catalog')->assertOk()->assertJsonPath('data.0.name', 'Published rule')->assertJsonPath('data.0.capabilities.canEdit', false)->assertJsonPath('data.0.capabilities.canExecute', false);
    }

    public function test_runtime_is_pinned_to_current_publication_revision(): void
    {
        [, $staff,, $admin] = $this->setupActors();
        $id = $this->actingAs($staff)->postJson('/api/automations', $this->definition())->json('id');
        $s = $this->actingAs($staff)->postJson("/api/automations/{$id}/publication-submissions")->json('data.id');
        $this->actingAs($admin)->postJson("/api/admin/automation-publication-submissions/{$s}/claim");
        $this->actingAs($admin)->postJson("/api/admin/automation-publication-submissions/{$s}/approve");
        $state = ResourcePublicationState::where(['resource_type' => 'automation', 'resource_id' => $id])->firstOrFail();
        $this->actingAs($staff)->patchJson("/api/automations/{$id}", ['actions' => [['type' => 'notification', 'target' => 'organization', 'payload' => ['message' => 'Private version two']]], 'baseRevisionId' => ResourceRevision::where(['resource_type' => 'automation', 'resource_id' => $id])->latest('revision_number')->value('id')])->assertOk();
        $this->actingAs($staff)->postJson("/api/automations/{$id}/enable")->assertOk();
        $this->actingAs($staff)->postJson("/api/automations/{$id}/execute")->assertOk();
        $run = AutomationExecution::latest('id')->firstOrFail();
        $this->assertSame($state->approved_revision_id, $run->automation_revision_id);
        $this->assertSame($state->current_publication_version_id, $run->publication_version_id);
    }

    public function test_staff_cannot_approve_and_approval_is_idempotent(): void
    {
        [, $staff,, $admin] = $this->setupActors();
        $id = $this->actingAs($staff)->postJson('/api/automations', $this->definition())->json('id');
        $s = $this->actingAs($staff)->postJson("/api/automations/{$id}/publication-submissions")->json('data.id');
        $this->actingAs($staff)->postJson("/api/admin/automation-publication-submissions/{$s}/approve")->assertForbidden();
        $this->actingAs($admin)->postJson("/api/admin/automation-publication-submissions/{$s}/claim")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/automation-publication-submissions/{$s}/approve")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/automation-publication-submissions/{$s}/approve")->assertUnprocessable();
        $this->assertSame(1,ResourcePublicationVersion::count());
    }
}
