<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\Organization;
use App\Models\ResourceCollaborator;
use App\Models\User;
use App\Services\Admin\DeviceAccessService;
use App\Services\DeviceCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class B02DeviceFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $organization, bool $admin = false): User
    {
        return User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'staff',
            'platform_role' => $admin ? 'platform_admin' : null,
            'status' => 'active',
        ]);
    }

    private function device(Organization $organization, string $serial): Device
    {
        return $organization->devices()->create([
            'name' => $serial,
            'external_id' => $serial,
            'type' => 'sensor',
            'protocol' => 'mqtt',
            'status' => 'online',
        ]);
    }

    public function test_assignment_is_the_only_staff_authority_and_dto_is_frontend_safe(): void
    {
        $organization = Organization::create(['name' => 'A', 'slug' => 'b02-a']);
        $staff = $this->user($organization);
        $assigned = $this->device($organization, 'B02-ASSIGNED');
        $hidden = $this->device($organization, 'B02-HIDDEN');
        DeviceAccessAssignment::create(['device_id' => $assigned->id, 'user_id' => $staff->id, 'access_level' => 'viewer']);
        ResourceCollaborator::create(['resource_type' => 'device', 'resource_id' => $hidden->id, 'user_id' => $staff->id, 'permission' => 'edit', 'granted_by' => $staff->id]);

        $response = $this->actingAs($staff)->getJson("/api/devices/{$assigned->id}")
            ->assertOk()
            ->assertJsonPath('access.level', 'viewer')
            ->assertJsonPath('capabilities.canView', true)
            ->assertJsonPath('capabilities.canEdit', false)
            ->assertJsonStructure(['id', 'canonicalId', 'name', 'serialNumber', 'protocol', 'access', 'capabilities']);

        foreach (['organization_id', 'external_id', 'password', 'token_hash', 'credential', 'private_key', 'accessAssignments'] as $field) {
            $this->assertArrayNotHasKey($field, $response->json());
        }
        $this->actingAs($staff)->getJson("/api/devices/{$hidden->id}")->assertNotFound();
        $this->actingAs($staff)->getJson('/api/devices')->assertOk()->assertJsonCount(1);
    }

    public function test_downgrade_and_revoke_change_authority_immediately_without_changing_role(): void
    {
        $organization = Organization::create(['name' => 'A', 'slug' => 'b02-change']);
        $admin = $this->user($organization, true);
        $staff = $this->user($organization);
        $device = $this->device($organization, 'B02-CHANGE');
        $assignment = app(DeviceAccessService::class)->create($admin, $device->id, $staff->id, 'full_access', false);

        $this->actingAs($staff)->getJson("/api/devices/{$device->id}")->assertOk()->assertJsonPath('capabilities.canEdit', true);
        app(DeviceAccessService::class)->update($assignment, 'viewer', $admin, false);
        $this->actingAs($staff)->getJson("/api/devices/{$device->id}")->assertOk()->assertJsonPath('capabilities.canEdit', false);
        $this->actingAs($staff)->patchJson("/api/devices/{$device->id}", ['name' => 'Denied'])->assertForbidden();
        $this->assertSame('staff', $staff->productRole());

        app(DeviceAccessService::class)->delete($assignment->refresh(), $admin);
        $this->actingAs($staff)->getJson("/api/devices/{$device->id}")->assertNotFound();
        $this->actingAs($staff)->getJson('/api/devices')->assertOk()->assertJsonCount(0);
    }

    public function test_staff_creation_atomically_creates_exactly_one_creator_assignment(): void
    {
        $organization = Organization::create(['name' => 'A', 'slug' => 'b02-create']);
        $staff = $this->user($organization);

        $response = $this->actingAs($staff)->postJson('/api/devices', [
            'name' => 'Created', 'type' => 'sensor', 'serialNumber' => 'B02-CREATED', 'protocol' => 'mqtt',
        ])->assertCreated()->assertJsonPath('access.level', 'full_access');

        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseHas('device_access_assignments', [
            'device_id' => $response->json('id'), 'user_id' => $staff->id,
            'access_level' => 'full_access', 'assigned_by' => $staff->id,
        ]);
        $this->assertSame(1, DeviceAccessAssignment::where('device_id', $response->json('id'))->where('user_id', $staff->id)->count());

        $this->actingAs($staff)->postJson('/api/devices', [
            'name' => 'Rollback', 'type' => 'sensor', 'serialNumber' => 'B02-ROLLBACK',
            'protocol' => 'mqtt', 'template_id' => 999999,
        ])->assertNotFound();
        $this->assertDatabaseMissing('devices', ['external_id' => 'B02-ROLLBACK']);
    }

    public function test_admin_and_creation_service_are_current_organization_scoped(): void
    {
        $a = Organization::create(['name' => 'A', 'slug' => 'b02-admin-a']);
        $b = Organization::create(['name' => 'B', 'slug' => 'b02-admin-b']);
        $admin = $this->user($a, true);
        $foreign = $this->device($b, 'B02-FOREIGN');

        $this->actingAs($admin)->getJson("/api/admin/devices/{$foreign->id}")->assertNotFound();
        $this->actingAs($admin)->postJson('/api/admin/devices', [
            'organization_id' => $b->id, 'name' => 'Foreign create', 'type' => 'sensor',
            'serialNumber' => 'B02-FOREIGN-CREATE', 'protocol' => 'mqtt',
        ])->assertNotFound();

        try {
            app(DeviceCreationService::class)->create($admin, $b, [
                'name' => 'Service bypass', 'type' => 'sensor', 'serialNumber' => 'B02-SERVICE', 'protocol' => 'mqtt',
            ]);
            $this->fail('Cross-Organization service creation was not rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('devices', ['external_id' => 'B02-SERVICE']);
        }
    }
}
