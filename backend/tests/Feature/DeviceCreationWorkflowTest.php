<?php

namespace Tests\Feature;

use App\Jobs\NotifyAdminsOfDeviceCreated;
use App\Models\Device;
use App\Models\DeviceTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeviceCreationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $organization, bool $admin = false): User
    {
        return User::factory()->create(['organization_id' => $organization->id, 'role' => 'staff', 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']);
    }

    private function payload(array $extra = []): array
    {
        return [...['name' => 'Cold Room Sensor', 'type' => 'sensor', 'serialNumber' => 'CR-001', 'protocol' => 'mqtt'], ...$extra];
    }

    public function test_staff_creation_is_immediate_attributed_and_assigns_full_access_atomically(): void
    {
        Queue::fake(); $org = Organization::create(['name' => 'Plant', 'slug' => 'plant']); $staff = $this->user($org);
        $response = $this->actingAs($staff)->postJson('/api/devices', $this->payload())->assertCreated()
            ->assertJsonPath('status', 'offline')->assertJsonPath('creator.id', (string) $staff->id)->assertJsonPath('access.level', 'full_access');
        $id = $response->json('id');
        $this->assertDatabaseHas('devices', ['id' => $id, 'organization_id' => $org->id, 'created_by' => $staff->id, 'status' => 'offline']);
        $this->assertDatabaseHas('device_access_assignments', ['device_id' => $id, 'user_id' => $staff->id, 'access_level' => 'full_access', 'assigned_by' => $staff->id]);
        $this->actingAs($staff)->getJson("/api/devices/{$id}")->assertOk();
        Queue::assertPushed(NotifyAdminsOfDeviceCreated::class);
    }

    public function test_staff_cannot_forge_server_authoritative_creation_fields(): void
    {
        $org = Organization::create(['name' => 'Plant', 'slug' => 'plant']); $staff = $this->user($org);
        foreach (['organization_id' => 999, 'created_by' => 999, 'status' => 'online', 'access_level' => 'viewer', 'assigned_by' => 999] as $field => $value) {
            $this->actingAs($staff)->postJson('/api/devices', $this->payload([$field => $value, 'serialNumber' => 'X-'.$field]))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('devices', 0);
    }

    public function test_admin_creation_is_limited_to_current_organization_without_assignment_or_awareness(): void
    {
        Queue::fake(); $home = Organization::create(['name' => 'HQ', 'slug' => 'hq']); $target = Organization::create(['name' => 'Plant', 'slug' => 'plant']); $admin = $this->user($home, true);
        $this->actingAs($admin)->postJson('/api/admin/devices', $this->payload(['organization_id' => $target->id]))->assertNotFound();
        $response = $this->actingAs($admin)->postJson('/api/admin/devices', $this->payload(['organization_id' => $home->id, 'serialNumber' => 'HQ-001']))->assertCreated()
            ->assertJsonPath('data.device.organization.id', (string) $home->id)->assertJsonPath('data.device.creator.id', (string) $admin->id);
        $id = $response->json('data.device.id');
        $this->assertDatabaseMissing('device_access_assignments', ['device_id' => $id, 'user_id' => $admin->id]);
        Queue::assertNothingPushed();
    }

    public function test_template_initialization_copies_parameters_and_preserves_provenance(): void
    {
        Queue::fake(); $org = Organization::create(['name' => 'Plant', 'slug' => 'plant']); $staff = $this->user($org);
        $template = DeviceTemplate::create(['organization_id' => $org->id, 'created_by' => $staff->id, 'name' => 'Thermal']);
        $template->parameters()->create(['name' => 'Temperature', 'key' => 'temperature', 'data_type' => 'number', 'unit' => 'C']);
        $response = $this->actingAs($staff)->postJson('/api/devices', $this->payload(['template_id' => $template->id]))->assertCreated()->assertJsonPath('template.id', (string) $template->id);
        $device = Device::findOrFail($response->json('id'));
        $this->assertDatabaseHas('device_parameters', ['device_id' => $device->id, 'key' => 'temperature', 'unit' => 'C']);
        $template->parameters()->first()->update(['unit' => 'F']);
        $this->assertSame('C', $device->parameters()->first()->unit);
    }

    public function test_staff_created_device_is_globally_visible_recent_and_generates_safe_admin_awareness(): void
    {
        Queue::fake(); $org = Organization::create(['name' => 'Plant', 'slug' => 'plant']);
        $staff = $this->user($org); $admin = $this->user($org, true);
        $id = $this->actingAs($staff)->postJson('/api/devices', $this->payload())->assertCreated()->json('id');
        $this->actingAs($admin)->getJson('/api/admin/devices?recent=1')->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.creator.id', (string) $staff->id);
        (new NotifyAdminsOfDeviceCreated((int) $id))->handle();
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'device.created', 'resource_type' => 'device', 'resource_id' => $id, 'action_url' => "/admin/devices/{$id}"]);
        $notification = \App\Models\Notification::where('user_id', $admin->id)->firstOrFail();
        $this->assertStringNotContainsString('secret', strtolower(json_encode($notification->data).$notification->message));
    }
}
