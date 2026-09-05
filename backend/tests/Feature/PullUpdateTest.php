<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\ResourceRevision;
use App\Models\User;
use App\Services\ResourceRevisionStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PullUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_revision_creates_update_review_ignore_and_atomic_pull_without_copy(): void
    {
        [$device,$author,$viewer] = $this->context();
        $this->saveDashboard($author, $device, 'Revision 2')->assertOk();
        $state = $this->actingAs($viewer)->getJson($this->stateUrl($device))->assertOk()->assertJsonPath('updateAvailable', true)->assertJsonPath('acceptedRevision.revisionNumber', 1)->assertJsonPath('latestRevision.revisionNumber', 2)->json();
        $accepted = $state['acceptedRevision']['id'];
        $this->actingAs($viewer)->getJson($this->base($device).'/review-changes')->assertOk()->assertJsonPath('fromRevision.revisionNumber', 1)->assertJsonPath('toRevision.revisionNumber', 2)->assertJsonPath('pullableChangedSections.0', 'dashboard');
        $this->actingAs($viewer)->postJson($this->base($device).'/ignore')->assertOk()->assertJsonPath('updateAvailable', true)->assertJsonPath('acceptedRevision.id', $accepted);
        $count = ResourceRevision::count();
        $this->actingAs($viewer)->postJson($this->base($device).'/pull', ['target_revision_id' => 999, 'user_id' => $author->id])->assertUnprocessable();
        $this->actingAs($viewer)->postJson($this->base($device).'/pull')->assertOk()->assertJsonPath('updateAvailable', false)->assertJsonPath('acceptedRevision.revisionNumber', 2);
        $this->assertSame($count, ResourceRevision::count());
        $this->assertSame(1, Device::whereKey($device->id)->count());
    }

    public function test_behind_editor_is_blocked_but_live_metadata_access_and_comments_are_not_pinned(): void
    {
        [$device,$author,$viewer] = $this->context('full_access');
        $this->saveDashboard($author, $device, 'Accepted')->assertOk();
        $this->actingAs($viewer)->postJson($this->base($device).'/pull')->assertOk();
        $this->saveDashboard($author, $device, 'Latest', 2)->assertOk();
        $version = $this->actingAs($viewer)->getJson("/api/devices/$device->id/dashboard")->assertOk()->assertJsonPath('name', 'Accepted')->json('layoutVersion');
        $this->saveDashboard($viewer, $device, 'Stale', $version)->assertConflict();
        $this->actingAs($author)->patchJson("/api/devices/$device->id", ['location_id' => Location::create(['organization_id' => $device->organization_id, 'name' => 'Current Bay', 'normalized_name' => 'current bay'])->id])->assertOk();
        $this->actingAs($viewer)->getJson("/api/devices/$device->id")->assertOk()->assertJsonPath('location.name', 'Current Bay');
        $this->actingAs($viewer)->postJson("/api/collaboration/device/$device->id/threads", ['body' => 'Live discussion'])->assertCreated();
        $this->assertSame(3, ResourceRevision::whereJsonContains('changed_sections', 'dashboard')->count());
    }

    public function test_changes_inbox_is_authorized_paginated_and_notification_is_coalesced(): void
    {
        [$device,$author,$viewer] = $this->context();
        $this->saveDashboard($author, $device, 'Second')->assertOk();
        $this->saveDashboard($author, $device, 'Third', 2)->assertOk();
        $this->actingAs($viewer)->getJson('/api/changes')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.resource.id', (string) $device->id)->assertJsonPath('data.0.latestRevision.revisionNumber', 3);
        $this->assertDatabaseCount('notifications', 1);
        $notification = Notification::firstOrFail();
        $this->assertSame('revision.available', $notification->type);
        $this->actingAs($viewer)->postJson("/api/notifications/$notification->id/read")->assertOk();
        $this->actingAs($viewer)->getJson($this->stateUrl($device))->assertJsonPath('updateAvailable', true);
        DeviceAccessAssignment::where('device_id', $device->id)->where('user_id', $viewer->id)->delete();
        $this->actingAs($viewer)->getJson('/api/changes')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($viewer)->postJson($this->base($device).'/pull')->assertNotFound();
    }

    public function test_non_pullable_metadata_revision_does_not_create_update_available(): void
    {
        [$device,$author,$viewer] = $this->context();
        $this->actingAs($author)->patchJson("/api/devices/$device->id", ['location_id' => Location::create(['organization_id' => $device->organization_id, 'name' => 'Bay 3', 'normalized_name' => 'bay 3'])->id])->assertOk();
        $this->actingAs($viewer)->getJson($this->stateUrl($device))->assertOk()->assertJsonPath('updateAvailable', false);
    }

    private function context(string $viewerLevel = 'viewer'): array
    {
        $org = Organization::create(['name' => 'Plant', 'slug' => 'plant']);
        $author = $this->user($org, 'author@test');
        $viewer = $this->user($org, 'viewer@test');
        $id = $this->actingAs($author)->postJson('/api/devices', ['name' => 'Pump', 'type' => 'sensor', 'serialNumber' => 'P-36', 'protocol' => 'mqtt'])->assertCreated()->json('id');
        $device = Device::findOrFail($id);
        DeviceAccessAssignment::create(['device_id' => $id, 'user_id' => $viewer->id, 'access_level' => $viewerLevel]);
        app(ResourceRevisionStateService::class)->initializeGrant($viewer, $device);

        return [$device, $author, $viewer];
    }

    private function saveDashboard(User $u, Device $d, string $name, int $version = 1)
    {
        return $this->actingAs($u)->patchJson("/api/devices/$d->id/dashboard", ['name' => $name, 'layoutVersion' => $version, 'widgets' => [['id' => (string) Str::uuid(), 'type' => 'status', 'settings' => ['title' => 'Status', 'datasource' => ['deviceId' => (string) $d->id, 'telemetryKey' => '']], 'layout' => ['x' => 0, 'y' => 0, 'w' => 3, 'h' => 3]]]]);
    }

    private function base(Device $d): string
    {
        return "/api/collaboration/resources/device/$d->id";
    }

    private function stateUrl(Device $d): string
    {
        return $this->base($d).'/revision-state';
    }

    private function user(Organization $o,string $email): User
    {
        return User::factory()->create(['organization_id' => $o->id, 'email' => $email, 'role' => 'staff', 'status' => 'active']);
    }
}
