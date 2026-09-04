<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\DeviceSnapshot;
use App\Models\Organization;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function org(string $slug): Organization
    {
        return Organization::create(['name' => $slug, 'slug' => $slug]);
    }

    private function user(Organization $org, string $role = 'staff', bool $admin = false): User
    {
        return User::factory()->create(['organization_id' => $org->id, 'role' => $role, 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']);
    }

    private function device(Organization $org, string $name): Device
    {
        $location=Location::firstOrCreate(['organization_id'=>$org->id,'normalized_name'=>'plant a'],['name'=>'Plant A']);return $org->devices()->create(['location_id'=>$location->id,'name' => $name, 'external_id' => str($name)->slug().uniqid(), 'status' => 'online', 'type' => 'sensor', 'protocol' => 'mqtt', 'firmware_version' => '1.0.0']);
    }

    private function assign(User $user, Device $device, string $access): void
    {
        DeviceAccessAssignment::create(['user_id' => $user->id, 'device_id' => $device->id, 'access_level' => $access]);
    }

    public function test_full_access_captures_allowlisted_immutable_device_state(): void
    {
        $org = $this->org('snapshot-capture');
        $user = $this->user($org, 'owner');
        $device = $this->device($org, 'Pump');
        $this->assign($user, $device, 'full_access');
        $device->parameters()->create(['name' => 'Temperature', 'key' => 'temperature', 'data_type' => 'number', 'unit' => 'C']);
        $response = $this->actingAs($user)->postJson("/api/devices/{$device->id}/snapshots", ['name' => 'Before service', 'payload' => ['token' => 'attack'], 'device_id' => 999, 'organization_id' => 999, 'created_by' => 999])->assertUnprocessable();
        $response = $this->actingAs($user)->postJson("/api/devices/{$device->id}/snapshots", ['name' => 'Before service'])->assertCreated();
        $snapshot = DeviceSnapshot::findOrFail($response->json('data.id'));
        $this->assertSame($org->id, $snapshot->organization_id);
        $this->assertSame($user->id, $snapshot->created_by);
        $this->assertSame('1.0.0', $snapshot->payload['device']['reported_firmware_version']);
        $encoded = json_encode($snapshot->payload);
        $this->assertStringNotContainsString('credential', strtolower($encoded));
        $this->assertStringNotContainsString('token', strtolower($encoded));
        $this->assertStringNotContainsString('secret', strtolower($encoded));
        $plantB=Location::create(['organization_id'=>$org->id,'name'=>'Plant B','normalized_name'=>'plant b']);$device->update(['location_id'=>$plantB->id,'firmware_version'=>'2.0.0']);
        $this->assertSame('Plant A', $snapshot->refresh()->payload['device']['location']['name']);
        $this->assertSame('1.0.0', $snapshot->payload['device']['reported_firmware_version']);
        $this->actingAs($user)->patchJson("/api/devices/{$device->id}/snapshots/{$snapshot->id}", ['payload' => []])->assertMethodNotAllowed();
    }

    public function test_viewer_reads_but_cannot_create_and_hidden_devices_are_not_disclosed(): void
    {
        $a = $this->org('snapshot-a');
        $b = $this->org('snapshot-b');
        $owner = $this->user($a, 'owner');
        $viewer = $this->user($a);
        $hidden = $this->user($a);
        $foreign = $this->user($b);
        $device = $this->device($a, 'Pump');
        $this->assign($owner, $device, 'full_access');
        $this->assign($viewer, $device, 'viewer');
        $id = $this->actingAs($owner)->postJson("/api/devices/{$device->id}/snapshots", ['name' => 'Safe'])->assertCreated()->json('data.id');
        $this->actingAs($viewer)->getJson("/api/devices/{$device->id}/snapshots")->assertOk()->assertJsonPath('meta.total', 1);
        $this->actingAs($viewer)->getJson("/api/devices/{$device->id}/snapshots/{$id}")->assertOk();
        $this->actingAs($viewer)->postJson("/api/devices/{$device->id}/snapshots", ['name' => 'Blocked'])->assertForbidden();
        $this->actingAs($hidden)->getJson("/api/devices/{$device->id}/snapshots")->assertNotFound();
        $this->actingAs($foreign)->getJson("/api/devices/{$device->id}/snapshots/{$id}")->assertNotFound();
    }

    public function test_compare_is_deterministic_nested_and_device_scoped(): void
    {
        $org = $this->org('snapshot-compare');
        $user = $this->user($org, 'owner');
        $one = $this->device($org, 'One');
        $two = $this->device($org, 'Two');
        $this->assign($user, $one, 'full_access');
        $this->assign($user, $two, 'full_access');
        $before = $this->actingAs($user)->postJson("/api/devices/{$one->id}/snapshots", ['name' => 'Before'])->assertCreated()->json('data.id');
        $plantB=Location::create(['organization_id'=>$org->id,'name'=>'Plant B','normalized_name'=>'plant b']);$one->update(['location_id'=>$plantB->id]);
        $after = $this->actingAs($user)->postJson("/api/devices/{$one->id}/snapshots", ['name' => 'After'])->assertCreated()->json('data.id');
        $foreignSnapshot = $this->actingAs($user)->postJson("/api/devices/{$two->id}/snapshots", ['name' => 'Other'])->assertCreated()->json('data.id');
        $this->actingAs($user)->postJson("/api/devices/{$one->id}/snapshots/compare", ['from_snapshot_id' => $before, 'to_snapshot_id' => $after])->assertOk()->assertJsonFragment(['path'=>'device.location.name','before'=>'Plant A','after'=>'Plant B']);
        $this->actingAs($user)->postJson("/api/devices/{$one->id}/snapshots/compare", ['from_snapshot_id' => $before, 'to_snapshot_id' => $foreignSnapshot])->assertNotFound();
    }

    public function test_admin_uses_current_organization_routes_without_assignments(): void
    {
        $adminOrg = $this->org('snapshot-admin');
        $targetOrg = $this->org('snapshot-target');
        $admin = $this->user($adminOrg, 'owner', true);
        $foreign = $this->device($targetOrg, 'Remote');
        $this->actingAs($admin)->postJson("/api/admin/devices/{$foreign->id}/snapshots", ['name' => 'Foreign capture'])->assertNotFound();
        $device = $this->device($adminOrg, 'Local');
        $id = $this->actingAs($admin)->postJson("/api/admin/devices/{$device->id}/snapshots", ['name' => 'Global capture'])->assertCreated()->json('data.id');
        $this->actingAs($admin)->getJson("/api/admin/devices/{$device->id}/snapshots/{$id}")->assertOk();
        $this->assertDatabaseCount('device_access_assignments', 0);
    }
}
