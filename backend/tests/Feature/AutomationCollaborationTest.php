<?php

namespace Tests\Feature;

use App\Models\AutomationExecution;
use App\Models\DeviceAccessAssignment;
use App\Models\Organization;
use App\Models\ResourceCollaborator;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationCollaborationTest extends TestCase
{
    use RefreshDatabase;

    private function definition(?int $deviceId = null): array
    {
        return ['name' => 'Shared rule', 'enabled' => false, 'trigger' => ['type' => 'telemetry', 'deviceId' => $deviceId, 'field' => 'temperature'], 'conditions' => ['logic' => 'AND', 'conditions' => []], 'actions' => [['type' => 'notification', 'target' => 'organization', 'payload' => ['message' => 'Threshold reached']]]];
    }

    public function test_private_view_and_edit_grants_do_not_grant_device_access(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'automation-collaboration']);
        $creator = User::factory()->create(['organization_id' => $org->id, 'role' => 'owner']);
        $viewer = User::factory()->create(['organization_id' => $org->id, 'role' => 'owner']);
        $editor = User::factory()->create(['organization_id' => $org->id, 'role' => 'owner']);
        $device = $org->devices()->create(['name' => 'Restricted Pump', 'external_id' => 'restricted-pump', 'type' => 'sensor', 'protocol' => 'mqtt']);
        DeviceAccessAssignment::create(['device_id' => $device->id, 'user_id' => $creator->id, 'access_level' => 'full_access']);
        $id = $this->actingAs($creator)->postJson('/api/automations', $this->definition($device->id))->assertCreated()->json('id');
        $this->actingAs($viewer)->getJson("/api/automations/{$id}")->assertNotFound();
        ResourceCollaborator::create(['resource_type' => 'automation', 'resource_id' => $id, 'user_id' => $viewer->id, 'permission' => 'view', 'granted_by' => $creator->id]);
        ResourceCollaborator::create(['resource_type' => 'automation', 'resource_id' => $id, 'user_id' => $editor->id, 'permission' => 'edit', 'granted_by' => $creator->id]);
        $this->actingAs($viewer)->getJson("/api/automations/{$id}")->assertOk()->assertJsonPath('triggerDevice.name', null)->assertJsonPath('capabilities.canEdit', false);
        $this->actingAs($viewer)->patchJson("/api/automations/{$id}", ['name' => 'No'])->assertNotFound();
        $this->actingAs($editor)->patchJson("/api/automations/{$id}", ['name' => 'No target authority'])->assertNotFound();
        $this->assertDatabaseMissing('device_access_assignments', ['user_id' => $viewer->id, 'device_id' => $device->id]);
    }

    public function test_definition_revisions_exclude_runtime_state(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'automation-revisions']);
        $creator = User::factory()->create(['organization_id' => $org->id, 'role' => 'owner']);
        $id = $this->actingAs($creator)->postJson('/api/automations', $this->definition())->assertCreated()->json('id');
        $this->assertSame(1, ResourceRevision::where(['resource_type' => 'automation', 'resource_id' => $id])->count());
        $this->actingAs($creator)->postJson("/api/automations/{$id}/enable")->assertOk();
        $this->assertSame(1, ResourceRevision::where(['resource_type' => 'automation', 'resource_id' => $id])->count());
        $this->actingAs($creator)->patchJson("/api/automations/{$id}", ['description' => 'Meaningful definition update', 'baseRevisionId' => ResourceRevision::where(['resource_type' => 'automation', 'resource_id' => $id])->latest('revision_number')->value('id')])->assertOk();
        $latest = ResourceRevision::where(['resource_type' => 'automation', 'resource_id' => $id])->latest('revision_number')->firstOrFail();
        $this->assertSame(2, $latest->revision_number);
        $this->assertArrayNotHasKey('enabled', $latest->snapshot);
        $this->assertArrayNotHasKey('executions', $latest->snapshot);
    }

    public function test_runtime_history_requires_both_automation_and_current_device_access(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'automation-runtime-access']);
        $creator = User::factory()->create(['organization_id' => $org->id, 'role' => 'owner']);
        $viewer = User::factory()->create(['organization_id' => $org->id, 'role' => 'owner']);
        $device = $org->devices()->create(['name' => 'Runtime Pump', 'external_id' => 'runtime-pump', 'type' => 'sensor', 'protocol' => 'mqtt']);
        DeviceAccessAssignment::create(['device_id' => $device->id, 'user_id' => $creator->id, 'access_level' => 'full_access']);
        $id = $this->actingAs($creator)->postJson('/api/automations', $this->definition($device->id))->assertCreated()->json('id');
        $execution = AutomationExecution::create(['automation_id' => $id, 'organization_id' => $org->id, 'correlation_id' => '11111111-1111-4111-8111-111111111111', 'trigger_type' => 'telemetry', 'trigger_data' => ['temperature' => 88], 'status' => 'completed']);

        $this->actingAs($viewer)->getJson('/api/automation/logs')->assertOk()->assertJsonPath('total', 0);
        ResourceCollaborator::create(['resource_type' => 'automation', 'resource_id' => $id, 'user_id' => $viewer->id, 'permission' => 'view', 'granted_by' => $creator->id]);
        $this->actingAs($viewer)->getJson('/api/automation/logs')->assertOk()->assertJsonPath('total', 0);
        $this->actingAs($viewer)->getJson("/api/automations/{$id}/executions")->assertNotFound();

        $assignment = DeviceAccessAssignment::create(['device_id' => $device->id, 'user_id' => $viewer->id, 'access_level' => 'viewer']);
        $this->actingAs($viewer)->getJson('/api/automation/logs')->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($viewer)->getJson("/api/automation/logs/{$execution->id}")->assertOk();
        $this->actingAs($viewer)->getJson("/api/automations/{$id}/executions")->assertOk()->assertJsonPath('total', 1);

        $assignment->delete();
        $this->actingAs($viewer)->getJson("/api/automation/logs/{$execution->id}")->assertNotFound();
        $this->actingAs($viewer)->getJson("/api/automations/{$id}/executions")->assertNotFound();
    }

    public function test_automation_share_requires_acceptance_and_keeps_one_resource(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'automation-share']);
        $creator = User::factory()->create(['organization_id' => $org->id, 'role' => 'owner']);
        $recipient = User::factory()->create(['organization_id' => $org->id, 'role' => 'owner']);
        $id = $this->actingAs($creator)->postJson('/api/automations', $this->definition())->assertCreated()->json('id');
        $share = $this->actingAs($creator)->postJson('/api/shares', ['resource_type' => 'automation', 'resource_id' => $id, 'recipient_id' => $recipient->id, 'permission' => 'view'])->assertCreated()->json('data.id');
        $this->actingAs($recipient)->postJson("/api/shares/{$share}/accept")->assertOk();
        $this->actingAs($recipient)->getJson("/api/automations/{$id}")->assertOk();
        $this->assertDatabaseCount('automations', 1);
        $this->assertDatabaseHas('resource_collaborators', ['resource_type' => 'automation', 'resource_id' => $id, 'user_id' => $recipient->id, 'permission' => 'view']);
    }
}
