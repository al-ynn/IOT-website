<?php

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\DeviceParameter;
use App\Models\Organization;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentRevisionAnchoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_semantic_anchor_contract_rejects_paths_and_server_derived_fields(): void
    {
        [$user, $device] = $this->context();

        $this->actingAs($user)->postJson("/api/collaboration/device/{$device->id}/threads", [
            'anchor_type' => 'section',
            'anchor_key' => 'parameters',
            'body' => 'Review parameters',
        ])->assertCreated()
            ->assertJsonPath('anchorContext.type', 'SECTION')
            ->assertJsonPath('anchorContext.current.focus', 'section:parameters');

        foreach (['css_selector', 'xpath', 'jsonpath', 'text_range', 'field_range'] as $type) {
            $this->actingAs($user)->postJson("/api/collaboration/device/{$device->id}/threads", [
                'anchor_type' => $type,
                'anchor_key' => '#form > div:nth-child(2)',
                'body' => 'Rejected',
            ])->assertUnprocessable();
        }

        foreach (['selector', 'path', 'currentLabel', 'changedSinceOrigin', 'anchor_schema_version'] as $field) {
            $this->actingAs($user)->postJson("/api/collaboration/device/{$device->id}/threads", [
                'body' => 'Rejected',
                $field => 'client-controlled',
            ])->assertUnprocessable();
        }
    }

    public function test_child_identity_is_parent_bound_and_removed_target_is_retained(): void
    {
        [$user, $device, $organization] = $this->context();
        $parameter = DeviceParameter::create([
            'device_id' => $device->id,
            'name' => 'Temperature',
            'key' => 'temperature',
            'data_type' => 'number',
        ]);
        $other = Device::create([
            'organization_id' => $organization->id,
            'name' => 'Other',
            'external_id' => 'other-anchor-device',
            'type' => 'sensor',
            'protocol' => 'mqtt',
        ]);
        $foreign = DeviceParameter::create([
            'device_id' => $other->id,
            'name' => 'Foreign',
            'key' => 'foreign',
            'data_type' => 'number',
        ]);

        $response = $this->actingAs($user)->postJson("/api/collaboration/device/{$device->id}/threads", [
            'anchor_type' => 'parameter',
            'anchor_key' => (string) $parameter->id,
            'body' => 'Stable identity',
        ])->assertCreated();
        $threadId = $response->json('id');

        $this->actingAs($user)->postJson("/api/collaboration/device/{$device->id}/threads", [
            'anchor_type' => 'parameter',
            'anchor_key' => (string) $foreign->id,
            'body' => 'Foreign child',
        ])->assertUnprocessable();

        $parameter->delete();

        $this->actingAs($user)->getJson("/api/collaboration/threads/{$threadId}")
            ->assertOk()
            ->assertJsonPath('anchorContext.current.exists', false)
            ->assertJsonPath('anchorContext.change.status', 'removed')
            ->assertJsonPath('anchorContext.change.message', 'Original context no longer exists in the current configuration.');
        $this->assertDatabaseHas('collaboration_threads', ['id' => $threadId, 'anchor_key' => (string) $parameter->id]);
    }

    public function test_origin_revision_must_belong_to_the_exact_resource_and_reply_cannot_reanchor(): void
    {
        [$user, $device, $organization] = $this->context();
        $origin = $this->revision($device, 1);
        $other = Device::create([
            'organization_id' => $organization->id,
            'name' => 'Other',
            'external_id' => 'other-revision-device',
            'type' => 'sensor',
            'protocol' => 'mqtt',
        ]);
        $foreign = $this->revision($other, 1);

        $threadId = $this->actingAs($user)->postJson("/api/collaboration/device/{$device->id}/threads", [
            'body' => 'Pinned',
            'origin_revision_id' => $origin->id,
        ])->assertCreated()
            ->assertJsonPath('anchorContext.origin.revisionId', (string) $origin->id)
            ->json('id');

        $this->actingAs($user)->postJson("/api/collaboration/device/{$device->id}/threads", [
            'body' => 'Foreign revision',
            'origin_revision_id' => $foreign->id,
        ])->assertNotFound();

        $this->actingAs($user)->postJson("/api/collaboration/threads/{$threadId}/comments", [
            'body' => 'Reply',
            'anchor_type' => 'section',
            'anchor_key' => 'metadata',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('collaboration_threads', [
            'id' => $threadId,
            'anchor_type' => 'resource',
            'anchor_revision_id' => $origin->id,
        ]);
    }

    public function test_dashboard_widget_reorder_and_rename_keep_the_same_anchor_identity(): void
    {
        [$user, $device, $organization] = $this->context();
        $dashboard = Dashboard::create([
            'organization_id' => $organization->id,
            'device_id' => $device->id,
            'name' => 'Operations',
            'scope' => 'device',
        ]);
        $widget = DashboardWidget::create([
            'id' => 'stable-widget',
            'dashboard_id' => $dashboard->id,
            'widget_type' => 'value',
            'title' => 'Temperature',
            'layout' => [],
            'configuration' => [],
            'position' => 0,
        ]);
        $threadId = $this->actingAs($user)->postJson("/api/collaboration/device/{$device->id}/threads", [
            'anchor_type' => 'dashboard_widget',
            'anchor_key' => $widget->id,
            'body' => 'Widget context',
        ])->assertCreated()->json('id');

        $widget->update(['title' => 'Ambient Temperature', 'position' => 9]);

        $this->actingAs($user)->getJson("/api/collaboration/threads/{$threadId}")
            ->assertOk()
            ->assertJsonPath('anchorContext.current.exists', true)
            ->assertJsonPath('anchorContext.current.label', 'Ambient Temperature')
            ->assertJsonPath('anchorContext.semanticKey', 'stable-widget');
    }

    private function context(): array
    {
        $organization = Organization::create(['name' => 'Plant', 'slug' => 'plant-anchor']);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'staff',
            'status' => 'active',
        ]);
        $device = Device::create([
            'organization_id' => $organization->id,
            'name' => 'Pump',
            'external_id' => 'anchor-device',
            'type' => 'sensor',
            'protocol' => 'mqtt',
        ]);
        DeviceAccessAssignment::create([
            'device_id' => $device->id,
            'user_id' => $user->id,
            'access_level' => 'viewer',
        ]);

        return [$user, $device, $organization];
    }

    private function revision(Device $device, int $number): ResourceRevision
    {
        $snapshot = ['metadata' => ['name' => $device->name], 'dashboard' => ['widgets' => []], 'parameters' => []];

        return ResourceRevision::create([
            'resource_type' => 'device',
            'resource_id' => $device->id,
            'revision_number' => $number,
            'change_summary' => "Revision {$number}",
            'snapshot_schema_version' => 1,
            'snapshot' => $snapshot,
            'changed_sections' => ['metadata'],
            'checksum' => hash('sha256', json_encode($snapshot)),
            'created_at' => now(),
        ]);
    }
}
