<?php

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\DeviceCredential;
use App\Models\DeviceParameter;
use App\Models\DeviceTemplate;
use App\Models\DeviceTemplateParameter;
use App\Models\Organization;
use App\Models\ResourceCollaborator;
use App\Models\ResourceLifecycleState;
use App\Models\ResourcePublicationState;
use App\Models\ResourcePublicationVersion;
use App\Models\ResourceRevision;
use App\Models\ResourceRevisionState;
use App\Models\ResourceShareRequest;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CrossDomainSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_is_authenticated_personal_authorized_and_strictly_validated(): void
    {
        [$org, $owner, $viewer] = $this->actors();
        $visible = $this->device($org, $owner, 'Assigned Sensor', 'ASSIGNED-42');
        DeviceAccessAssignment::create(['device_id' => $visible->id, 'user_id' => $viewer->id, 'access_level' => 'viewer', 'assigned_by' => $owner->id]);
        $hidden = $this->device($org, $owner, 'Hidden Sensor', 'HIDDEN-42');
        $request = ResourceShareRequest::create([
            'resource_type' => 'device',
            'resource_id' => $hidden->id,
            'sender_user_id' => $owner->id,
            'recipient_user_id' => $viewer->id,
            'requested_permission' => 'viewer',
            'status' => 'pending_recipient',
            'active_key' => "device:{$hidden->id}:{$viewer->id}",
        ]);
        $admin = User::factory()->create([
            'organization_id' => $org->id,
            'role' => 'staff',
            'platform_role' => 'platform_admin',
            'status' => 'active',
        ]);

        $this->getJson('/api/search?q=Sensor')->assertUnauthorized();
        $this->actingAs($viewer)->getJson('/api/search?q=Assigned')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.resourceId', (string) $visible->id);
        $this->actingAs($viewer)->getJson('/api/search?q=Hidden')->assertOk()->assertJsonPath('total', 0);
        $request->update(['status' => 'awaiting_admin_approval', 'accepted_at' => now()]);
        $this->actingAs($viewer)->getJson('/api/search?q=Hidden')->assertOk()->assertJsonPath('total', 0);
        $this->actingAs($admin)->getJson('/api/search?q=Assigned')->assertOk()->assertJsonPath('total', 0);
        $this->actingAs($viewer)->getJson('/api/search?q='.$hidden->id.'&user_id='.$owner->id)->assertUnprocessable();
        $this->actingAs($viewer)->getJson('/api/search?q=xx&global=true')->assertUnprocessable();
        $this->actingAs($viewer)->getJson('/api/search?q=xx&resource_type=App%5CModels%5CUser')->assertUnprocessable();
        $this->actingAs($viewer)->getJson('/api/search?q=x')->assertUnprocessable();
        $this->actingAs($viewer)->getJson('/api/search?q='.str_repeat('x', 101))->assertUnprocessable();
    }

    public function test_safe_children_match_parent_and_secrets_runtime_and_literal_wildcards_do_not_match(): void
    {
        [$org, $owner, $viewer] = $this->actors();
        $device = $this->device($org, $owner, 'Pump Alpha', 'PUMP_%_LITERAL');
        DeviceAccessAssignment::create(['device_id' => $device->id, 'user_id' => $viewer->id, 'access_level' => 'full_access', 'assigned_by' => $owner->id]);
        DeviceParameter::create(['device_id' => $device->id, 'name' => 'Ambient Temperature', 'key' => 'ambient_temperature', 'data_type' => 'number', 'unit' => 'C']);
        DeviceCredential::create(['device_id' => $device->id, 'public_id' => (string) Str::uuid(), 'name' => 'Credential', 'token_prefix' => 'iotd_safe', 'token_hash' => hash('sha256', 'never-search-this-token'), 'scopes' => ['telemetry:write'], 'created_by' => $owner->id]);

        $template = DeviceTemplate::create(['organization_id' => $org->id, 'created_by' => $owner->id, 'name' => 'Boiler Profile']);
        ResourceCollaborator::create(['resource_type' => 'device_template', 'resource_id' => $template->id, 'user_id' => $viewer->id, 'permission' => 'view', 'granted_by' => $owner->id]);
        DeviceTemplateParameter::create(['device_template_id' => $template->id, 'name' => 'Pressure Limit', 'key' => 'pressure_limit', 'data_type' => 'number']);

        $this->actingAs($viewer)->getJson('/api/search?q=ambient_temperature')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.resourceType', 'device')
            ->assertJsonPath('data.0.matchKind', 'child_metadata');
        $this->actingAs($viewer)->getJson('/api/search?q=Pressure%20Limit')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.resourceType', 'device_template');
        $this->actingAs($viewer)->getJson('/api/search?q=never-search-this-token')->assertOk()->assertJsonPath('total', 0);
        $this->actingAs($viewer)->getJson('/api/search?q=PUMP_%25_')->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($viewer)->getJson('/api/search?q=%25')->assertUnprocessable();
    }

    public function test_device_dashboard_search_uses_the_current_users_accepted_revision(): void
    {
        [$org, $owner, $viewer] = $this->actors();
        $device = $this->device($org, $owner, 'Revision Sensor', 'REV-1');
        DeviceAccessAssignment::create(['device_id' => $device->id, 'user_id' => $viewer->id, 'access_level' => 'viewer', 'assigned_by' => $owner->id]);
        $accepted = $this->revision('device', $device->id, 1, $owner, 'Cooling Profile');
        $latest = $this->revision('device', $device->id, 2, $owner, 'Thermal Control Profile', $accepted);
        ResourceRevisionState::create([
            'resource_type' => 'device', 'resource_id' => $device->id, 'user_id' => $viewer->id,
            'accepted_revision_id' => $accepted->id, 'seen_latest_revision_id' => $accepted->id,
            'last_reviewed_revision_id' => $accepted->id,
        ]);

        $this->actingAs($viewer)->getJson('/api/search?q=Cooling%20Profile')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.matchKind', 'child_metadata');
        $this->actingAs($viewer)->getJson('/api/search?q=Thermal%20Control')->assertOk()->assertJsonPath('total', 0);

        ResourceRevisionState::where('user_id', $viewer->id)->update(['accepted_revision_id' => $latest->id]);
        $this->actingAs($viewer)->getJson('/api/search?q=Thermal%20Control')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_independent_dashboard_owner_is_search_authorized_without_a_redundant_grant(): void
    {
        [$org, $other, $owner] = $this->actors();
        $owned = Dashboard::create([
            'organization_id' => $org->id, 'owner_user_id' => $owner->id, 'created_by' => $owner->id,
            'updated_by' => $owner->id, 'name' => 'Owner Search Dashboard', 'scope_type' => 'personal',
        ]);
        Dashboard::create([
            'organization_id' => $org->id, 'owner_user_id' => $other->id, 'created_by' => $other->id,
            'updated_by' => $other->id, 'name' => 'Hidden Search Dashboard', 'scope_type' => 'personal',
        ]);

        $this->actingAs($owner)->getJson('/api/search?q=Owner%20Search')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.resourceId', (string) $owned->id)
            ->assertJsonPath('data.0.accessMode', 'edit');
        $this->actingAs($owner)->getJson('/api/search?q=Hidden%20Search')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_published_dashboard_uses_pinned_revision_and_private_grant_deduplicates(): void
    {
        [$org, $owner, $viewer] = $this->actors();
        $dashboard = Dashboard::create([
            'organization_id' => $org->id, 'owner_user_id' => $owner->id, 'created_by' => $owner->id,
            'updated_by' => $owner->id, 'name' => 'Private New Name', 'description' => 'Private New Description',
            'scope_type' => 'personal',
        ]);
        $published = $this->revision('dashboard', $dashboard->id, 1, $owner, 'Published Gauge');
        $private = $this->revision('dashboard', $dashboard->id, 2, $owner, 'Private Future Gauge', $published);
        $version = ResourcePublicationVersion::create([
            'resource_type' => 'dashboard', 'resource_id' => $dashboard->id, 'publication_number' => 1,
            'resource_revision_id' => $published->id, 'published_by' => $owner->id,
            'published_at' => now(), 'created_at' => now(),
        ]);
        ResourcePublicationState::create([
            'resource_type' => 'dashboard', 'resource_id' => $dashboard->id,
            'approved_revision_id' => $published->id, 'current_publication_version_id' => $version->id,
        ]);

        $this->actingAs($viewer)->getJson('/api/search?q=Published%20Gauge')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.accessMode', 'published')
            ->assertJsonPath('data.0.destination', '/app/dashboard/published/'.$dashboard->id);
        $this->actingAs($viewer)->getJson('/api/search?q=Private%20Future')->assertOk()->assertJsonPath('total', 0);

        ResourceCollaborator::create(['resource_type' => 'dashboard', 'resource_id' => $dashboard->id, 'user_id' => $viewer->id, 'permission' => 'view', 'granted_by' => $owner->id]);
        $dashboard->update(['name' => 'Published Gauge']);
        $this->actingAs($viewer)->getJson('/api/search?q=Published%20Gauge')->assertOk()->assertJsonPath('total', 1);
        $this->assertSame(2, ResourceRevision::where(['resource_type' => 'dashboard', 'resource_id' => $dashboard->id])->count());
        $this->assertSame($published->id, ResourcePublicationState::firstOrFail()->approved_revision_id);
        $this->assertSame($private->id, ResourceRevision::latest('id')->firstOrFail()->id);
    }

    public function test_lifecycle_ranking_pagination_revocation_and_webhook_secret_safety(): void
    {
        [$org, $owner, $viewer] = $this->actors();
        $exact = $this->device($org, $owner, 'Ranked Sensor', 'RANK-1');
        $prefix = $this->device($org, $owner, 'Ranked Sensor Extended', 'RANK-2');
        foreach ([$exact, $prefix] as $device) {
            DeviceAccessAssignment::create(['device_id' => $device->id, 'user_id' => $viewer->id, 'access_level' => 'viewer', 'assigned_by' => $owner->id]);
        }
        ResourceLifecycleState::create(['resource_type' => 'device', 'resource_id' => $exact->id, 'state' => 'disabled']);
        $webhook = Webhook::create([
            'organization_id' => $org->id, 'name' => 'Operations Hook', 'url' => 'https://secret-endpoint.example.test/private',
            'enabled' => false, 'event_types' => ['automation.failed'], 'signing_secret' => 'never-search-webhook-secret',
            'secret_prefix' => 'never', 'created_by' => $owner->id,
        ]);
        $grant = ResourceCollaborator::create(['resource_type' => 'webhook', 'resource_id' => $webhook->id, 'user_id' => $viewer->id, 'permission' => 'view', 'granted_by' => $owner->id]);

        $response = $this->actingAs($viewer)->getJson('/api/search?q=Ranked%20Sensor&per_page=10')->assertOk();
        $response->assertJsonPath('total', 2)->assertJsonPath('data.0.resourceId', (string) $exact->id);
        $this->actingAs($viewer)->getJson('/api/search?q=Ranked&lifecycle=disabled')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.lifecycle', 'disabled');
        $this->actingAs($viewer)->getJson('/api/search?q=secret-endpoint')->assertOk()->assertJsonPath('total', 0);
        $this->actingAs($viewer)->getJson('/api/search?q=never-search-webhook-secret')->assertOk()->assertJsonPath('total', 0);

        $grant->delete();
        $this->actingAs($viewer)->getJson('/api/search?q=Operations')->assertOk()->assertJsonPath('total', 0);
    }

    private function actors(): array
    {
        $org = Organization::create(['name' => 'Search Org', 'slug' => 'search-'.uniqid()]);
        $owner = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $viewer = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        return [$org, $owner, $viewer];
    }

    private function device(Organization $org, User $owner, string $name, string $externalId): Device
    {
        return Device::create([
            'organization_id' => $org->id, 'created_by' => $owner->id, 'name' => $name,
            'external_id' => $externalId, 'status' => 'offline', 'type' => 'sensor', 'protocol' => 'mqtt',
        ]);
    }

    private function revision(string $type, int $id, int $number, User $author, string $widgetTitle, ?ResourceRevision $parent = null): ResourceRevision
    {
        $snapshot = [
            'metadata' => ['name' => $widgetTitle, 'description' => $widgetTitle],
            'dashboard' => ['widgets' => [['id' => (string) Str::uuid(), 'type' => 'status', 'title' => $widgetTitle]]],
            'parameters' => [],
        ];
        return ResourceRevision::create([
            'resource_type' => $type, 'resource_id' => $id, 'revision_number' => $number,
            'parent_revision_id' => $parent?->id, 'created_by' => $author->id,
            'change_summary' => 'Search fixture', 'snapshot_schema_version' => 1,
            'snapshot' => $snapshot, 'changed_sections' => ['dashboard'],
            'checksum' => hash('sha256', json_encode($snapshot).$number), 'created_at' => now(),
        ]);
    }
}
