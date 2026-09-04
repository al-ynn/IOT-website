<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\Organization;
use App\Models\ResourceRevision;
use App\Models\TelemetryRecord;
use App\Models\User;
use App\Services\ResourceRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $organization = Organization::create(['name' => 'Organization A', 'slug' => 'organization-a']);
        $staff = User::factory()->create(['organization_id' => $organization->id, 'role' => 'owner', 'platform_role' => null, 'status' => 'active']);
        return [$organization, $staff];
    }

    private function device(Organization $organization, string $name): Device
    {
        return $organization->devices()->create(['name' => $name, 'external_id' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(), 'type' => 'sensor', 'protocol' => 'mqtt', 'status' => 'online']);
    }

    private function assign(User $user, Device $device, string $level): void
    {
        DeviceAccessAssignment::create(['device_id' => $device->id, 'user_id' => $user->id, 'access_level' => $level]);
    }

    public function test_list_and_detail_only_expose_assigned_devices(): void
    {
        [$organization, $staff] = $this->context();
        $viewer = $this->device($organization, 'Viewer Device'); $this->assign($staff, $viewer, 'viewer');
        $full = $this->device($organization, 'Full Device'); $this->assign($staff, $full, 'full_access');
        $hidden = $this->device($organization, 'Hidden Device');

        $this->actingAs($staff)->getJson('/api/devices')->assertOk()->assertJsonCount(2)->assertJsonFragment(['name' => 'Viewer Device'])->assertJsonFragment(['name' => 'Full Device'])->assertJsonMissing(['name' => 'Hidden Device']);
        $this->actingAs($staff)->getJson("/api/devices/{$viewer->id}")->assertOk()->assertJsonPath('access.level', 'viewer')->assertJsonPath('access.canManage', false);
        $this->actingAs($staff)->getJson("/api/devices/{$hidden->id}")->assertNotFound();
    }

    public function test_viewer_is_read_only_and_full_access_can_update_and_delete(): void
    {
        [$organization, $staff] = $this->context();
        $viewer = $this->device($organization, 'Viewer'); $this->assign($staff, $viewer, 'viewer');
        $full = $this->device($organization, 'Full'); $this->assign($staff, $full, 'full_access');
        $this->actingAs($staff)->patchJson("/api/devices/{$viewer->id}", ['name' => 'Tampered', 'organization_id' => 999, 'platform_role' => 'platform_admin'])->assertForbidden();
        $this->actingAs($staff)->deleteJson("/api/devices/{$viewer->id}")->assertForbidden();
        $this->actingAs($staff)->patchJson("/api/devices/{$full->id}", ['name' => 'Updated', 'organization_id' => 999, 'access_level' => 'viewer'])->assertOk()->assertJsonPath('name', 'Updated');
        $this->assertSame($organization->id, $full->refresh()->organization_id);
        $this->assertDatabaseHas('device_access_assignments', ['device_id' => $full->id, 'access_level' => 'full_access']);
        $this->actingAs($staff)->deleteJson("/api/devices/{$full->id}")->assertStatus(405);
        $this->assertDatabaseHas('devices', ['id' => $full->id]);
    }

    public function test_telemetry_ingestion_requires_full_access(): void
    {
        [$organization, $staff] = $this->context();
        $viewer = $this->device($organization, 'Viewer'); $this->assign($staff, $viewer, 'viewer');
        $full = $this->device($organization, 'Full'); $this->assign($staff, $full, 'full_access');
        $payload = ['key' => 'temperature', 'value' => 10];
        $this->actingAs($staff)->postJson('/api/telemetry', [...$payload, 'device_id' => (string) $viewer->id])->assertForbidden();
        $this->actingAs($staff)->postJson('/api/telemetry', [...$payload, 'device_id' => (string) $full->id])->assertOk();
    }

    public function test_device_metadata_save_uses_base_revision_and_idempotency_contract(): void
    {
        [$organization, $staff] = $this->context();
        $device = $this->device($organization, 'Original');
        $this->assign($staff, $device, 'full_access');
        $base = app(ResourceRevisionService::class)->recordDevice($device, $staff, 'Baseline');
        $key = '44444444-4444-4444-8444-444444444444';
        $payload = ['name' => 'Saved once', 'baseRevisionId' => $base->id];

        $this->actingAs($staff)->withHeader('Idempotency-Key', $key)
            ->patchJson("/api/devices/{$device->id}", $payload)->assertOk()->assertJsonPath('name', 'Saved once');
        $this->actingAs($staff)->withHeader('Idempotency-Key', $key)
            ->patchJson("/api/devices/{$device->id}", $payload)->assertOk()->assertJsonPath('name', 'Saved once');

        $this->assertSame(2, ResourceRevision::where(['resource_type' => 'device', 'resource_id' => $device->id])->count());
        $this->assertDatabaseCount('resource_save_idempotencies', 1);
        $this->actingAs($staff)->withHeader('Idempotency-Key', '55555555-5555-4555-8555-555555555555')
            ->patchJson("/api/devices/{$device->id}", ['name' => 'Stale overwrite', 'baseRevisionId' => $base->id])
            ->assertConflict();
        $this->assertSame('Saved once', $device->refresh()->name);
    }

    public function test_analytics_aggregate_excludes_unassigned_device_data(): void
    {
        [$organization, $staff] = $this->context();
        $assigned = $this->device($organization, 'Assigned'); $this->assign($staff, $assigned, 'viewer');
        $hidden = $this->device($organization, 'Hidden');
        TelemetryRecord::create(['device_id' => $assigned->id, 'key' => 'power', 'value' => 10, 'recorded_at' => now()->subMinute()]);
        TelemetryRecord::create(['device_id' => $hidden->id, 'key' => 'power', 'value' => 1000, 'recorded_at' => now()->subMinute()]);
        $this->actingAs($staff)->getJson('/api/analytics/summary')->assertOk()->assertJsonPath('totalDevices', 1)->assertJsonPath('telemetryRecords', 1)->assertJsonPath('metrics.0.average', 10)->assertJsonPath('metrics.0.minimum', 10)->assertJsonPath('metrics.0.maximum', 10)->assertJsonPath('metrics.0.latest', 10);
        $this->actingAs($staff)->getJson("/api/analytics/devices/{$hidden->id}")->assertNotFound();
    }

    public function test_dashboard_redacts_saved_widgets_for_unassigned_devices(): void
    {
        [$organization, $staff] = $this->context();
        $assigned = $this->device($organization, 'Assigned'); $this->assign($staff, $assigned, 'viewer');
        $hidden = $this->device($organization, 'Hidden');
        $dashboard = $organization->dashboards()->create(['owner_user_id'=>$staff->id,'name' => 'Operations','scope_type'=>'personal']);
        $allowed=(string)\Illuminate\Support\Str::uuid();$hiddenId=(string)\Illuminate\Support\Str::uuid();
        $dashboard->widgets()->createMany([
            ['id'=>$allowed,'widget_type'=>'status','title'=>'Assigned','layout'=>['x'=>0,'y'=>0,'w'=>3,'h'=>3],'configuration'=>['deviceId'=>$assigned->id],'position'=>0],
            ['id'=>$hiddenId,'widget_type'=>'status','title'=>'Hidden','layout'=>['x'=>3,'y'=>0,'w'=>3,'h'=>3],'configuration'=>['deviceId'=>$hidden->id],'position'=>1],
        ]);
        $this->actingAs($staff)->getJson("/api/dashboards/{$dashboard->id}")->assertOk()->assertJsonCount(2, 'widgets')->assertJsonPath('widgets.0.id',$allowed)->assertJsonPath('widgets.0.available',true)->assertJsonPath('widgets.1.available',false)->assertJsonPath('widgets.1.settings.datasource',null);
    }

    public function test_full_device_access_does_not_grant_admin_routes(): void
    {
        [$organization, $staff] = $this->context(); $device = $this->device($organization, 'Full'); $this->assign($staff, $device, 'full_access');
        $this->actingAs($staff)->getJson('/api/admin/device-access')->assertForbidden();
        $this->actingAs($staff)->getJson('/api/admin/operations/overview')->assertForbidden();
    }

    public function test_automation_device_ids_are_authorized_on_the_backend(): void
    {
        [$organization, $staff] = $this->context();
        $viewer = $this->device($organization, 'Viewer'); $this->assign($staff, $viewer, 'viewer');
        $full = $this->device($organization, 'Full'); $this->assign($staff, $full, 'full_access');
        $hidden = $this->device($organization, 'Hidden');
        $definition = fn (Device $device, string $name) => ['name' => $name, 'enabled' => false, 'trigger' => ['type' => 'telemetry', 'deviceId' => $device->id, 'field' => 'temperature'], 'conditions' => ['logic' => 'AND', 'conditions' => []], 'actions' => [['type' => 'notification', 'payload' => ['message' => 'Alert']]]];
        $this->actingAs($staff)->postJson('/api/automations', $definition($viewer, 'Viewer target'))->assertForbidden();
        $this->actingAs($staff)->postJson('/api/automations', $definition($hidden, 'Hidden target'))->assertNotFound();
        $this->actingAs($staff)->postJson('/api/automations', $definition($full, 'Full target'))->assertCreated();
    }
}
