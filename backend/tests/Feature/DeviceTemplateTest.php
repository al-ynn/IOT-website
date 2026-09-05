<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\DeviceTemplate;
use App\Models\Organization;
use App\Models\TelemetryRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $o, string $role = 'owner', bool $admin = false): User
    {
        return User::factory()->create(['organization_id' => $o->id, 'role' => $role, 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']);
    }

    private function device(Organization $o, string $name): Device
    {
        return $o->devices()->create(['name' => $name, 'external_id' => str($name)->slug().uniqid(), 'type' => 'sensor', 'protocol' => 'mqtt']);
    }

    private function assign(User $u, Device $d, string $level = 'full_access'): void
    {
        DeviceAccessAssignment::create(['user_id' => $u->id, 'device_id' => $d->id, 'access_level' => $level]);
    }

    private function template(Organization $o, User $u, string $name = 'Sensor Schema'): DeviceTemplate
    {
        return $o->deviceTemplates()->create(['name' => $name, 'description' => 'Reusable schema', 'device_type' => 'sensor', 'protocol' => 'mqtt', 'created_by' => $u->id]);
    }

    private function parameter(string $key = 'temperature'): array
    {
        return ['name' => 'Temperature', 'key' => $key, 'data_type' => 'number', 'unit' => 'C', 'description' => 'Ambient temperature'];
    }

    public function test_template_crud_is_authenticated_permission_gated_and_organization_scoped(): void
    {
        $a = Organization::create(['name' => 'A', 'slug' => 'template-a']);
        $b = Organization::create(['name' => 'B', 'slug' => 'template-b']);
        $owner = $this->user($a);
        $staff = $this->user($a, 'staff');
        $foreign = $this->user($b);
        $this->getJson('/api/device-templates')->assertUnauthorized();
        $this->actingAs($staff)->getJson('/api/device-templates')->assertOk();
        $this->actingAs($staff)->postJson('/api/device-templates', ['name' => 'Blocked'])->assertForbidden();
        $id = $this->actingAs($owner)->postJson('/api/device-templates', ['name' => 'Pump', 'organization_id' => $b->id, 'created_by' => $foreign->id])->assertCreated()->assertJsonPath('data.organization.id', (string) $a->id)->json('data.id');
        $this->actingAs($owner)->patchJson("/api/device-templates/$id", ['name' => 'Pump v2', 'organization_id' => $b->id])->assertOk()->assertJsonPath('data.name', 'Pump v2');
        $this->actingAs($foreign)->getJson("/api/device-templates/$id")->assertNotFound();
        $this->actingAs($foreign)->patchJson("/api/device-templates/$id", ['name' => 'Attack'])->assertNotFound();
        $this->actingAs($owner)->deleteJson("/api/device-templates/$id")->assertStatus(405);
        $this->assertDatabaseHas('device_templates', ['id' => $id]);
    }

    public function test_parameter_definitions_validate_keys_types_uniqueness_and_nested_ownership(): void
    {
        $o = Organization::create(['name' => 'Org', 'slug' => 'template-parameters']);
        $owner = $this->user($o);
        $one = $this->template($o, $owner, 'One');
        $two = $this->template($o, $owner, 'Two');
        $id = $this->actingAs($owner)->postJson("/api/device-templates/{$one->id}/parameters", $this->parameter())->assertOk()->json('data.parameters.0.id');
        $this->actingAs($owner)->postJson("/api/device-templates/{$one->id}/parameters", $this->parameter())->assertUnprocessable();
        $this->actingAs($owner)->postJson("/api/device-templates/{$two->id}/parameters", $this->parameter())->assertOk();
        foreach (['Bad Key', '9wrong', 'door-open'] as $key) {
            $this->actingAs($owner)->postJson("/api/device-templates/{$one->id}/parameters", $this->parameter($key))->assertUnprocessable();
        }
        $this->actingAs($owner)->postJson("/api/device-templates/{$one->id}/parameters", [...$this->parameter('valid_integer'), 'data_type' => 'integer'])->assertOk();
        foreach (['float', 'json'] as $type) {
            $this->actingAs($owner)->postJson("/api/device-templates/{$one->id}/parameters", [...$this->parameter('valid_'.uniqid()), 'data_type' => $type])->assertUnprocessable();
        }$this->actingAs($owner)->patchJson("/api/device-templates/{$two->id}/parameters/$id", ['name' => 'Attack'])->assertNotFound();
        $this->actingAs($owner)->patchJson("/api/device-templates/{$one->id}/parameters/$id", ['name' => 'Room Temperature', 'key' => 'room_temperature', 'data_type' => 'string', 'unit' => null])->assertOk()->assertJsonPath('data.parameters.0.key', 'temperature')->assertJsonPath('data.parameters.0.dataType', 'string');
    }

    public function test_application_is_transactional_copied_and_requires_full_device_access(): void
    {
        $o = Organization::create(['name' => 'Org', 'slug' => 'template-apply']);
        $owner = $this->user($o);
        $viewer = $this->user($o, 'staff');
        $none = $this->user($o);
        $template = $this->template($o, $owner);
        $template->parameters()->create($this->parameter());
        $template->parameters()->create([...$this->parameter('humidity'), 'name' => 'Humidity']);
        $device = $this->device($o, 'Pump');
        $this->assign($owner, $device);
        $this->assign($viewer, $device, 'viewer');
        $this->actingAs($viewer)->postJson("/api/devices/{$device->id}/apply-template", ['template_id' => $template->id])->assertForbidden();
        $this->actingAs($none)->postJson("/api/devices/{$device->id}/apply-template", ['template_id' => $template->id])->assertNotFound();
        $this->actingAs($owner)->postJson("/api/devices/{$device->id}/apply-template", ['template_id' => $template->id])->assertOk();
        $this->assertDatabaseHas('device_parameters', ['device_id' => $device->id, 'key' => 'temperature']);
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'device_template_id' => $template->id]);
        $template->parameters()->where('key', 'temperature')->update(['name' => 'Changed later']);
        $this->assertDatabaseHas('device_parameters', ['device_id' => $device->id, 'key' => 'temperature', 'name' => 'Temperature']);
        $this->actingAs($owner)->postJson("/api/devices/{$device->id}/apply-template", ['template_id' => $template->id])->assertUnprocessable();
        $this->assertSame(2, $device->parameters()->count());
    }

    public function test_any_key_collision_rejects_all_writes_and_cross_org_template_is_rejected(): void
    {
        $a = Organization::create(['name' => 'A', 'slug' => 'collision-a']);
        $b = Organization::create(['name' => 'B', 'slug' => 'collision-b']);
        $owner = $this->user($a);
        $other = $this->user($b);
        $device = $this->device($a, 'Pump');
        $this->assign($owner, $device);
        $template = $this->template($a, $owner);
        $template->parameters()->create($this->parameter());
        $template->parameters()->create([...$this->parameter('humidity'), 'name' => 'Humidity']);
        $device->parameters()->create($this->parameter());
        $this->actingAs($owner)->postJson("/api/devices/{$device->id}/apply-template", ['template_id' => $template->id])->assertUnprocessable();
        $this->assertDatabaseMissing('device_parameters', ['device_id' => $device->id, 'key' => 'humidity']);
        $foreign = $this->template($b, $other);
        $this->actingAs($owner)->postJson("/api/devices/{$device->id}/apply-template", ['template_id' => $foreign->id])->assertNotFound();
    }

    public function test_duplicate_and_delete_preserve_instantiated_device_data_and_telemetry(): void
    {
        $o = Organization::create(['name' => 'Org', 'slug' => 'template-lifecycle']);
        $owner = $this->user($o);
        $template = $this->template($o, $owner);
        $template->parameters()->create($this->parameter());
        $copyId = $this->actingAs($owner)->postJson("/api/device-templates/{$template->id}/duplicate")->assertCreated()->assertJsonPath('data.parameterCount', 1)->json('data.id');
        $this->assertDatabaseHas('device_templates', ['id' => $copyId, 'name' => 'Copy of Sensor Schema']);
        $device = $this->device($o, 'Pump');
        $this->assign($owner, $device);
        $this->actingAs($owner)->postJson("/api/devices/{$device->id}/apply-template", ['template_id' => $template->id])->assertOk();
        $telemetry = TelemetryRecord::create(['device_id' => $device->id, 'key' => 'temperature', 'value' => 22, 'unit' => 'C', 'recorded_at' => now()]);
        $this->actingAs($owner)->deleteJson("/api/device-templates/{$template->id}")->assertStatus(405);
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'device_template_id' => $template->id]);
        $this->assertDatabaseHas('device_parameters', ['device_id' => $device->id, 'key' => 'temperature']);
        $this->assertDatabaseHas('telemetry_records', ['id' => $telemetry->id]);
    }

    public function test_admin_template_boundary_is_current_organization(): void
    {
        $a = Organization::create(['name' => 'A', 'slug' => 'admin-template-a']);
        $b = Organization::create(['name' => 'B', 'slug' => 'admin-template-b']);
        $admin = $this->user($a, 'staff', true);
        $staff = $this->user($b, 'staff');
        $this->actingAs($staff)->getJson('/api/admin/device-templates')->assertForbidden();
        $this->actingAs($admin)->postJson('/api/admin/device-templates', ['organization_id' => $b->id, 'name' => 'Foreign Schema'])->assertNotFound();
        $id = $this->actingAs($admin)->postJson('/api/admin/device-templates', ['organization_id' => $a->id, 'name' => 'Organization Schema'])->assertCreated()->assertJsonPath('data.organization.id', (string) $a->id)->json('data.id');
        $this->actingAs($admin)->getJson("/api/admin/device-templates/$id")->assertOk();
    }
}
