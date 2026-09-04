<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\Organization;
use App\Models\ResourcePublicationState;
use App\Models\ResourcePublicationVersion;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class VersionedPublicationTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $organization, bool $admin = false): User
    {
        return User::factory()->create(['organization_id' => $organization->id, 'role' => $admin ? 'staff' : 'owner', 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']);
    }

    private function template(User $creator, string $name = 'Published name'): string
    {
        return $this->actingAs($creator)->postJson('/api/device-templates', ['name' => $name, 'description' => 'Published description'])->assertCreated()->json('data.id');
    }

    private function approve(User $creator, User $admin, string $template): int
    {
        $submission = $this->actingAs($creator)->postJson("/api/device-templates/{$template}/publication-submissions")->assertCreated()->json('data.id');
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$submission}/claim")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$submission}/approve")->assertOk();
        return (int) $submission;
    }

    public function test_first_and_second_approval_create_monotonic_immutable_versions(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'version-sequence']);
        $creator = $this->user($org);
        $admin = $this->user($org, true);
        $template = $this->template($creator);
        $revisionCount = ResourceRevision::count();
        $firstSubmission = $this->approve($creator, $admin, $template);
        $first = ResourcePublicationVersion::firstOrFail();
        $this->assertSame(1, $first->publication_number);
        $this->assertSame($firstSubmission, $first->review_submission_id);
        $this->assertSame($revisionCount, ResourceRevision::count());
        $this->assertSame($first->id, ResourcePublicationState::where('resource_id', $template)->value('current_publication_version_id'));

        $this->actingAs($creator)->patchJson("/api/device-templates/{$template}", ['name' => 'Version two'])->assertOk();
        $this->assertSame($first->id, ResourcePublicationState::where('resource_id', $template)->value('current_publication_version_id'));
        $this->approve($creator, $admin, $template);
        $second = ResourcePublicationVersion::latest('publication_number')->firstOrFail();
        $this->assertSame(2, $second->publication_number);
        $this->assertSame($first->id, $second->previous_publication_version_id);
        $this->assertSame($second->id, ResourcePublicationState::where('resource_id', $template)->value('current_publication_version_id'));
        $this->assertSame(1, $first->fresh()->publication_number);
        $this->expectException(LogicException::class);
        $first->update(['publication_number' => 99]);
    }

    public function test_rejected_and_changes_requested_cycles_do_not_consume_versions(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'version-terminal']);
        $creator = $this->user($org);
        $admin = $this->user($org, true);
        $template = $this->template($creator);
        foreach ([['request-changes', 'Fix it'], ['reject', 'No']] as [$action, $note]) {
            $submission = $this->actingAs($creator)->postJson("/api/device-templates/{$template}/publication-submissions")->assertCreated()->json('data.id');
            $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$submission}/claim")->assertOk();
            $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$submission}/{$action}", ['note' => $note])->assertOk();
            $this->actingAs($creator)->patchJson("/api/device-templates/{$template}", ['description' => $action])->assertOk();
        }
        $this->assertDatabaseCount('resource_publication_versions', 0);
        $this->approve($creator, $admin, $template);
        $this->assertSame(1, ResourcePublicationVersion::value('publication_number'));
    }

    public function test_catalog_detail_and_application_use_current_version_not_newer_draft(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'version-resolution']);
        $creator = $this->user($org);
        $admin = $this->user($org, true);
        $staff = $this->user($org);
        $template = $this->template($creator);
        $this->actingAs($creator)->postJson("/api/device-templates/{$template}/parameters", ['name' => 'Published parameter', 'key' => 'published', 'data_type' => 'number'])->assertOk();
        $this->approve($creator, $admin, $template);
        $version = ResourcePublicationVersion::firstOrFail();
        $this->actingAs($creator)->patchJson("/api/device-templates/{$template}", ['name' => 'Private draft name'])->assertOk();

        $this->actingAs($staff)->getJson('/api/device-templates/published')->assertOk()->assertJsonPath('data.0.name', 'Published name')->assertJsonPath('data.0.currentVersion.number', 1);
        $this->actingAs($staff)->getJson("/api/device-templates/published/{$template}")->assertOk()->assertJsonPath('data.currentVersion.id', (string) $version->id)->assertJsonPath('data.snapshot.metadata.name', 'Published name');
        $this->actingAs($staff)->getJson("/api/device-templates/{$template}")->assertNotFound();
        $this->actingAs($staff)->getJson("/api/device-templates/{$template}/publication/versions")->assertOk()->assertJsonPath('data.0.current', true);
        $this->actingAs($staff)->getJson("/api/device-templates/{$template}/publication/versions/{$version->id}")->assertOk()->assertJsonPath('data.snapshot.metadata.name', 'Published name');

        $device = Device::create(['organization_id' => $org->id, 'name' => 'Device', 'external_id' => 'version-device', 'type' => 'sensor', 'protocol' => 'mqtt']);
        DeviceAccessAssignment::create(['device_id' => $device->id, 'user_id' => $staff->id, 'access_level' => 'full_access']);
        $this->actingAs($staff)->postJson("/api/devices/{$device->id}/apply-template", ['template_id' => $template])->assertOk();
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'device_template_revision_id' => $version->resource_revision_id, 'device_template_publication_version_id' => $version->id]);
        $this->assertDatabaseHas('device_parameters', ['device_id' => $device->id, 'key' => 'published', 'name' => 'Published parameter']);
    }

    public function test_version_id_is_resource_and_tenant_scoped_and_has_no_mutation_route(): void
    {
        $orgA = Organization::create(['name' => 'A', 'slug' => 'version-a']);
        $orgB = Organization::create(['name' => 'B', 'slug' => 'version-b']);
        $creator = $this->user($orgA);
        $admin = $this->user($orgA, true);
        $outsider = $this->user($orgB);
        $template = $this->template($creator);
        $this->approve($creator, $admin, $template);
        $version = ResourcePublicationVersion::firstOrFail();
        $this->actingAs($outsider)->getJson("/api/device-templates/{$template}/publication/versions/{$version->id}")->assertNotFound();
        $this->actingAs($creator)->patchJson("/api/device-templates/{$template}/publication/versions/{$version->id}", ['publication_number' => 9])->assertMethodNotAllowed();
        $this->actingAs($creator)->deleteJson("/api/device-templates/{$template}/publication/versions/{$version->id}")->assertMethodNotAllowed();
    }

    public function test_legacy_backfill_is_idempotent_and_preserves_real_pointer(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'version-backfill']);
        $creator = $this->user($org);
        $template = $this->template($creator);
        $revision = ResourceRevision::where('resource_id', $template)->firstOrFail();
        ResourcePublicationState::create(['resource_type' => 'device_template', 'resource_id' => $template, 'approved_revision_id' => $revision->id]);
        $this->artisan('publication-versions:backfill')->assertSuccessful();
        $this->artisan('publication-versions:backfill')->assertSuccessful();
        $this->assertDatabaseCount('resource_publication_versions', 1);
        $version = ResourcePublicationVersion::firstOrFail();
        $this->assertSame('legacy_baseline', $version->metadata['source']);
        $this->assertSame($version->id, ResourcePublicationState::firstOrFail()->current_publication_version_id);
        $this->assertNull($version->published_by);
    }
}
