<?php

namespace Tests\Feature;

use App\Models\AutomationExecution;
use App\Models\DeviceAccessAssignment;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\ResourceRevision;
use App\Models\User;
use App\Services\Automation\AutomationDefinitionService;
use App\Services\Automation\AutomationExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationPlatformTest extends TestCase
{
    use RefreshDatabase;

    private function context(string $role = 'owner'): array
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org-'.uniqid()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => $role]);

        return [$organization, $user];
    }

    private function definition(array $overrides = []): array
    {
        return array_replace_recursive(['name' => 'High temperature', 'enabled' => true, 'trigger' => ['type' => 'telemetry', 'field' => 'temperature'], 'conditions' => ['logic' => 'AND', 'conditions' => [['field' => 'temperature', 'operator' => '>', 'value' => 80]]], 'actions' => [['type' => 'notification', 'target' => 'organization', 'payload' => ['message' => 'Too hot']]]], $overrides);
    }

    public function test_crud_and_execution_have_no_commercial_dependency(): void
    {
        [$organization,$user] = $this->context();
        $created = $this->actingAs($user)->postJson('/api/automations', $this->definition())->assertCreated();
        $id = $created->json('id');
        $this->actingAs($user)->getJson('/api/automations')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($user)->putJson("/api/automations/$id", ['name' => 'Renamed', 'baseRevisionId' => $created->json('baseRevisionId')])->assertOk();
        $this->actingAs($user)->postJson("/api/automations/$id/execute", ['data' => ['temperature' => 100]])->assertOk()->assertJsonPath('status', 'completed');
        $this->assertSame(1, Notification::where('organization_id', $organization->id)->count());
    }

    public function test_advanced_definition_is_available_without_entitlement(): void
    {
        [, $user] = $this->context();
        $this->actingAs($user)->postJson('/api/automations', $this->definition(['conditions' => ['logic' => 'OR'], 'actions' => [['type' => 'notification', 'payload' => ['message' => 'One']], ['type' => 'notification', 'payload' => ['message' => 'Two']]]]))->assertCreated();
    }

    public function test_viewer_role_cannot_create_automation(): void
    {
        [, $viewer] = $this->context('viewer');
        $this->actingAs($viewer)->postJson('/api/automations', $this->definition())->assertForbidden();
    }

    public function test_full_access_device_can_be_targeted_without_commercial_state(): void
    {
        [$organization,$user] = $this->context();
        $device = $organization->devices()->create(['name' => 'Sensor', 'external_id' => 'sensor', 'type' => 'sensor', 'protocol' => 'mqtt']);
        DeviceAccessAssignment::create(['device_id' => $device->id, 'user_id' => $user->id, 'access_level' => 'full_access']);
        $this->actingAs($user)->postJson('/api/automations', $this->definition(['trigger' => ['type' => 'telemetry', 'deviceId' => $device->id, 'field' => 'temperature']]))->assertCreated();
    }

    public function test_viewer_and_unassigned_device_targets_are_denied(): void
    {
        [$organization,$user] = $this->context();
        $viewer = $organization->devices()->create(['name' => 'Viewer', 'external_id' => 'viewer', 'type' => 'sensor', 'protocol' => 'mqtt']);
        $hidden = $organization->devices()->create(['name' => 'Hidden', 'external_id' => 'hidden', 'type' => 'sensor', 'protocol' => 'mqtt']);
        DeviceAccessAssignment::create(['device_id' => $viewer->id, 'user_id' => $user->id, 'access_level' => 'viewer']);
        $this->actingAs($user)->postJson('/api/automations', $this->definition(['trigger' => ['deviceId' => $viewer->id]]))->assertForbidden();
        $this->actingAs($user)->postJson('/api/automations', $this->definition(['trigger' => ['deviceId' => $hidden->id]]))->assertNotFound();
    }

    public function test_execution_is_idempotent(): void
    {
        [$organization,$user] = $this->context();
        $automation = app(AutomationDefinitionService::class)->create($organization, $user, $this->definition());
        $service = app(AutomationExecutionService::class);
        $one = $service->execute($automation, 'manual', ['temperature' => 100], '11111111-1111-4111-8111-111111111111');
        $two = $service->execute($automation, 'manual', ['temperature' => 100], '11111111-1111-4111-8111-111111111111');
        $this->assertSame($one->id, $two->id);
        $this->assertSame(1, AutomationExecution::count());
    }

    public function test_organization_isolation_hides_automations(): void
    {
        [$a,$userA] = $this->context();
        [$b,$userB] = $this->context();
        $automation = app(AutomationDefinitionService::class)->create($b, $userB, $this->definition());
        $this->actingAs($userA)->getJson("/api/automations/$automation->id")->assertNotFound();
        $this->assertSame(0, $a->automations()->count());
    }

    public function test_scheduled_execution_remains_system_authority(): void
    {
        [$organization,$user] = $this->context();
        $automation = app(AutomationDefinitionService::class)->create($organization, $user, $this->definition(['trigger' => ['type' => 'schedule'], 'conditions' => ['conditions' => []], 'schedule' => ['type' => 'interval', 'intervalMinutes' => 5, 'enabled' => true]]));
        $automation->schedules()->first()->update(['next_run_at' => now()->subMinute()]);
        $this->artisan('automation:run-schedules')->assertSuccessful();
        $this->assertSame(1, $automation->executions()->count());
    }

    public function test_authentication_cross_org_device_and_injected_authority_are_rejected(): void
    {
        [$organization,$user] = $this->context();
        [$foreign,$foreignUser] = $this->context();
        $device = $foreign->devices()->create(['name' => 'Foreign', 'external_id' => 'foreign-'.uniqid(), 'type' => 'sensor', 'protocol' => 'mqtt']);
        $this->getJson('/api/automations')->assertUnauthorized();
        $this->postJson('/api/automations', $this->definition())->assertUnauthorized();
        $this->actingAs($user)->postJson('/api/automations', $this->definition(['trigger' => ['deviceId' => $device->id], 'organization_id' => $foreign->id, 'platform_role' => 'platform_admin', 'access_level' => 'full_access']))->assertUnprocessable();
        $created = $this->actingAs($user)->postJson('/api/automations', [...$this->definition(), 'organization_id' => $foreign->id, 'platform_role' => 'platform_admin', 'access_level' => 'full_access'])->assertCreated();
        $this->assertDatabaseHas('automations', ['id' => $created->json('id'), 'organization_id' => $organization->id]);
        $this->assertDatabaseMissing('automations', ['organization_id' => $foreign->id, 'created_by' => $user->id]);
    }

    public function test_assignment_downgrade_blocks_configuration_but_not_system_runtime(): void
    {
        [$organization,$user] = $this->context();
        $device = $organization->devices()->create(['name' => 'Pump', 'external_id' => 'pump-'.uniqid(), 'type' => 'sensor', 'protocol' => 'mqtt']);
        $assignment = DeviceAccessAssignment::create(['device_id' => $device->id, 'user_id' => $user->id, 'access_level' => 'full_access']);
        $created = $this->actingAs($user)->postJson('/api/automations', $this->definition(['trigger' => ['type' => 'telemetry', 'deviceId' => $device->id, 'field' => 'temperature']]))->assertCreated();
        $assignment->update(['access_level' => 'viewer']);
        $this->actingAs($user)->patchJson('/api/automations/'.$created->json('id'), ['name' => 'Forbidden'])->assertForbidden();
        $automation = $organization->automations()->findOrFail($created->json('id'));
        app(AutomationExecutionService::class)->execute($automation, 'telemetry', ['temperature' => 100]);
        $this->assertSame(1, $automation->executions()->count());
        $assignment->delete();
        $this->actingAs($user)->postJson('/api/automations/'.$automation->id.'/enable')->assertNotFound();
    }

    public function test_parameter_keys_remain_backward_compatible_and_device_deletion_is_safe(): void
    {
        [$organization,$user] = $this->context();
        $device = $organization->devices()->create(['name' => 'Sensor', 'external_id' => 'parameter-'.uniqid(), 'type' => 'sensor', 'protocol' => 'mqtt']);
        DeviceAccessAssignment::create(['device_id' => $device->id, 'user_id' => $user->id, 'access_level' => 'full_access']);
        $device->parameters()->create(['name' => 'Temperature', 'key' => 'temperature', 'data_type' => 'number']);
        $known = $this->actingAs($user)->postJson('/api/automations', $this->definition(['name' => 'Known', 'trigger' => ['deviceId' => $device->id, 'field' => 'temperature']]))->assertCreated();
        $this->actingAs($user)->postJson('/api/automations', $this->definition(['name' => 'Legacy raw', 'trigger' => ['deviceId' => $device->id, 'field' => 'legacy_metric']]))->assertCreated();
        $device->delete();
        $this->actingAs($user)->getJson('/api/automations/'.$known->json('id'))->assertOk()->assertJsonPath('triggerDevice.available', false)->assertJsonPath('triggerDevice.name', null);
    }

    public function test_history_is_real_scoped_and_paginated(): void
    {
        [$organization,$user] = $this->context();
        $automation = app(AutomationDefinitionService::class)->create($organization, $user, $this->definition());
        for ($i = 0; $i < 3; $i++) {
            app(AutomationExecutionService::class)->execute($automation, 'manual', ['temperature' => 100]);
        }$this->actingAs($user)->getJson("/api/automations/{$automation->id}/executions?perPage=2")->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('total', 3);
        [, $foreign] = $this->context();
        $this->actingAs($foreign)->getJson("/api/automations/{$automation->id}/executions")->assertNotFound();
    }

    public function test_update_uses_validated_payload_and_rejects_a_stale_base_before_mutation(): void
    {
        [$organization,$user] = $this->context();
        $created = $this->actingAs($user)->postJson('/api/automations', $this->definition())->assertCreated();
        $id = $created->json('id');
        $base = $created->json('baseRevisionId');
        $updated = $this->actingAs($user)->putJson("/api/automations/$id", ['name' => 'Safe name', 'baseRevisionId' => $base, 'organization_id' => 999999, 'created_by' => 999999, 'version' => 999999])->assertOk();
        $this->assertNotSame($base, $updated->json('baseRevisionId'));
        $this->assertDatabaseHas('automations', ['id' => $id, 'name' => 'Safe name', 'organization_id' => $organization->id, 'created_by' => $user->id]);
        $this->actingAs($user)->putJson("/api/automations/$id", ['name' => 'Stale overwrite', 'baseRevisionId' => $base])->assertStatus(409);
        $this->assertDatabaseHas('automations', ['id' => $id, 'name' => 'Safe name']);
        $this->assertDatabaseMissing('automations', ['id' => $id, 'name' => 'Stale overwrite']);
    }

    public function test_definition_update_replays_same_committed_revision_after_response_loss(): void
    {
        [, $user] = $this->context();
        $created = $this->actingAs($user)->postJson('/api/automations', $this->definition())->assertCreated();
        $id = $created->json('id');
        $base = $created->json('baseRevisionId');
        $headers = ['Idempotency-Key' => '44444444-4444-4444-8444-444444444444'];
        $first = $this->actingAs($user)->putJson("/api/automations/$id", ['name' => 'Idempotent', 'baseRevisionId' => $base], $headers)->assertOk()->assertJsonPath('saveOutcome.replayed', false);
        $committed = $first->json('saveOutcome.committedRevisionId');
        $this->actingAs($user)->putJson("/api/automations/$id", ['name' => 'Idempotent', 'baseRevisionId' => $base], $headers)->assertOk()->assertJsonPath('saveOutcome.replayed',true)->assertJsonPath('saveOutcome.committedRevisionId',$committed);
        $this->assertSame(2,ResourceRevision::where(['resource_type' => 'automation', 'resource_id' => $id])->count());
    }
}
