<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\DeviceCredential;
use App\Models\FirmwareDeployment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeviceCredentialTest extends TestCase
{
    use RefreshDatabase;

    private function org(string $slug): Organization
    {
        return Organization::create(['name' => $slug, 'slug' => $slug]);
    }

    private function user(Organization $o, string $role = 'owner', bool $admin = false): User
    {
        return User::factory()->create(['organization_id' => $o->id, 'role' => $role, 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']);
    }

    private function device(Organization $o, string $name): Device
    {
        return $o->devices()->create(['name' => $name, 'external_id' => str($name)->slug().uniqid(), 'type' => 'sensor', 'protocol' => 'mqtt']);
    }

    private function assign(User $u, Device $d, string $level): void
    {
        DeviceAccessAssignment::create(['user_id' => $u->id, 'device_id' => $d->id, 'access_level' => $level]);
    }

    private function create(User $u, Device $d, array $data = []): array
    {
        return $this->actingAs($u)->postJson("/api/devices/{$d->id}/credentials", ['name' => 'Production', 'scopes' => ['telemetry:write'], ...$data])->assertCreated()->json();
    }

    public function test_secret_is_server_generated_hashed_and_returned_once_only(): void
    {
        $o = $this->org('credential-secret');
        $u = $this->user($o);
        $d = $this->device($o, 'Pump');
        $this->assign($u, $d, 'full_access');
        $created = $this->create($u, $d, ['token' => 'attack', 'token_hash' => 'attack', 'device_id' => 999, 'created_by' => 999, 'organization_id' => 999]);
        $this->assertMatchesRegularExpression('/^iotd_[0-9a-f-]{36}_[A-Za-z0-9_-]{43}$/', $created['token']);
        $row = DeviceCredential::firstOrFail();
        $this->assertNotSame($created['token'], $row->token_hash);
        $this->assertStringNotContainsString(substr($created['token'], -20), json_encode($row->getAttributes()));
        $list = $this->actingAs($u)->getJson("/api/devices/{$d->id}/credentials")->assertOk();
        $list->assertJsonMissingPath('data.0.token')->assertJsonMissingPath('data.0.token_hash');
        $this->assertArrayNotHasKey('token_hash', $created['data']);
    }

    public function test_valid_invalid_revoked_expired_and_deleted_device_authentication(): void
    {
        $o = $this->org('credential-auth');
        $u = $this->user($o);
        $d = $this->device($o, 'Pump');
        $this->assign($u, $d, 'full_access');
        $token = $this->create($u, $d)['token'];
        $payload = ['key' => 'temperature', 'value' => 20];
        $this->withToken($token)->postJson('/api/device/telemetry', $payload)->assertCreated();
        $this->withToken('iotd_invalid')->postJson('/api/device/telemetry', $payload)->assertUnauthorized();
        $credential = DeviceCredential::first();
        $this->actingAs($u)->postJson("/api/devices/{$d->id}/credentials/{$credential->id}/revoke")->assertOk();
        $this->withToken($token)->postJson('/api/device/telemetry', $payload)->assertUnauthorized();
        $expired = $this->create($u, $d, ['expires_at' => now()->addMinute()->toISOString()]);
        DeviceCredential::latest('id')->first()->update(['expires_at' => now()->subMinute()]);
        $this->withToken($expired['token'])->postJson('/api/device/telemetry', $payload)->assertUnauthorized();
        $fresh = $this->create($u, $d)['token'];
        $d->delete();
        $this->withToken($fresh)->postJson('/api/device/telemetry', $payload)->assertUnauthorized();
    }

    public function test_telemetry_scope_and_device_identity_are_structurally_enforced(): void
    {
        $o = $this->org('credential-telemetry');
        $u = $this->user($o);
        $a = $this->device($o, 'A');
        $b = $this->device($o, 'B');
        $this->assign($u, $a, 'full_access');
        $this->assign($u, $b, 'full_access');
        $telemetry = $this->create($u, $a)['token'];
        $firmware = $this->create($u, $a, ['name' => 'Firmware', 'scopes' => ['firmware:read']])['token'];
        $this->withToken($firmware)->postJson('/api/device/telemetry', ['key' => 'temperature', 'value' => 10])->assertForbidden();
        $this->withToken($telemetry)->postJson('/api/device/telemetry', ['device_id' => $b->id, 'key' => 'temperature', 'value' => 10])->assertUnprocessable();
        $this->withToken($telemetry)->postJson('/api/device/telemetry', ['key' => 'temperature', 'value' => 10])->assertCreated()->assertJsonPath('data.device_id', (string) $a->id);
        $this->assertDatabaseHas('telemetry_records', ['device_id' => $a->id, 'key' => 'temperature']);
        $this->assertDatabaseMissing('telemetry_records', ['device_id' => $b->id]);
    }

    public function test_staff_management_requires_permission_full_access_and_nested_ownership(): void
    {
        $a = $this->org('credential-staff-a');
        $b = $this->org('credential-staff-b');
        $owner = $this->user($a);
        $viewer = $this->user($a, 'staff');
        $unassignedUser = $this->user($a);
        $foreign = $this->user($b);
        $one = $this->device($a, 'One');
        $two = $this->device($a, 'Two');
        $foreignDevice = $this->device($b, 'Foreign');
        $this->assign($owner, $one, 'full_access');
        $this->assign($owner, $two, 'full_access');
        $this->assign($viewer, $one, 'viewer');
        $this->actingAs($viewer)->getJson("/api/devices/{$one->id}/credentials")->assertForbidden();
        $this->actingAs($viewer)->postJson("/api/devices/{$one->id}/credentials", ['name' => 'Blocked'])->assertForbidden();
        $this->actingAs($unassignedUser)->postJson("/api/devices/{$one->id}/credentials", ['name' => 'Blocked'])->assertNotFound();
        $this->actingAs($owner)->postJson("/api/devices/{$foreignDevice->id}/credentials", ['name' => 'Blocked'])->assertNotFound();
        $credentialId = $this->create($owner, $one)['data']['id'];
        $this->actingAs($owner)->postJson("/api/devices/{$two->id}/credentials/$credentialId/revoke")->assertNotFound();
        $this->actingAs($foreign)->getJson('/api/admin/devices/'.$one->id.'/credentials')->assertForbidden();
    }

    public function test_scopes_rotation_and_independent_revocation(): void
    {
        $o = $this->org('credential-rotation');
        $u = $this->user($o);
        $d = $this->device($o, 'Pump');
        $this->assign($u, $d, 'full_access');
        foreach ([['admin'], ['users:write'], ['*']] as $scopes) {
            $this->actingAs($u)->postJson("/api/devices/{$d->id}/credentials", ['name' => 'Invalid', 'scopes' => $scopes])->assertUnprocessable();
        }$old = $this->create($u, $d);
        $new = $this->create($u, $d, ['name' => 'Replacement']);
        $payload = ['key' => 'pressure', 'value' => 2];
        $this->withToken($old['token'])->postJson('/api/device/telemetry', $payload)->assertCreated();
        $this->withToken($new['token'])->postJson('/api/device/telemetry', $payload)->assertCreated();
        $this->actingAs($u)->postJson("/api/devices/{$d->id}/credentials/{$old['data']['id']}/revoke")->assertOk();
        $this->withToken($old['token'])->postJson('/api/device/telemetry', $payload)->assertUnauthorized();
        $this->withToken($new['token'])->postJson('/api/device/telemetry', $payload)->assertCreated();
        $this->assertNull(DeviceCredential::find($new['data']['id'])->revoked_at);
    }

    public function test_admin_current_organization_management_is_nested_and_creates_no_assignment(): void
    {
        $a = $this->org('credential-admin-a');
        $b = $this->org('credential-admin-b');
        $admin = $this->user($a, 'staff', true);
        $d1 = $this->device($a, 'One');
        $d2 = $this->device($a, 'Two');
        $foreign = $this->device($b, 'Foreign');
        $created = $this->actingAs($admin)->postJson("/api/admin/devices/{$d1->id}/credentials", ['name' => 'Admin credential', 'scopes' => ['telemetry:write']])->assertCreated()->json();
        $this->actingAs($admin)->getJson("/api/admin/devices/{$d1->id}/credentials")->assertOk()->assertJsonPath('data.0.id', $created['data']['id']);
        $this->actingAs($admin)->postJson("/api/admin/devices/{$d2->id}/credentials/{$created['data']['id']}/revoke")->assertNotFound();
        $this->actingAs($admin)->getJson("/api/admin/devices/{$foreign->id}/credentials")->assertNotFound();
        $this->actingAs($admin)->postJson("/api/admin/devices/{$foreign->id}/credentials", ['name' => 'Foreign'])->assertNotFound();
        $this->assertDatabaseCount('device_access_assignments', 0);
    }

    public function test_firmware_download_requires_scope_and_own_target(): void
    {
        Storage::fake('local');
        $o = $this->org('credential-firmware');
        $u = $this->user($o);
        $a = $this->device($o, 'A');
        $b = $this->device($o, 'B');
        $this->assign($u, $a, 'full_access');
        $this->assign($u, $b, 'full_access');
        $artifact = $o->firmwareArtifacts()->create(['uploaded_by' => $u->id, 'name' => 'FW', 'version' => '1', 'storage_disk' => 'local', 'storage_path' => 'firmware/test.bin', 'original_filename' => 'test.bin', 'mime_type' => 'application/octet-stream', 'size_bytes' => 3, 'sha256' => hash('sha256', 'abc')]);
        Storage::disk('local')->put('firmware/test.bin', 'abc');
        $deployment = FirmwareDeployment::create(['organization_id' => $o->id, 'firmware_artifact_id' => $artifact->id, 'created_by' => $u->id, 'status' => 'delivery_unavailable']);
        $targetA = $deployment->targets()->create(['device_id' => $a->id, 'status' => 'delivery_unavailable']);
        $targetB = $deployment->targets()->create(['device_id' => $b->id, 'status' => 'delivery_unavailable']);
        $telemetry = $this->create($u, $a)['token'];
        $firmware = $this->create($u, $a, ['name' => 'Download', 'scopes' => ['firmware:read']])['token'];
        $this->withToken($telemetry)->get("/api/device/firmware/{$targetA->id}/download")->assertForbidden();
        $this->withToken($firmware)->get("/api/device/firmware/{$targetB->id}/download")->assertNotFound();
        $this->withToken($firmware)->get("/api/device/firmware/{$targetA->id}/download")->assertOk();
        DeviceCredential::where('name','Download')->update(['revoked_at' => now()]);
        $this->withToken($firmware)->get("/api/device/firmware/{$targetA->id}/download")->assertUnauthorized();
    }
}
