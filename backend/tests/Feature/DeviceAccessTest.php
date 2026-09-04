<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Organization $organization): User
    {
        return User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'staff',
            'platform_role' => 'platform_admin',
            'status' => 'active',
        ]);
    }

    private function staff(Organization $organization): User
    {
        return User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'staff',
            'platform_role' => null,
            'status' => 'active',
        ]);
    }

    private function device(Organization $organization, string $name = 'Pump-01'): Device
    {
        return Device::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'type' => 'sensor',
            'external_id' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'protocol' => 'mqtt',
        ]);
    }

    public function test_unauthenticated_cannot_list_assignments(): void
    {
        $this->getJson('/api/admin/device-access')->assertUnauthorized();
    }

    public function test_staff_cannot_manage_device_access(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $staff = $this->staff($organization);

        $this->actingAs($staff)->getJson('/api/admin/device-access')->assertForbidden();
        $this->actingAs($staff)->postJson('/api/admin/device-access', [
            'device_id' => 1,
            'user_id' => 2,
            'access_level' => 'viewer',
        ])->assertForbidden();
    }

    public function test_admin_can_assign_staff_to_device_and_list_it(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $admin = $this->admin($organization);
        $staff = $this->staff($organization);
        $device = $this->device($organization);

        $response = $this->actingAs($admin)->postJson('/api/admin/device-access', [
            'device_id' => $device->id,
            'user_id' => $staff->id,
            'access_level' => 'viewer',
        ])->assertCreated()->assertJsonPath('data.accessLevel', 'viewer')->assertJsonPath('data.device.id', (string) $device->id)->assertJsonPath('data.user.id', (string) $staff->id);

        $this->assertDatabaseHas('device_access_assignments', [
            'device_id' => $device->id,
            'user_id' => $staff->id,
            'access_level' => 'viewer',
            'assigned_by' => $admin->id,
        ]);

        $this->actingAs($admin)->getJson('/api/admin/device-access')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_admin_can_change_access_and_remove_assignment(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $admin = $this->admin($organization);
        $staff = $this->staff($organization);
        $device = $this->device($organization);
        $assignment = DeviceAccessAssignment::create([
            'device_id' => $device->id,
            'user_id' => $staff->id,
            'access_level' => 'viewer',
            'assigned_by' => $admin->id,
        ]);

        $this->actingAs($admin)->patchJson("/api/admin/device-access/{$assignment->id}", [
            'access_level' => 'full_access',
        ])->assertOk()->assertJsonPath('data.accessLevel', 'full_access');

        $this->actingAs($admin)->deleteJson("/api/admin/device-access/{$assignment->id}")->assertNoContent();
        $this->assertDatabaseMissing('device_access_assignments', ['id' => $assignment->id]);
    }

    public function test_duplicate_and_invalid_assignments_are_rejected(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $admin = $this->admin($organization);
        $staff = $this->staff($organization);
        $device = $this->device($organization);

        $payload = ['device_id' => $device->id, 'user_id' => $staff->id, 'access_level' => 'viewer'];
        $this->actingAs($admin)->postJson('/api/admin/device-access', $payload)->assertCreated();
        $this->actingAs($admin)->postJson('/api/admin/device-access', $payload)->assertStatus(422);
        $this->actingAs($admin)->postJson('/api/admin/device-access', [...$payload, 'access_level' => 'invalid'])->assertStatus(422);
    }

    public function test_admin_target_and_cross_organization_assignment_are_rejected(): void
    {
        $organizationA = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $organizationB = Organization::create(['name' => 'Org B', 'slug' => 'org-b']);
        $admin = $this->admin($organizationA);
        $staffA = $this->staff($organizationA);
        $staffB = $this->staff($organizationB);
        $deviceA = $this->device($organizationA);
        $adminTarget = $this->admin($organizationA);

        $this->actingAs($admin)->postJson('/api/admin/device-access', [
            'device_id' => $deviceA->id,
            'user_id' => $adminTarget->id,
            'access_level' => 'viewer',
        ])->assertStatus(422);

        $this->actingAs($admin)->postJson('/api/admin/device-access', [
            'device_id' => $deviceA->id,
            'user_id' => $staffB->id,
            'access_level' => 'viewer',
        ])->assertNotFound();

        $this->actingAs($admin)->postJson('/api/admin/device-access', [
            'device_id' => 999999,
            'user_id' => $staffA->id,
            'access_level' => 'viewer',
        ])->assertNotFound();

        $this->actingAs($admin)->postJson('/api/admin/device-access', [
            'device_id' => $deviceA->id,
            'user_id' => 999999,
            'access_level' => 'viewer',
        ])->assertUnprocessable();
    }

    public function test_assignment_endpoints_return_device_and_user_views(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $admin = $this->admin($organization);
        $staff = $this->staff($organization);
        $device = $this->device($organization);
        DeviceAccessAssignment::create([
            'device_id' => $device->id,
            'user_id' => $staff->id,
            'access_level' => 'viewer',
            'assigned_by' => $admin->id,
        ]);

        $this->actingAs($admin)->getJson("/api/admin/devices/{$device->id}/access")
            ->assertOk()
            ->assertJsonPath('data.0.user.id', (string) $staff->id);

        $this->actingAs($admin)->getJson("/api/admin/users/{$staff->id}/device-access")
            ->assertOk()
            ->assertJsonPath('data.0.device.id', (string) $device->id);
    }

    public function test_device_and_user_deletion_cascade_assignments(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $admin = $this->admin($organization);
        $staff = $this->staff($organization);
        $device = $this->device($organization);
        $assignment = DeviceAccessAssignment::create([
            'device_id' => $device->id,
            'user_id' => $staff->id,
            'access_level' => 'viewer',
            'assigned_by' => $admin->id,
        ]);

        $device->delete();
        $this->assertDatabaseMissing('device_access_assignments', ['id' => $assignment->id]);

        $device = $this->device($organization, 'Sensor-02');
        $assignment = DeviceAccessAssignment::create([
            'device_id' => $device->id,
            'user_id' => $staff->id,
            'access_level' => 'viewer',
            'assigned_by' => $admin->id,
        ]);

        $staff->delete();
        $this->assertDatabaseMissing('device_access_assignments', ['id' => $assignment->id]);
    }

    public function test_assigned_by_is_server_controlled_and_secrets_are_not_exposed(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $admin = $this->admin($organization);
        $staff = $this->staff($organization);
        $device = $this->device($organization);

        $response = $this->actingAs($admin)->postJson('/api/admin/device-access', [
            'device_id' => $device->id,
            'user_id' => $staff->id,
            'access_level' => 'viewer',
            'assigned_by' => 999,
            'platform_role' => 'platform_admin',
            'permissions' => ['*'],
        ])->assertCreated();

        $this->actingAs($admin)->postJson('/api/admin/device-access', [
            'device_id' => $device->id,
            'user_id' => $staff->id,
            'access_level' => 'viewer',
            'organization_id' => 123,
        ])->assertNotFound();

        $this->assertSame((string) $admin->id, $response->json('data.assignedBy.id'));
        $response->assertJsonMissingPath('data.password');
        $response->assertJsonMissingPath('data.remember_token');
    }

    public function test_inventory_is_paginated_searchable_and_filterable_from_every_supported_field(): void
    {
        $a=Organization::create(['name'=>'Alpha Operations','slug'=>'alpha-ops']);$b=Organization::create(['name'=>'Beta Operations','slug'=>'beta-ops']);$admin=$this->admin($a);$alice=$this->staff($a);$alice->update(['name'=>'Alice Search','email'=>'alice-search@example.test']);$bob=$this->staff($b);
        $pump=$this->device($a,'Search Pump');$pump->update(['external_id'=>'identifier-search']);$sensor=$this->device($b,'Beta Sensor');
        $first=DeviceAccessAssignment::create(['device_id'=>$pump->id,'user_id'=>$alice->id,'access_level'=>'viewer','assigned_by'=>$admin->id]);DeviceAccessAssignment::create(['device_id'=>$sensor->id,'user_id'=>$bob->id,'access_level'=>'full_access','assigned_by'=>$admin->id]);
        foreach(range(1,24) as $i){$user=$this->staff($a);$device=$this->device($a,"Paged $i");DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$user->id,'access_level'=>'viewer','assigned_by'=>$admin->id]);}
        $this->actingAs($admin)->getJson('/api/admin/device-access?per_page=25')->assertOk()->assertJsonCount(25,'data')->assertJsonPath('meta.total',25)->assertJsonPath('meta.last_page',1);
        foreach(['Alice Search','alice-search@example.test','Search Pump','identifier-search'] as $search)$this->actingAs($admin)->getJson('/api/admin/device-access?search='.urlencode($search))->assertOk()->assertJsonPath('meta.total',1)->assertJsonPath('data.0.id',(string)$first->id);
        $this->actingAs($admin)->getJson('/api/admin/device-access?search=Alpha%20Operations')->assertOk()->assertJsonPath('meta.total',25);
        foreach(["user_id={$alice->id}","device_id={$pump->id}","organization_id={$a->id}&device_id={$pump->id}",'access_level=viewer&device_id='.$pump->id] as $query)$this->actingAs($admin)->getJson("/api/admin/device-access?$query")->assertOk()->assertJsonPath('meta.total',1);
        $this->actingAs($admin)->getJson('/api/admin/device-access?access_level=operator')->assertUnprocessable();
    }

    public function test_access_transitions_and_removal_are_immediately_authoritative(): void
    {
        $organization=Organization::create(['name'=>'Org','slug'=>'transition-org']);$admin=$this->admin($organization);$staff=$this->staff($organization);$device=$this->device($organization);$assignment=DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$staff->id,'access_level'=>'full_access','assigned_by'=>$admin->id]);
        $this->actingAs($staff)->patchJson("/api/devices/{$device->id}",['name'=>'Full works'])->assertOk();
        $this->actingAs($admin)->patchJson("/api/admin/device-access/{$assignment->id}",['access_level'=>'viewer'])->assertOk();
        $this->actingAs($staff)->patchJson("/api/devices/{$device->id}",['name'=>'Viewer blocked'])->assertForbidden();
        $this->actingAs($admin)->patchJson("/api/admin/device-access/{$assignment->id}",['access_level'=>'full_access'])->assertOk();
        $this->actingAs($staff)->patchJson("/api/devices/{$device->id}",['name'=>'Full restored'])->assertOk();
        $this->actingAs($admin)->deleteJson("/api/admin/device-access/{$assignment->id}")->assertNoContent();
        $this->actingAs($staff)->getJson("/api/devices/{$device->id}")->assertNotFound();
        $this->actingAs($staff)->patchJson("/api/devices/{$device->id}",['name'=>'Removed'])->assertNotFound();
        $this->actingAs($staff)->getJson("/api/analytics/devices/{$device->id}")->assertNotFound();
    }

    public function test_context_options_and_safe_user_detail_enforce_assignment_eligibility(): void
    {
        $a=Organization::create(['name'=>'Alpha','slug'=>'options-alpha']);$b=Organization::create(['name'=>'Beta','slug'=>'options-beta']);$admin=$this->admin($a);$staffA=$this->staff($a);$staffB=$this->staff($b);$deviceA=$this->device($a);$deviceB=$this->device($b);DeviceAccessAssignment::create(['device_id'=>$deviceA->id,'user_id'=>$staffA->id,'access_level'=>'viewer','assigned_by'=>$admin->id]);
        $this->actingAs($admin)->getJson("/api/admin/users/options?organization_id={$a->id}&device_id={$deviceA->id}")->assertOk()->assertJsonMissing(['id'=>(string)$staffA->id])->assertJsonMissing(['id'=>(string)$staffB->id]);
        $this->actingAs($admin)->getJson("/api/admin/devices/options?organization_id={$a->id}&user_id={$staffA->id}")->assertOk()->assertJsonMissing(['id'=>(string)$deviceA->id])->assertJsonMissing(['id'=>(string)$deviceB->id]);
        $this->actingAs($admin)->getJson("/api/admin/users/{$staffA->id}")->assertOk()->assertJsonPath('assignedDevicesCount',1)->assertJsonMissingPath('password')->assertJsonMissingPath('remember_token');
    }

    public function test_grant_upgrade_downgrade_and_revoke_emit_access_notifications(): void
    {
        $organization=Organization::create(['name'=>'Governance','slug'=>'governance']);$admin=$this->admin($organization);$staff=$this->staff($organization);$device=$this->device($organization);
        $this->actingAs($admin)->postJson('/api/admin/device-access',['device_id'=>$device->id,'user_id'=>$staff->id,'access_level'=>'viewer'])->assertCreated();
        $assignment=DeviceAccessAssignment::where('device_id',$device->id)->where('user_id',$staff->id)->firstOrFail();
        $this->actingAs($admin)->patchJson("/api/admin/device-access/{$assignment->id}",['access_level'=>'full_access'])->assertOk()->assertJsonPath('data.capabilities.canDowngrade',true);
        $this->actingAs($admin)->patchJson("/api/admin/device-access/{$assignment->id}",['access_level'=>'viewer'])->assertOk()->assertJsonPath('data.capabilities.canUpgrade',true);
        $this->actingAs($admin)->deleteJson("/api/admin/device-access/{$assignment->id}")->assertNoContent();
        foreach(['device.access_granted','device.access_upgraded','device.access_downgraded','device.access_revoked'] as $type)$this->assertDatabaseHas('notifications',['user_id'=>$staff->id,'type'=>$type]);
        $revoked=\App\Models\Notification::where('user_id',$staff->id)->where('type','device.access_revoked')->firstOrFail();
        $this->assertSame('/app/devices',$revoked->action_url);
        $this->assertDatabaseMissing('device_access_assignments',['device_id'=>$device->id,'user_id'=>$staff->id]);
    }

    public function test_disabled_staff_assignment_is_not_authorization(): void
    {
        $organization=Organization::create(['name'=>'Disabled','slug'=>'disabled-governance']);$admin=$this->admin($organization);$staff=$this->staff($organization);$device=$this->device($organization);DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$staff->id,'access_level'=>'full_access','assigned_by'=>$admin->id]);$staff->update(['status'=>'suspended']);
        $service=app(\App\Services\Admin\DeviceAccessService::class);
        $this->assertNull($service->getDeviceAccessLevel($staff->fresh(),$device));
        $this->assertFalse($service->accessibleDevices($staff->fresh())->whereKey($device->id)->exists());
    }
}
