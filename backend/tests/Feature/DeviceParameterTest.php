<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\Organization;
use App\Models\TelemetryRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceParameterTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $o, bool $admin = false): User
    {
        return User::factory()->create(['organization_id' => $o->id, 'role' => 'staff', 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']);
    }

    private function device(Organization $o, string $name): Device
    {
        return $o->devices()->create(['name' => $name, 'external_id' => str($name)->slug().uniqid(), 'type' => 'sensor', 'protocol' => 'mqtt']);
    }

    private function assign(User $u, Device $d, string $level): void
    {
        DeviceAccessAssignment::create(['user_id' => $u->id, 'device_id' => $d->id, 'access_level' => $level]);
    }

    private function payload(string $key = 'temperature'): array
    {
        return ['name' => 'Temperature', 'key' => $key, 'data_type' => 'number', 'unit' => 'C', 'description' => 'Ambient temperature'];
    }

    public function test_staff_parameter_authorization_matches_viewer_and_full_access(): void
    {
        $o = Organization::create(['name' => 'Org', 'slug' => 'parameter-auth']);
        $none = $this->user($o);
        $viewer = $this->user($o);
        $full = $this->user($o);
        $device = $this->device($o, 'Pump');
        $this->assign($viewer, $device, 'viewer');
        $this->assign($full, $device, 'full_access');
        $parameter = $device->parameters()->create($this->payload());
        $this->getJson("/api/devices/{$device->id}/parameters")->assertUnauthorized();
        $this->actingAs($none)->getJson("/api/devices/{$device->id}/parameters")->assertNotFound();
        $this->actingAs($viewer)->getJson("/api/devices/{$device->id}/parameters")->assertOk()->assertJsonPath('data.0.key', 'temperature');
        $this->actingAs($viewer)->postJson("/api/devices/{$device->id}/parameters", $this->payload('humidity'))->assertForbidden();
        $this->actingAs($viewer)->patchJson("/api/devices/{$device->id}/parameters/{$parameter->id}", ['name' => 'Changed'])->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/api/devices/{$device->id}/parameters/{$parameter->id}")->assertForbidden();
        $created = $this->actingAs($full)->postJson("/api/devices/{$device->id}/parameters", $this->payload('humidity'))->assertCreated();
        $this->actingAs($full)->patchJson("/api/devices/{$device->id}/parameters/{$created->json('data.id')}", ['name' => 'Humidity'])->assertOk()->assertJsonPath('data.name', 'Humidity');
        $this->actingAs($full)->deleteJson("/api/devices/{$device->id}/parameters/{$created->json('data.id')}")->assertNoContent();
    }

    public function test_admin_parameter_crud_is_current_organization_and_staff_cannot_use_it(): void
    {
        $a = Organization::create(['name' => 'A', 'slug' => 'parameter-a']);
        $b = Organization::create(['name' => 'B', 'slug' => 'parameter-b']);
        $admin = $this->user($a, true);
        $staff = $this->user($b);
        $foreign = $this->device($b, 'Foreign');
        $device = $this->device($a, 'Local');
        $this->actingAs($staff)->getJson("/api/admin/devices/{$foreign->id}/parameters")->assertForbidden();
        $this->actingAs($admin)->postJson("/api/admin/devices/{$foreign->id}/parameters", $this->payload())->assertNotFound();
        $id = $this->actingAs($admin)->postJson("/api/admin/devices/{$device->id}/parameters", $this->payload())->assertCreated()->json('data.id');
        $this->actingAs($admin)->getJson("/api/admin/devices/{$device->id}/parameters")->assertOk()->assertJsonPath('data.0.id', $id);
        $this->actingAs($admin)->patchJson("/api/admin/devices/{$device->id}/parameters/$id", ['unit' => '°C'])->assertOk()->assertJsonPath('data.unit', '°C');
        $this->actingAs($admin)->deleteJson("/api/admin/devices/{$device->id}/parameters/$id")->assertNoContent();
    }

    public function test_nested_ownership_tenancy_and_same_key_rules_are_enforced(): void
    {
        $a = Organization::create(['name' => 'A', 'slug' => 'nested-a']);
        $b = Organization::create(['name' => 'B', 'slug' => 'nested-b']);
        $staff = $this->user($a);
        $one = $this->device($a, 'One');
        $two = $this->device($a, 'Two');
        $foreign = $this->device($b, 'Foreign');
        $this->assign($staff, $one, 'full_access');
        $this->assign($staff, $foreign, 'full_access');
        $parameter = $two->parameters()->create($this->payload());
        $this->actingAs($staff)->patchJson("/api/devices/{$one->id}/parameters/{$parameter->id}", ['name' => 'Attack'])->assertNotFound();
        $this->actingAs($staff)->getJson("/api/devices/{$foreign->id}/parameters")->assertNotFound();
        $this->actingAs($staff)->postJson("/api/devices/{$one->id}/parameters", $this->payload())->assertCreated();
        $this->actingAs($staff)->postJson("/api/devices/{$one->id}/parameters", $this->payload())->assertUnprocessable();
        $this->actingAs($staff)->postJson("/api/devices/{$two->id}/parameters", $this->payload())->assertNotFound();
        $this->assertDatabaseHas('device_parameters', ['device_id' => $two->id, 'key' => 'temperature']);
    }

    public function test_validation_allowlist_and_immutability_are_explicit(): void
    {
        $o = Organization::create(['name' => 'Org', 'slug' => 'parameter-validation']);
        $staff = $this->user($o);
        $device = $this->device($o, 'Pump');
        $this->assign($staff, $device, 'full_access');
        foreach (['Bad Key', '../secret', '9starts_wrong', 'door-open'] as $key) {
            $this->actingAs($staff)->postJson("/api/devices/{$device->id}/parameters", $this->payload($key))->assertUnprocessable();
        }
        $this->actingAs($staff)->postJson("/api/devices/{$device->id}/parameters", [...$this->payload('integer_value'), 'data_type' => 'integer'])->assertCreated();
        foreach (['float', 'bool', 'json'] as $type) {
            $this->actingAs($staff)->postJson("/api/devices/{$device->id}/parameters", [...$this->payload(uniqid('key_')), 'data_type' => $type])->assertUnprocessable();
        }$response = $this->actingAs($staff)->postJson("/api/devices/{$device->id}/parameters", [...$this->payload(), 'device_id' => 999, 'organization_id' => 999, 'user_id' => 999, 'access_level' => 'full_access'])->assertCreated();
        $id = $response->json('data.id');
        $this->actingAs($staff)->patchJson("/api/devices/{$device->id}/parameters/$id", ['name' => 'Room Temperature', 'unit' => 'K', 'description' => 'Updated', 'key' => 'temp', 'data_type' => 'boolean', 'device_id' => 999])->assertOk()->assertJsonPath('data.key', 'temperature')->assertJsonPath('data.dataType', 'number')->assertJsonPath('data.name', 'Room Temperature');
        $this->assertDatabaseHas('device_parameters', ['id' => $id, 'device_id' => $device->id, 'key' => 'temperature', 'data_type' => 'number']);
        $this->actingAs($staff)->postJson("/api/devices/{$device->id}/parameters", [...$this->payload('long_name'), 'name' => str_repeat('x', 101)])->assertUnprocessable();
        $this->actingAs($staff)->postJson("/api/devices/{$device->id}/parameters", [...$this->payload('long_unit'), 'unit' => str_repeat('x', 31)])->assertUnprocessable();
        $this->actingAs($staff)->postJson("/api/devices/{$device->id}/parameters", [...$this->payload('long_description'), 'description' => str_repeat('x', 501)])->assertUnprocessable();
    }

    public function test_definition_deletion_preserves_telemetry_and_device_deletion_cascades_definitions(): void
    {
        $o = Organization::create(['name' => 'Org', 'slug' => 'parameter-delete']);
        $staff = $this->user($o);
        $device = $this->device($o, 'Pump');
        $this->assign($staff, $device, 'full_access');
        $parameter = $device->parameters()->create($this->payload());
        $telemetry = TelemetryRecord::create(['device_id' => $device->id, 'key' => 'temperature', 'value' => 22.5, 'unit' => 'C', 'recorded_at' => now()]);
        $this->actingAs($staff)->deleteJson("/api/devices/{$device->id}/parameters/{$parameter->id}")->assertNoContent();
        $this->assertDatabaseHas('devices', ['id' => $device->id]);
        $this->assertDatabaseHas('telemetry_records',['id' => $telemetry->id]);
        $recreated = $device->parameters()->create($this->payload());
        $device->delete();
        $this->assertDatabaseMissing('device_parameters',['id' => $recreated->id]);
    }
}
