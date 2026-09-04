<?php

namespace Tests\Feature;

use App\Models\DeviceAccessAssignment;
use App\Models\FirmwareArtifact;
use App\Models\Organization;
use App\Models\ResourceCollaborator;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class FirmwareCollaborationReleaseTest extends TestCase
{
    use RefreshDatabase;

    private function setupData(): array
    {
        Storage::fake('local');
        $org = Organization::create(['name' => 'Firmware Collaboration', 'slug' => 'fw-collab-'.uniqid()]);
        $editor = User::factory()->create(['organization_id' => $org->id, 'role' => 'owner', 'status' => 'active']);
        $viewer = User::factory()->create(['organization_id' => $org->id, 'role' => 'staff', 'status' => 'active']);
        $other = User::factory()->create(['organization_id' => $org->id, 'role' => 'staff', 'status' => 'active']);
        $admin = User::factory()->create(['organization_id' => $org->id, 'role' => 'owner', 'platform_role' => 'platform_admin', 'status' => 'active']);
        $id = $this->actingAs($editor)->post('/api/firmware/artifacts', ['name' => 'Controller', 'version' => '2.4.0', 'description' => 'Candidate', 'firmware' => UploadedFile::fake()->createWithContent('controller.bin', 'immutable firmware bytes')], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        ResourceCollaborator::create(['resource_type' => 'firmware', 'resource_id' => $id, 'user_id' => $viewer->id, 'permission' => 'view', 'granted_by' => $editor->id]);

        return compact('org', 'editor', 'viewer', 'other', 'admin', 'id');
    }

    public function test_private_view_edit_and_revision_snapshot_are_safe(): void
    {
        extract($this->setupData());
        $this->actingAs($viewer)->getJson("/api/firmware/artifacts/{$id}")->assertOk();
        $this->actingAs($viewer)->patchJson("/api/firmware/artifacts/{$id}", ['description' => 'Denied'])->assertNotFound();
        $this->actingAs($other)->getJson("/api/firmware/artifacts/{$id}")->assertNotFound();
        $this->actingAs($editor)->patchJson("/api/firmware/artifacts/{$id}", ['description' => 'Updated'])->assertOk();
        $revisions = ResourceRevision::where(['resource_type' => 'firmware', 'resource_id' => $id])->orderBy('revision_number')->get();
        $this->assertCount(2, $revisions);
        $this->assertSame(FirmwareArtifact::find($id)->sha256, $revisions->last()->snapshot['artifact']['sha256']);
        $this->assertArrayNotHasKey('storage_path', $revisions->last()->snapshot['artifact']);
        $this->assertStringNotContainsString('immutable firmware bytes', json_encode($revisions->last()->snapshot));
        $this->actingAs($editor)->patchJson("/api/firmware/artifacts/{$id}", ['description' => 'Updated'])->assertOk();
        $this->assertSame(2, ResourceRevision::where(['resource_type' => 'firmware', 'resource_id' => $id])->count());
    }

    public function test_submission_claim_approval_pins_exact_candidate_and_notifies(): void
    {
        extract($this->setupData());
        $artifact = FirmwareArtifact::findOrFail($id);
        $submission = $this->actingAs($editor)->postJson("/api/firmware/artifacts/{$id}/release-submissions")->assertCreated()->assertJsonPath('data.candidate.sha256', $artifact->sha256)->assertJsonPath('data.domainVersion', '2.4.0')->json('data.submissionId');
        $this->actingAs($viewer)->postJson("/api/firmware/artifacts/{$id}/release-submissions")->assertNotFound();
        $this->actingAs($admin)->postJson("/api/admin/firmware-submissions/{$submission}/approve")->assertUnprocessable();
        $this->actingAs($admin)->postJson("/api/admin/firmware-submissions/{$submission}/claim")->assertOk();
        $revisionCount = ResourceRevision::count();
        $this->actingAs($admin)->postJson("/api/admin/firmware-submissions/{$submission}/approve")->assertOk();
        $this->assertDatabaseHas('firmware_releases', ['firmware_artifact_id' => $id, 'artifact_sha256' => $artifact->sha256, 'domain_version' => '2.4.0', 'is_current' => true]);
        $this->assertSame($revisionCount, ResourceRevision::count());
        $this->actingAs($editor)->getJson("/api/firmware/artifacts/{$id}/release")->assertOk()->assertJsonPath('data.currentRelease.sha256', $artifact->sha256);
        $this->assertDatabaseHas('notifications', ['user_id' => $editor->id, 'type' => 'firmware.release_approved']);
    }

    public function test_unapproved_and_disabled_deployment_are_blocked_but_approved_record_is_truthful(): void
    {
        extract($this->setupData());
        $device = $org->devices()->create(['name' => 'Pump', 'external_id' => 'fw-'.uniqid(), 'type' => 'pump', 'protocol' => 'mqtt']);
        DeviceAccessAssignment::create(['user_id' => $editor->id, 'device_id' => $device->id, 'access_level' => 'full_access']);
        $this->actingAs($editor)->postJson('/api/firmware/deployments', ['firmware_artifact_id' => $id, 'device_ids' => [$device->id]])->assertUnprocessable();
        $submission = $this->actingAs($editor)->postJson("/api/firmware/artifacts/{$id}/release-submissions")->json('data.submissionId');
        $this->actingAs($admin)->postJson("/api/admin/firmware-submissions/{$submission}/claim");
        $this->actingAs($admin)->postJson("/api/admin/firmware-submissions/{$submission}/approve");
        $this->actingAs($editor)->postJson('/api/firmware/deployments', ['firmware_artifact_id' => $id, 'device_ids' => [$device->id]])->assertCreated()->assertJsonPath('data.status', 'delivery_unavailable');
        $this->assertNull($device->refresh()->firmware_version);
        $this->actingAs($admin)->postJson("/api/admin/resources/firmware/{$id}/lifecycle/disable")->assertOk();
        $this->actingAs($editor)->postJson('/api/firmware/deployments', ['firmware_artifact_id' => $id, 'device_ids' => [$device->id]])->assertStatus(409);
    }

    public function test_foreign_admin_cannot_read_or_decide_firmware_review_even_with_corrupt_claim(): void
    {
        extract($this->setupData());
        $foreignOrg = Organization::create(['name' => 'Foreign', 'slug' => 'firmware-foreign']);
        $foreignAdmin = User::factory()->create(['organization_id' => $foreignOrg->id, 'role' => 'owner', 'platform_role' => 'platform_admin', 'status' => 'active']);
        $submission = $this->actingAs($editor)->postJson("/api/firmware/artifacts/{$id}/release-submissions")->assertCreated()->json('data.submissionId');
        ResourcePublicationSubmission::whereKey($submission)->update(['reviewer_id' => $foreignAdmin->id]);
        $this->actingAs($foreignAdmin)->getJson("/api/admin/firmware-submissions/{$submission}")->assertNotFound();
        $this->actingAs($foreignAdmin)->getJson("/api/admin/firmware-submissions/{$submission}/history")->assertNotFound();
        $this->actingAs($foreignAdmin)->postJson("/api/admin/firmware-submissions/{$submission}/approve")->assertNotFound();
        $this->assertDatabaseMissing('notifications',['user_id' => $foreignAdmin->id, 'type' => 'firmware.release_submitted']);
    }
}
