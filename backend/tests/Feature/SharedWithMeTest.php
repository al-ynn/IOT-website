<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\DeviceTemplate;
use App\Models\Dashboard;
use App\Models\Automation;
use App\Models\Report;
use App\Models\Webhook;
use App\Models\Location;
use App\Models\FirmwareArtifact;
use App\Models\Organization;
use App\Models\ResourceCollaborator;
use App\Models\ResourceLifecycleState;
use App\Models\ResourceShareRequest;
use App\Models\User;
use App\Models\UserMonitoredDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Services\SharedWithMeService;
use Tests\TestCase;

final class SharedWithMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_count_and_preview_do_not_execute_hidden_paginator_queries(): void
    {
        $org = Organization::create(['name' => 'Shared Performance', 'slug' => 'shared-performance-'.uniqid()]);
        $owner = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $recipient = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        for ($index = 0; $index < 10; $index++) {
            $template = DeviceTemplate::create(['organization_id' => $org->id, 'created_by' => $owner->id, 'name' => 'Shared '.$index]);
            ResourceCollaborator::create(['resource_type' => 'device_template', 'resource_id' => $template->id, 'user_id' => $recipient->id, 'permission' => 'view', 'granted_by' => $owner->id]);
        }

        $service = app(SharedWithMeService::class);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->assertSame(10, $service->count($recipient));
        $countQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->assertCount(5, $service->preview($recipient));
        $previewQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        if (getenv('PHASE102_REPORT')) fwrite(STDERR, "PHASE102 shared_count_queries={$countQueries} shared_preview_queries={$previewQueries}\n");

        $this->assertSame(1, $countQueries);
        $this->assertSame(1, $previewQueries);
    }

    public function test_projection_uses_only_current_direct_grants_and_excludes_self_created_resources(): void
    {
        $org = Organization::create(['name' => 'Shared Org', 'slug' => 'shared-'.uniqid()]);
        $owner = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $recipient = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $device = Device::create(['organization_id' => $org->id, 'created_by' => $owner->id, 'name' => 'Shared Pump', 'external_id' => 'SWM-'.uniqid(), 'status' => 'offline', 'type' => 'sensor', 'protocol' => 'mqtt']);
        DeviceAccessAssignment::create(['device_id' => $device->id, 'user_id' => $recipient->id, 'access_level' => 'viewer', 'assigned_by' => $owner->id]);
        $own = Device::create(['organization_id' => $org->id, 'created_by' => $recipient->id, 'name' => 'Own Pump', 'external_id' => 'OWN-'.uniqid(), 'status' => 'offline', 'type' => 'sensor', 'protocol' => 'mqtt']);
        DeviceAccessAssignment::create(['device_id' => $own->id, 'user_id' => $recipient->id, 'access_level' => 'full_access', 'assigned_by' => $recipient->id]);
        ResourceShareRequest::create(['resource_type' => 'device', 'resource_id' => $own->id, 'sender_user_id' => $owner->id, 'recipient_user_id' => $recipient->id, 'requested_permission' => 'viewer', 'status' => 'pending_recipient']);

        $this->actingAs($recipient)->getJson('/api/shared-with-me')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.resourceType', 'device')
            ->assertJsonPath('data.0.resourceId', (string) $device->id)
            ->assertJsonPath('data.0.accessKey', 'viewer')
            ->assertJsonMissing(['label' => 'Own Pump']);
    }

    public function test_non_device_grants_are_current_lifecycle_aware_revocation_aware_and_paginated(): void
    {
        $org = Organization::create(['name' => 'Shared Org', 'slug' => 'shared-'.uniqid()]);
        $owner = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $recipient = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $template = DeviceTemplate::create(['organization_id' => $org->id, 'created_by' => $owner->id, 'name' => 'Shared Template']);
        $grant = ResourceCollaborator::create(['resource_type' => 'device_template', 'resource_id' => $template->id, 'user_id' => $recipient->id, 'permission' => 'edit', 'granted_by' => $owner->id]);
        ResourceLifecycleState::create(['resource_type' => 'device_template', 'resource_id' => $template->id, 'state' => 'disabled', 'lifecycle_generation' => 2]);

        $this->actingAs($recipient)->getJson('/api/shared-with-me?resource_type=device_template&lifecycle=disabled&q=Shared')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.accessKey', 'edit')
            ->assertJsonPath('data.0.lifecycle', 'disabled')
            ->assertJsonMissingPath('data.0.grantId');

        $grant->delete();
        $this->actingAs($recipient)->getJson('/api/shared-with-me')->assertOk()->assertJsonPath('total', 0);
        $this->actingAs($recipient)->getJson('/api/shared-with-me?user_id='.$owner->id)->assertUnprocessable();
    }

    public function test_device_projection_ignores_workflow_admin_and_monitoring_state_and_reflects_current_assignment(): void
    {
        $org = Organization::create(['name' => 'Device Projection', 'slug' => 'device-projection-'.uniqid()]);
        $owner = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $recipient = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $admin = User::factory()->create(['organization_id' => $org->id, 'status' => 'active', 'platform_role' => 'platform_admin']);
        $pending = Device::create(['organization_id' => $org->id, 'created_by' => $owner->id, 'name' => 'Pending Device', 'external_id' => 'PENDING-'.uniqid(), 'status' => 'offline', 'type' => 'sensor', 'protocol' => 'mqtt']);
        $assigned = Device::create(['organization_id' => $org->id, 'created_by' => $owner->id, 'name' => 'Assigned Device', 'external_id' => 'ASSIGNED-'.uniqid(), 'status' => 'offline', 'type' => 'sensor', 'protocol' => 'mqtt']);
        ResourceShareRequest::create(['resource_type' => 'device', 'resource_id' => $pending->id, 'sender_user_id' => $owner->id, 'recipient_user_id' => $recipient->id, 'requested_permission' => 'full_access', 'status' => 'awaiting_admin_approval', 'active_key' => "device:{$pending->id}:{$recipient->id}", 'accepted_at' => now()]);
        UserMonitoredDevice::create(['user_id' => $admin->id, 'device_id' => $pending->id]);

        $this->actingAs($recipient)->getJson('/api/shared-with-me')->assertOk()->assertJsonPath('total', 0);
        $this->actingAs($admin)->getJson('/api/shared-with-me')->assertOk()->assertJsonPath('total', 0);

        $grant = DeviceAccessAssignment::create(['device_id' => $assigned->id, 'user_id' => $recipient->id, 'access_level' => 'full_access', 'assigned_by' => $owner->id]);
        $this->actingAs($recipient)->getJson('/api/shared-with-me')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.accessKey', 'full_access')
            ->assertJsonPath('data.0.accessLabel', 'Full Access');
        $grant->update(['access_level' => 'viewer']);
        $this->actingAs($recipient)->getJson('/api/shared-with-me')->assertOk()
            ->assertJsonPath('data.0.accessKey', 'viewer')->assertJsonPath('data.0.accessLabel', 'Viewer');
        $grant->delete();
        $this->actingAs($recipient)->getJson('/api/shared-with-me')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_every_real_non_device_domain_uses_direct_grants_and_excludes_self_and_nonpersonal_dashboards(): void
    {
        $org = Organization::create(['name' => 'Domain Projection', 'slug' => 'domain-projection-'.uniqid()]);
        $owner = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $recipient = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $resources = [
            'device_template' => DeviceTemplate::create(['organization_id' => $org->id, 'created_by' => $owner->id, 'name' => 'Shared Template']),
            'dashboard' => Dashboard::create(['organization_id' => $org->id, 'owner_user_id' => $owner->id, 'created_by' => $owner->id, 'name' => 'Shared Dashboard', 'scope_type' => 'personal']),
            'automation' => Automation::create(['organization_id' => $org->id, 'created_by' => $owner->id, 'updated_by' => $owner->id, 'name' => 'Shared Automation']),
            'report' => Report::create(['organization_id' => $org->id, 'created_by' => $owner->id, 'updated_by' => $owner->id, 'name' => 'Shared Report', 'report_type' => 'device_summary', 'configuration' => []]),
            'webhook' => Webhook::create(['organization_id' => $org->id, 'created_by' => $owner->id, 'name' => 'Shared Webhook', 'url' => 'https://example.test/hook', 'event_types' => ['automation.failed'], 'signing_secret' => 'never-return', 'secret_prefix' => 'never']),
            'location' => Location::create(['organization_id' => $org->id, 'created_by' => $owner->id, 'name' => 'Shared Location', 'normalized_name' => 'shared location']),
            'firmware' => FirmwareArtifact::create(['organization_id' => $org->id, 'uploaded_by' => $owner->id, 'name' => 'Shared Firmware', 'version' => '1.0.0', 'storage_disk' => 'local', 'storage_path' => 'firmware/shared.bin', 'original_filename' => 'shared.bin', 'mime_type' => 'application/octet-stream', 'size_bytes' => 1, 'sha256' => str_repeat('a', 64)]),
        ];
        foreach ($resources as $type => $resource) {
            ResourceCollaborator::create(['resource_type' => $type, 'resource_id' => $resource->id, 'user_id' => $recipient->id, 'permission' => $type === 'report' ? 'edit' : 'view', 'granted_by' => $owner->id]);
        }
        $own = DeviceTemplate::create(['organization_id' => $org->id, 'created_by' => $recipient->id, 'name' => 'Own Template']);
        $deviceDashboard = Dashboard::create(['organization_id' => $org->id, 'created_by' => $owner->id, 'name' => 'Subordinate Dashboard', 'scope_type' => 'device']);
        ResourceCollaborator::create(['resource_type' => 'dashboard', 'resource_id' => $deviceDashboard->id, 'user_id' => $recipient->id, 'permission' => 'view', 'granted_by' => $owner->id]);

        $response = $this->actingAs($recipient)->getJson('/api/shared-with-me')->assertOk()
            ->assertJsonPath('total', 7)->assertJsonMissing(['label' => 'Own Template'])
            ->assertJsonMissing(['label' => 'Subordinate Dashboard'])
            ->assertJsonMissing(['signing_secret' => 'never-return']);
        $this->assertEqualsCanonicalizing(array_keys($resources), array_column($response->json('data'), 'resourceType'));
        $this->actingAs($recipient)->getJson('/api/shared-with-me?permission=edit')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.resourceType', 'report');
    }

    public function test_reference_visibility_strict_parameters_literal_query_and_database_pagination_do_not_create_access(): void
    {
        $org = Organization::create(['name' => 'Strict Projection', 'slug' => 'strict-projection-'.uniqid()]);
        $owner = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $recipient = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $location = Location::create(['organization_id' => $org->id, 'created_by' => $owner->id, 'name' => 'Reference Only', 'normalized_name' => 'reference only']);
        $device = Device::create(['organization_id' => $org->id, 'created_by' => $owner->id, 'location_id' => $location->id, 'name' => 'Literal %_ Pump', 'external_id' => 'LITERAL-'.uniqid(), 'status' => 'offline', 'type' => 'sensor', 'protocol' => 'mqtt']);
        DeviceAccessAssignment::create(['device_id' => $device->id, 'user_id' => $recipient->id, 'access_level' => 'viewer', 'assigned_by' => $owner->id]);
        for ($index = 0; $index < 10; $index++) {
            $template = DeviceTemplate::create(['organization_id' => $org->id, 'created_by' => $owner->id, 'name' => 'Page Template '.$index]);
            ResourceCollaborator::create(['resource_type' => 'device_template', 'resource_id' => $template->id, 'user_id' => $recipient->id, 'permission' => 'view', 'granted_by' => $owner->id]);
        }

        $this->actingAs($recipient)->getJson('/api/shared-with-me?q=%25_')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.resourceType', 'device');
        $this->actingAs($recipient)->getJson('/api/shared-with-me?resource_type=location')->assertOk()->assertJsonPath('total', 0);
        $this->actingAs($recipient)->getJson('/api/shared-with-me?per_page=10&page=1')->assertOk()
            ->assertJsonPath('total', 11)->assertJsonCount(10, 'data')->assertJsonPath('last_page', 2);
        $this->actingAs($recipient)->getJson('/api/shared-with-me?per_page=10&page=2')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($recipient)->getJson('/api/shared-with-me?q=x')->assertUnprocessable();
        $this->actingAs($recipient)->getJson('/api/shared-with-me?admin=true')->assertUnprocessable();
        $this->actingAs($recipient)->getJson('/api/shared-with-me?resource_type=App%5CModels%5CDevice')->assertUnprocessable();
        $this->assertDatabaseCount('resource_share_requests', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('resource_revisions', 0);
    }
}
