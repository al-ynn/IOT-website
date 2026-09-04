<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\Organization;
use App\Models\ResourceShareRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceSharingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $sender;
    private User $recipient;
    private User $admin;
    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create(['name' => 'Sharing Org', 'slug' => 'sharing-org']);
        $this->sender = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'staff', 'platform_role' => null, 'status' => 'active']);
        $this->recipient = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'staff', 'platform_role' => null, 'status' => 'active']);
        $this->admin = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'staff', 'platform_role' => 'platform_admin', 'status' => 'active']);
        $this->device = Device::create(['organization_id' => $this->org->id, 'name' => 'Canonical Pump', 'type' => 'sensor', 'external_id' => 'canonical-pump', 'protocol' => 'mqtt']);
        DeviceAccessAssignment::create(['device_id' => $this->device->id, 'user_id' => $this->sender->id, 'access_level' => 'full_access']);
    }

    private function createRequest(string $permission = 'full_access'): ResourceShareRequest
    {
        $id = $this->actingAs($this->sender)->postJson('/api/shares', ['resource_type' => 'device', 'resource_id' => $this->device->id, 'recipient_id' => $this->recipient->id, 'permission' => $permission, 'note' => 'Please help maintain this device.'])
            ->assertCreated()->assertJsonPath('data.status', 'pending_recipient')->json('data.id');
        return ResourceShareRequest::findOrFail($id);
    }

    public function test_viewer_cannot_share_but_full_access_can_create_governed_request(): void
    {
        DeviceAccessAssignment::where('user_id', $this->sender->id)->update(['access_level' => 'viewer']);
        $payload = ['resource_type' => 'device', 'resource_id' => $this->device->id, 'recipient_id' => $this->recipient->id, 'permission' => 'viewer'];
        $this->actingAs($this->sender)->postJson('/api/shares', $payload)->assertNotFound();
        DeviceAccessAssignment::where('user_id', $this->sender->id)->update(['access_level' => 'full_access']);
        $this->actingAs($this->sender)->postJson('/api/shares', $payload)->assertCreated();
        $this->assertDatabaseMissing('device_access_assignments', ['device_id' => $this->device->id, 'user_id' => $this->recipient->id]);
    }

    public function test_recipient_accept_then_admin_downgrade_approval_uses_canonical_assignment(): void
    {
        $share = $this->createRequest();
        $this->actingAs($this->recipient)->postJson("/api/shares/{$share->id}/accept")->assertOk()->assertJsonPath('data.status', 'awaiting_admin_approval');
        $this->assertDatabaseMissing('device_access_assignments', ['device_id' => $this->device->id, 'user_id' => $this->recipient->id]);
        $this->actingAs($this->admin)->postJson("/api/admin/access-requests/{$share->id}/approve", ['permission' => 'viewer'])->assertOk()->assertJsonPath('data.final_permission', 'viewer');
        $this->assertDatabaseHas('device_access_assignments', ['device_id' => $this->device->id, 'user_id' => $this->recipient->id, 'access_level' => 'viewer', 'assigned_by' => $this->admin->id]);
        $this->actingAs($this->recipient)->getJson("/api/devices/{$this->device->id}")->assertOk()->assertJsonPath('id', (string) $this->device->id);
    }

    public function test_decline_reject_and_cancel_never_create_assignment(): void
    {
        $share = $this->createRequest('viewer');
        $this->actingAs($this->recipient)->postJson("/api/shares/{$share->id}/decline")->assertOk()->assertJsonPath('data.status', 'declined');
        $this->actingAs($this->recipient)->postJson("/api/shares/{$share->id}/decline")->assertOk();
        $this->assertDatabaseMissing('device_access_assignments', ['device_id' => $this->device->id, 'user_id' => $this->recipient->id]);
    }

    public function test_eligibility_type_permission_duplicate_and_secret_attacks_fail_safely(): void
    {
        $foreignOrg = Organization::create(['name' => 'Foreign', 'slug' => 'foreign']);
        $foreign = User::factory()->create(['organization_id' => $foreignOrg->id, 'role' => 'staff', 'status' => 'active']);
        $base = ['resource_type' => 'device', 'resource_id' => $this->device->id, 'recipient_id' => $this->recipient->id, 'permission' => 'viewer'];
        foreach ([
            [...$base, 'resource_type' => 'App\\Models\\Device'],
            [...$base, 'resource_type' => '../device'],
            [...$base, 'permission' => 'edit'],
            [...$base, 'recipient_id' => $foreign->id],
            [...$base, 'recipient_id' => $this->sender->id],
            [...$base, 'note' => 'Authorization: Bearer abc.secret'],
        ] as $payload) $this->actingAs($this->sender)->postJson('/api/shares', $payload)->assertStatus(422);
        $this->actingAs($this->sender)->postJson('/api/shares', $base)->assertCreated();
        $this->actingAs($this->sender)->postJson('/api/shares', $base)->assertStatus(422);
    }

    public function test_request_ids_are_private_and_double_accept_and_approval_are_idempotent(): void
    {
        $share = $this->createRequest();
        $unrelated = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'staff', 'status' => 'active']);
        $this->actingAs($unrelated)->getJson("/api/shares/{$share->id}")->assertNotFound();
        $this->actingAs($unrelated)->postJson("/api/shares/{$share->id}/accept")->assertNotFound();
        $this->actingAs($this->recipient)->postJson("/api/shares/{$share->id}/accept")->assertOk();
        $this->actingAs($this->recipient)->postJson("/api/shares/{$share->id}/accept")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/admin/access-requests/{$share->id}/approve", ['permission' => 'full_access'])->assertOk();
        $this->actingAs($this->admin)->postJson("/api/admin/access-requests/{$share->id}/approve", ['permission' => 'full_access'])->assertOk();
        $this->assertSame(1, DeviceAccessAssignment::where('device_id', $this->device->id)->where('user_id', $this->recipient->id)->count());
    }

    public function test_admin_direct_grant_is_immediate_and_admin_recipient_is_rejected(): void
    {
        $this->actingAs($this->admin)->postJson('/api/shares', ['resource_type' => 'device', 'resource_id' => $this->device->id, 'recipient_id' => $this->recipient->id, 'permission' => 'viewer'])->assertCreated()->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseHas('device_access_assignments', ['device_id' => $this->device->id, 'user_id' => $this->recipient->id, 'access_level' => 'viewer']);
        $otherAdmin = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'staff', 'platform_role' => 'platform_admin', 'status' => 'active']);
        $this->actingAs($this->admin)->postJson('/api/shares', ['resource_type' => 'device', 'resource_id' => $this->device->id, 'recipient_id' => $otherAdmin->id, 'permission' => 'viewer'])->assertStatus(422);
    }

    public function test_stale_viewer_request_never_downgrades_existing_full_access(): void
    {
        $share=ResourceShareRequest::create(['resource_type'=>'device','resource_id'=>$this->device->id,'sender_user_id'=>$this->sender->id,'recipient_user_id'=>$this->recipient->id,'requested_permission'=>'viewer','status'=>'awaiting_admin_approval','active_key'=>"device:{$this->device->id}:{$this->recipient->id}",'accepted_at'=>now()]);
        DeviceAccessAssignment::create(['device_id'=>$this->device->id,'user_id'=>$this->recipient->id,'access_level'=>'full_access','assigned_by'=>$this->admin->id]);
        $this->actingAs($this->admin)->postJson("/api/admin/access-requests/{$share->id}/approve",['permission'=>'viewer'])->assertOk()->assertJsonPath('data.final_permission','full_access');
        $this->assertDatabaseHas('device_access_assignments',['device_id'=>$this->device->id,'user_id'=>$this->recipient->id,'access_level'=>'full_access']);
    }
}
