<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Organization;
use App\Models\ResourceCollaborator;
use App\Models\ResourceRevision;
use App\Models\User;
use App\Services\ResourceLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ResourceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $org, bool $admin = false, string $email = 'staff@life.test'): User
    {
        return User::factory()->create(['organization_id' => $org->id, 'email' => $email, 'role' => 'owner', 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']);
    }

    private function fixture(): array
    {
        $org = Organization::create(['name' => 'Lifecycle Org', 'slug' => 'lifecycle-org-'.uniqid()]);
        $staff = $this->user($org);
        $admin = $this->user($org, true, 'admin'.uniqid().'@life.test');
        $device = $this->actingAs($staff)->postJson('/api/devices', ['name' => 'Pump', 'type' => 'sensor', 'serialNumber' => 'LC-'.uniqid(), 'protocol' => 'mqtt'])->assertCreated()->json('id');
        $template = $this->actingAs($staff)->postJson('/api/device-templates', ['name' => 'Template'])->assertCreated()->json('data.id');

        return compact('org', 'staff', 'admin', 'device', 'template');
    }

    public function test_admin_transitions_device_without_touching_runtime_access_history_or_attention(): void
    {
        extract($this->fixture());
        $revisionCount = ResourceRevision::count();
        $status = Device::findOrFail($device)->status;
        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$device}/attention", ['reason_code' => 'other', 'note' => 'Retirement check.']);
        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$device}/lifecycle/disable")->assertOk()->assertJsonPath('data.state', 'disabled');
        $this->assertDatabaseHas('device_access_assignments', ['device_id' => $device, 'user_id' => $staff->id, 'access_level' => 'full_access']);
        $this->assertDatabaseHas('resource_attention_states', ['resource_type' => 'device', 'resource_id' => $device, 'status' => 'open']);
        $this->assertDatabaseHas('devices', ['id' => $device, 'status' => $status]);
        $this->assertSame($revisionCount, ResourceRevision::count());
        $this->actingAs($staff)->getJson("/api/devices/{$device}")->assertOk()->assertJsonPath('capabilities.canEdit', false);
        $this->actingAs($staff)->getJson('/api/my-work/needs-attention')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($staff)->patchJson("/api/devices/{$device}", ['name' => 'Blocked'])->assertStatus(409);
        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$device}/lifecycle/restore")->assertOk()->assertJsonPath('data.state', 'active');
        $this->actingAs($staff)->patchJson("/api/devices/{$device}", ['name' => 'Restored'])->assertOk();
    }

    public function test_staff_and_full_access_cannot_transition_and_unknown_type_is_rejected(): void
    {
        extract($this->fixture());
        $this->actingAs($staff)->postJson("/api/admin/resources/device/{$device}/lifecycle/disable")->assertForbidden();
        $this->actingAs($admin)->postJson('/api/admin/resources/blueprint/1/lifecycle/disable')->assertUnprocessable();
    }

    public function test_template_disable_blocks_edit_share_submit_and_catalog_but_preserves_records(): void
    {
        extract($this->fixture());
        $revisions = ResourceRevision::where(['resource_type' => 'device_template', 'resource_id' => $template])->count();
        $collaborators = ResourceCollaborator::where(['resource_type' => 'device_template', 'resource_id' => $template])->count();
        $this->actingAs($admin)->postJson("/api/admin/resources/device_template/{$template}/lifecycle/disable")->assertOk();
        $this->actingAs($staff)->getJson("/api/device-templates/{$template}")->assertOk();
        $this->actingAs($staff)->patchJson("/api/device-templates/{$template}", ['name' => 'Blocked'])->assertNotFound();
        $this->actingAs($staff)->postJson('/api/shares', ['resource_type' => 'device_template', 'resource_id' => $template, 'recipient_id' => $admin->id, 'permission' => 'view'])->assertNotFound();
        $this->actingAs($staff)->postJson("/api/device-templates/{$template}/publication-submissions")->assertStatus(409);
        $this->assertSame($revisions, ResourceRevision::where(['resource_type' => 'device_template', 'resource_id' => $template])->count());
        $this->assertSame($collaborators, ResourceCollaborator::where(['resource_type' => 'device_template', 'resource_id' => $template])->count());
    }

    public function test_archive_and_restore_are_explicit_idempotent_transitions(): void
    {
        extract($this->fixture());
        $this->actingAs($admin)->postJson("/api/admin/resources/device_template/{$template}/lifecycle/archive")->assertOk()->assertJsonPath('data.state', 'archived');
        $this->actingAs($admin)->postJson("/api/admin/resources/device_template/{$template}/lifecycle/archive")->assertOk()->assertJsonPath('data.state', 'archived');
        $this->actingAs($staff)->getJson("/api/resources/device_template/{$template}/lifecycle")->assertOk()->assertJsonPath('data.state', 'archived');
        $this->actingAs($admin)->postJson("/api/admin/resources/device_template/{$template}/lifecycle/restore")->assertOk()->assertJsonPath('data.state', 'active');
    }

    public function test_creation_defaults_active_and_lifecycle_does_not_create_conflicting_domain_status(): void
    {
        extract($this->fixture());
        $this->actingAs($staff)->getJson("/api/resources/device/{$device}/lifecycle")->assertOk()->assertJsonPath('data.state', 'active');
        $this->actingAs($staff)->getJson("/api/resources/device_template/{$template}/lifecycle")->assertOk()->assertJsonPath('data.state', 'active');
        $this->assertDatabaseMissing('resource_lifecycle_states', ['resource_type' => 'device', 'resource_id' => $device]);
    }

    public function test_absolute_transitions_advance_generation_only_once_and_reject_stale_expected_epochs(): void
    {
        extract($this->fixture());

        $disabled = $this->actingAs($admin)->postJson("/api/admin/resources/device/{$device}/lifecycle/disable", [
            'expectedLifecycle' => 'active',
            'lifecycleGeneration' => 1,
        ])->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.lifecycleGeneration', 2);

        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$device}/lifecycle/disable", [
            'expectedLifecycle' => 'active',
            'lifecycleGeneration' => 1,
        ])->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.lifecycleGeneration', 2);

        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$device}/lifecycle/restore", [
            'expectedLifecycle' => 'active',
            'lifecycleGeneration' => 1,
        ])->assertUnprocessable();

        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$device}/lifecycle/restore", [
            'expectedLifecycle' => 'disabled',
            'lifecycleGeneration' => $disabled->json('data.lifecycleGeneration'),
        ])->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.lifecycleGeneration', 3);

        $this->assertFalse(app(ResourceLifecycleService::class)->allowsGeneration('device', $device, 1));
        $this->assertTrue(app(ResourceLifecycleService::class)->allowsGeneration('device', $device, 3));
    }

    public function test_transition_metadata_and_side_effect_injection_are_rejected(): void
    {
        extract($this->fixture());

        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$device}/lifecycle/disable", [
            'lifecycle_generation' => 99,
            'disabled_by' => $staff->id,
            'reactivate' => true,
            'regrant' => true,
            'resumeOldJobs' => true,
            'resetGeneration' => true,
            'target' => 'active',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('resource_lifecycle_states', [
            'resource_type' => 'device',
            'resource_id' => $device,
        ]);
    }
}
