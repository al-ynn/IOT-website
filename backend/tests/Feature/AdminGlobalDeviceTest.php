<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGlobalDeviceTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $organization, bool $admin = false): User
    {
        return User::factory()->create(['organization_id' => $organization->id, 'role' => 'staff', 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']);
    }

    private function device(Organization $organization, string $name, string $status = 'offline'): Device
    {
        return $organization->devices()->create(['name' => $name, 'external_id' => str($name)->slug().'-'.uniqid(), 'status' => $status, 'type' => 'sensor', 'protocol' => 'mqtt']);
    }

    public function test_global_device_routes_require_platform_admin_regardless_of_assignment_level(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $staff = $this->user($organization);
        $admin = $this->user($organization, true);
        $device = $this->device($organization, 'Pump');

        $this->getJson('/api/admin/devices')->assertUnauthorized();
        $this->actingAs($staff)->getJson('/api/admin/devices')->assertForbidden();
        $this->actingAs($staff)->getJson("/api/admin/devices/{$device->id}")->assertForbidden();
        foreach (['viewer', 'full_access'] as $level) {
            DeviceAccessAssignment::updateOrCreate(['device_id' => $device->id, 'user_id' => $staff->id], ['access_level' => $level]);
            $this->actingAs($staff)->getJson('/api/admin/devices')->assertForbidden();
        }
        $this->actingAs($admin)->getJson('/api/admin/devices')->assertOk();
        $this->actingAs($admin)->getJson("/api/admin/devices/{$device->id}")->assertOk();
    }

    public function test_admin_list_is_current_organization_and_normal_staff_list_remains_assignment_scoped(): void
    {
        $a = Organization::create(['name' => 'Alpha', 'slug' => 'alpha']);
        $b = Organization::create(['name' => 'Beta', 'slug' => 'beta']);
        $admin = $this->user($a, true);
        $staff = $this->user($a);
        $deviceA = $this->device($a, 'Alpha Pump', 'online');
        $deviceB = $this->device($b, 'Beta Pump');
        DeviceAccessAssignment::create(['device_id' => $deviceA->id, 'user_id' => $staff->id, 'access_level' => 'viewer']);

        $this->actingAs($admin)->getJson('/api/admin/devices')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonFragment(['name' => 'Alpha Pump'])->assertJsonMissing(['name' => 'Beta Pump']);
        $this->actingAs($admin)->getJson("/api/admin/devices/{$deviceB->id}")->assertNotFound();
        $this->actingAs($staff)->getJson('/api/devices')->assertOk()->assertJsonCount(1)->assertJsonFragment(['name' => 'Alpha Pump'])->assertJsonMissing(['name' => 'Beta Pump']);
        $this->actingAs($staff)->getJson("/api/devices/{$deviceB->id}")->assertNotFound();
    }

    public function test_list_filters_search_and_safe_sorting_are_server_side(): void
    {
        $a = Organization::create(['name' => 'Alpha Works', 'slug' => 'alpha']);
        $b = Organization::create(['name' => 'Beta Works', 'slug' => 'beta']);
        $admin = $this->user($a, true);
        $this->device($a, 'North Sensor', 'online');
        $this->device($b, 'South Pump', 'offline');

        $this->actingAs($admin)->getJson("/api/admin/devices?organization_id={$b->id}&status=offline&search=South&sort=name&direction=asc")
            ->assertNotFound();
        $this->actingAs($admin)->getJson('/api/admin/devices?sort=organization_id desc;drop table devices')->assertUnprocessable();
        $this->actingAs($admin)->getJson('/api/admin/devices?direction=sideways')->assertUnprocessable();
        $this->actingAs($admin)->getJson('/api/admin/devices?status=critical')->assertUnprocessable();
    }

    public function test_list_uses_bounded_database_pagination_without_duplicates(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $admin = $this->user($organization, true);
        foreach (range(1, 27) as $number) $this->device($organization, sprintf('Device %02d', $number));

        $first = $this->actingAs($admin)->getJson('/api/admin/devices?sort=name&direction=asc&per_page=25')->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('meta.total', 27)->assertJsonPath('meta.last_page', 2);
        $second = $this->actingAs($admin)->getJson('/api/admin/devices?sort=name&direction=asc&per_page=25&page=2')->assertOk()->assertJsonCount(2, 'data');
        $this->assertEmpty(array_intersect(collect($first->json('data'))->pluck('id')->all(), collect($second->json('data'))->pluck('id')->all()));
        $this->actingAs($admin)->getJson('/api/admin/devices?per_page=1000')->assertUnprocessable();
    }

    public function test_assignment_counts_and_detail_assignment_data_are_exact_and_safe(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $admin = $this->user($organization, true);
        $viewer = $this->user($organization);
        $manager = $this->user($organization);
        $none = $this->device($organization, 'None');
        $one = $this->device($organization, 'One');
        $multiple = $this->device($organization, 'Multiple');
        DeviceAccessAssignment::create(['device_id' => $one->id, 'user_id' => $viewer->id, 'access_level' => 'viewer']);
        DeviceAccessAssignment::create(['device_id' => $multiple->id, 'user_id' => $viewer->id, 'access_level' => 'viewer']);
        DeviceAccessAssignment::create(['device_id' => $multiple->id, 'user_id' => $manager->id, 'access_level' => 'full_access']);

        $this->actingAs($admin)->getJson('/api/admin/devices?sort=name&direction=asc')->assertOk()
            ->assertJsonFragment(['name' => 'None', 'assignedStaffCount' => 0])
            ->assertJsonFragment(['name' => 'One', 'assignedStaffCount' => 1])
            ->assertJsonFragment(['name' => 'Multiple', 'assignedStaffCount' => 2]);
        $detail = $this->actingAs($admin)->getJson("/api/admin/devices/{$multiple->id}")->assertOk()->assertJsonCount(2, 'data.assignments')->assertJsonPath('data.device.organization.name', 'Org');
        foreach (['password', 'remember_token', 'token', 'secret'] as $term) $this->assertStringNotContainsString($term, strtolower($detail->getContent()));
    }

    public function test_admin_can_update_allowlisted_fields_without_privilege_or_tenant_injection(): void
    {
        $a = Organization::create(['name' => 'Alpha', 'slug' => 'alpha']);
        $b = Organization::create(['name' => 'Beta', 'slug' => 'beta']);
        $admin = $this->user($a, true);
        $staff = $this->user($a);
        $device = $this->device($a, 'Original');
        $assignment = DeviceAccessAssignment::create(['device_id' => $device->id, 'user_id' => $staff->id, 'access_level' => 'viewer']);

        $this->actingAs($admin)->patchJson("/api/admin/devices/{$device->id}", [
            'name' => 'Updated', 'type' => 'gateway', 'protocol' => 'http', 'location' => 'Plant A', 'macAddress' => 'AA:BB',
            'user_id' => $admin->id, 'platform_role' => 'platform_admin', 'access_level' => 'full_access', 'assigned_by' => $admin->id, 'status' => 'online',
        ])->assertOk()->assertJsonPath('data.device.name', 'Updated');
        $this->actingAs($admin)->patchJson("/api/admin/devices/{$device->id}", ['organization_id' => $b->id])->assertNotFound();

        $device->refresh();
        $this->assertSame($a->id, $device->organization_id);
        $this->assertSame('offline', $device->status);
        $this->assertDatabaseHas('device_access_assignments', ['id' => $assignment->id, 'access_level' => 'viewer']);
    }

    public function test_no_delete_route_is_exposed_while_json_references_cannot_be_cascade_cleaned(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $admin = $this->user($organization, true);
        $device = $this->device($organization, 'Protected');
        $this->actingAs($admin)->deleteJson("/api/admin/devices/{$device->id}")->assertMethodNotAllowed();
        $this->assertDatabaseHas('devices', ['id' => $device->id]);
    }
}
