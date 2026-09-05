<?php

namespace Tests\Feature;

use App\Models\CrashReport;
use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\OperationalEvent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGlobalOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $organization, bool $admin = false): User
    {
        return User::factory()->create(['organization_id' => $organization->id, 'role' => 'staff', 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']);
    }

    private function device(Organization $organization, string $name, string $status, ?string $lastSeen = null): Device
    {
        return $organization->devices()->create(['name' => $name, 'external_id' => str($name)->slug().'-'.uniqid(), 'status' => $status, 'type' => 'sensor', 'protocol' => 'mqtt', 'last_seen' => $lastSeen]);
    }

    public function test_admin_overview_is_authenticated_and_platform_admin_only(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $staff = $this->user($organization);
        $admin = $this->user($organization, true);
        $device = $this->device($organization, 'Assigned', 'online', '2026-08-20 10:00:00');

        $this->getJson('/api/admin/operations/overview')->assertUnauthorized();
        $this->actingAs($staff)->getJson('/api/admin/operations/overview')->assertForbidden();
        foreach (['viewer', 'full_access'] as $level) {
            DeviceAccessAssignment::updateOrCreate(['device_id' => $device->id, 'user_id' => $staff->id], ['access_level' => $level]);
            $this->actingAs($staff)->getJson('/api/admin/operations/overview')->assertForbidden();
        }
        $this->actingAs($admin)->getJson('/api/admin/operations/overview')->assertOk();
    }

    public function test_overview_aggregates_current_organization_with_real_status_and_assignment_counts(): void
    {
        $a = Organization::create(['name' => 'Alpha', 'slug' => 'alpha']);
        $b = Organization::create(['name' => 'Beta', 'slug' => 'beta']);
        $admin = $this->user($a, true);
        $staff = $this->user($a);
        $devices = [
            $this->device($a, 'A Online', 'online', '2026-08-20 10:00:00'),
            $this->device($a, 'A Offline', 'offline', '2026-08-19 10:00:00'),
            $this->device($b, 'B Online One', 'online', '2026-08-20 09:00:00'),
            $this->device($b, 'B Online Two', 'online', null),
            $this->device($b, 'B Offline', 'offline', null),
        ];
        DeviceAccessAssignment::create(['device_id' => $devices[0]->id, 'user_id' => $staff->id, 'access_level' => 'viewer']);

        $response = $this->actingAs($admin)->getJson('/api/admin/operations/overview')->assertOk()
            ->assertJsonPath('devices.total', 2)->assertJsonPath('devices.online', 1)->assertJsonPath('devices.offline', 1)
            ->assertJsonPath('organizations.total', 1)->assertJsonPath('organizations.withDevices', 1)
            ->assertJsonFragment(['name' => 'A Online', 'assignedStaffCount' => 1])
            ->assertJsonMissing(['name' => 'B Offline']);
        foreach (['billing', 'plan', 'subscription', 'entitlement', 'revenue'] as $term) {
            $this->assertStringNotContainsString($term, strtolower($response->getContent()));
        }
    }

    public function test_filters_are_server_side_and_validated(): void
    {
        $a = Organization::create(['name' => 'Alpha Works', 'slug' => 'alpha']);
        $b = Organization::create(['name' => 'Beta Works', 'slug' => 'beta']);
        $admin = $this->user($a, true);
        $this->device($a, 'Pump North', 'online');
        $this->device($b, 'Pump South', 'offline');

        $this->actingAs($admin)->getJson("/api/admin/operations/overview?organization_id={$b->id}&status=offline&search=South")
            ->assertNotFound();
        $this->actingAs($admin)->getJson('/api/admin/operations/overview?status=critical')->assertUnprocessable();
        $this->actingAs($admin)->getJson('/api/admin/operations/overview?organization_id=999999')->assertNotFound();
    }

    public function test_operational_window_uses_real_persisted_last_24_hour_records(): void
    {
        $organization = Organization::create(['name' => 'Operations', 'slug' => 'operations-window']);
        $admin = $this->user($organization, true);
        $device = $this->device($organization, 'Pump', 'online');
        OperationalEvent::create(['organization_id' => $organization->id, 'device_id' => $device->id, 'device_bound' => true, 'source' => 'device', 'event_type' => 'device_crash_reported', 'severity' => 'error', 'title' => 'Recent', 'message' => 'Recent', 'context' => [], 'occurred_at' => now()->subHours(2)]);
        OperationalEvent::create(['organization_id' => $organization->id, 'device_id' => $device->id, 'device_bound' => true, 'source' => 'device', 'event_type' => 'device_crash_reported', 'severity' => 'error', 'title' => 'Old', 'message' => 'Old', 'context' => [], 'occurred_at' => now()->subDays(2)]);
        CrashReport::create(['organization_id' => $organization->id, 'device_id' => $device->id, 'crash_type' => 'panic', 'received_at' => now()->subHour()]);
        $this->actingAs($admin)->getJson('/api/admin/operations/overview')->assertOk()->assertJsonPath('operationalWindow.hours', 24)->assertJsonPath('operationalWindow.errors', 1)->assertJsonPath('operationalWindow.crashes', 1);
    }

    public function test_normal_device_endpoint_remains_assignment_scoped(): void
    {
        $a = Organization::create(['name' => 'Alpha', 'slug' => 'alpha']);
        $b = Organization::create(['name' => 'Beta', 'slug' => 'beta']);
        $staff = $this->user($a);
        $visible = $this->device($a, 'Visible', 'online');
        $this->device($a, 'Unassigned', 'online');
        $this->device($b, 'Foreign', 'online');
        DeviceAccessAssignment::create(['device_id' => $visible->id, 'user_id' => $staff->id, 'access_level' => 'viewer']);

        $this->actingAs($staff)->getJson('/api/devices')->assertOk()->assertJsonCount(1)->assertJsonFragment(['name' => 'Visible'])->assertJsonMissing(['name' => 'Foreign']);
    }
}
