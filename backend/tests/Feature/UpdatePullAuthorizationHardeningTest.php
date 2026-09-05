<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\Organization;
use App\Models\ResourceRevision;
use App\Models\ResourceRevisionState;
use App\Models\User;
use App\Services\Admin\DeviceAccessService;
use App\Services\ResourceLifecycleService;
use App\Services\ResourceRevisionStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class UpdatePullAuthorizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_accepted_pointer_fails_closed_and_cannot_be_used_to_pull(): void
    {
        [$device, $author, $viewer] = $this->pending('P-79-A');
        [$foreign] = $this->pending('P-79-B');
        $foreignRevision = ResourceRevision::query()->where('resource_type', 'device')->where('resource_id', $foreign->id)->latest('revision_number')->firstOrFail();
        ResourceRevisionState::query()->where(['resource_type' => 'device', 'resource_id' => $device->id, 'user_id' => $viewer->id])
            ->update(['accepted_revision_id' => $foreignRevision->id]);

        $this->actingAs($viewer)->getJson($this->base($device).'/revision-state')->assertStatus(409);
        $this->actingAs($viewer)->postJson($this->base($device).'/pull')->assertStatus(409);
    }

    public function test_pull_is_server_latest_forward_only_and_idempotent(): void
    {
        [$device, $author, $viewer] = $this->pending('P-79-C');
        $this->save($author, $device, 'Newest', 2)->assertOk();
        $latest = ResourceRevision::query()->where('resource_type', 'device')->where('resource_id', $device->id)->latest('revision_number')->firstOrFail();
        $count = ResourceRevision::count();

        $this->actingAs($viewer)->postJson($this->base($device).'/pull', [
            'target_revision_id' => $latest->parent_revision_id,
            'accepted_revision_id' => $latest->parent_revision_id,
            'user_id' => $author->id,
        ])->assertUnprocessable();

        $this->actingAs($viewer)->postJson($this->base($device).'/pull')
            ->assertOk()->assertJsonPath('acceptedRevision.id', (string) $latest->id);
        $this->actingAs($viewer)->postJson($this->base($device).'/pull')
            ->assertOk()->assertJsonPath('acceptedRevision.id', (string) $latest->id);
        $this->assertSame($count, ResourceRevision::count());
    }

    public function test_disabled_resource_blocks_state_review_pull_ignore_and_reminder(): void
    {
        [$device, $author, $viewer] = $this->pending('P-79-D');
        $admin = User::factory()->create(['organization_id' => $device->organization_id, 'role' => 'admin', 'platform_role' => 'platform_admin', 'status' => 'active']);
        app(ResourceLifecycleService::class)->disable($admin, 'device', $device->id);

        $this->actingAs($viewer)->getJson($this->base($device).'/revision-state')->assertStatus(409);
        $this->actingAs($viewer)->getJson($this->base($device).'/review-changes')->assertStatus(409);
        $this->actingAs($viewer)->postJson($this->base($device).'/pull')->assertStatus(409);
        $this->actingAs($viewer)->postJson($this->base($device).'/ignore')->assertStatus(409);
        $this->actingAs($viewer)->postJson($this->base($device).'/reminder', ['preset' => 'one_hour'])->assertStatus(409);
        $this->actingAs($viewer)->getJson('/api/changes')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_regrant_resets_pointer_to_current_and_does_not_resurrect_backlog(): void
    {
        [$device, $author, $viewer] = $this->pending('P-79-E');
        $assignment = DeviceAccessAssignment::query()->where(['device_id' => $device->id, 'user_id' => $viewer->id])->firstOrFail();
        app(DeviceAccessService::class)->delete($assignment, $author);
        $this->save($author, $device, 'During revoke', 2)->assertOk();

        app(DeviceAccessService::class)->create($author, $device->id, $viewer->id, 'viewer', false);
        $this->actingAs($viewer)->getJson($this->base($device).'/revision-state')
            ->assertOk()->assertJsonPath('updateAvailable', false)
            ->assertJsonPath('acceptedRevision.revisionNumber', 3);
        $this->actingAs($viewer)->getJson('/api/changes')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_immediate_and_unsafe_to_pin_types_have_no_pull_surface(): void
    {
        [$device,, $viewer] = $this->pending('P-79-F');
        foreach (['dashboard', 'location', 'device_template', 'automation', 'report', 'webhook', 'firmware'] as $type) {
            $this->actingAs($viewer)->getJson("/api/collaboration/resources/$type/$device->id/revision-state")->assertNotFound();
            $this->actingAs($viewer)->postJson("/api/collaboration/resources/$type/$device->id/pull")->assertNotFound();
        }
    }

    public function test_ignore_requires_a_real_pending_update_and_never_accepts(): void
    {
        [$device,, $viewer] = $this->pending('P-79-G');
        $this->actingAs($viewer)->postJson($this->base($device).'/pull')->assertOk();
        $accepted = ResourceRevisionState::query()->where(['resource_type' => 'device', 'resource_id' => $device->id, 'user_id' => $viewer->id])->value('accepted_revision_id');

        $this->actingAs($viewer)->postJson($this->base($device).'/ignore')->assertStatus(409);
        $this->assertSame($accepted, ResourceRevisionState::query()->where(['resource_type' => 'device', 'resource_id' => $device->id, 'user_id' => $viewer->id])->value('accepted_revision_id'));
    }

    private function pending(string $serial): array
    {
        $organization = Organization::create(['name' => $serial, 'slug' => strtolower($serial)]);
        $author = $this->user($organization, "author-$serial@test");
        $viewer = $this->user($organization, "viewer-$serial@test");
        $id = $this->actingAs($author)->postJson('/api/devices', [
            'name' => 'Pump', 'type' => 'sensor', 'serialNumber' => $serial, 'protocol' => 'mqtt',
        ])->assertCreated()->json('id');
        $device = Device::findOrFail($id);
        DeviceAccessAssignment::create(['device_id' => $id, 'user_id' => $viewer->id, 'access_level' => 'viewer']);
        app(ResourceRevisionStateService::class)->initializeGrant($viewer, $device);
        $this->save($author, $device, 'Changed')->assertOk();

        return [$device, $author, $viewer];
    }

    private function save(User $user, Device $device, string $name, int $version = 1)
    {
        return $this->actingAs($user)->patchJson("/api/devices/$device->id/dashboard", [
            'name' => $name,
            'layoutVersion' => $version,
            'widgets' => [[
                'id' => (string) Str::uuid(), 'type' => 'status',
                'settings' => ['title' => 'Status', 'datasource' => ['deviceId' => (string) $device->id, 'telemetryKey' => '']],
                'layout' => ['x' => 0, 'y' => 0, 'w' => 3, 'h' => 3],
            ]],
        ]);
    }

    private function base(Device $device): string
    {
        return "/api/collaboration/resources/device/$device->id";
    }

    private function user(Organization $organization, string $email): User
    {
        return User::factory()->create([
            'organization_id' => $organization->id, 'email' => $email, 'role' => 'staff', 'status' => 'active',
        ]);
    }
}
