<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\DeviceTemplate;
use App\Models\Organization;
use App\Models\ResourceCollaborator;
use App\Models\ResourcePublicationState;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplatePublicationTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $organization, string $role = 'owner', bool $admin = false): User
    {
        return User::factory()->create(['organization_id' => $organization->id, 'role' => $role, 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']);
    }

    private function template(User $creator, string $name = 'Alarm Template'): string
    {
        return $this->actingAs($creator)->postJson('/api/device-templates', ['name' => $name, 'device_type' => 'sensor', 'protocol' => 'mqtt'])->assertCreated()->json('data.id');
    }

    public function test_submission_is_revision_pinned_and_approval_does_not_create_or_mutate_revision(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'publication-pinning']);
        $creator = $this->user($organization);
        $admin = $this->user($organization, 'staff', true);
        $templateId = $this->template($creator);
        $revision = ResourceRevision::where(['resource_type' => 'device_template', 'resource_id' => $templateId])->firstOrFail();
        $checksum = $revision->checksum;

        $submissionId = $this->actingAs($creator)->postJson("/api/device-templates/{$templateId}/publication-submissions", ['revision_id' => $revision->id])->assertCreated()->assertJsonPath('data.status', 'submitted')->json('data.id');
        $this->actingAs($creator)->patchJson("/api/device-templates/{$templateId}", ['name' => 'New private draft'])->assertOk();
        $this->assertSame(2, ResourceRevision::where(['resource_type' => 'device_template', 'resource_id' => $templateId])->count());

        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$submissionId}/claim")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$submissionId}/approve")->assertUnprocessable()->assertJsonValidationErrors('confirm_older_revision');
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$submissionId}/approve", ['confirm_older_revision' => true])->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.newerDraftExists', true);

        $this->assertSame(2, ResourceRevision::where(['resource_type' => 'device_template', 'resource_id' => $templateId])->count());
        $this->assertSame($checksum, $revision->fresh()->checksum);
        $this->assertSame($revision->id, ResourcePublicationState::where('resource_id', $templateId)->value('approved_revision_id'));
        $this->actingAs($creator)->getJson("/api/device-templates/{$templateId}/publication")->assertOk()->assertJsonPath('data.displayState', 'approved_with_draft_changes')->assertJsonPath('data.approvedRevision.number', 1)->assertJsonPath('data.latestRevision.number', 2);
    }

    public function test_view_collaborator_cannot_submit_and_staff_cannot_decide(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'publication-auth']);
        $creator = $this->user($organization);
        $viewer = $this->user($organization, 'staff');
        $templateId = $this->template($creator);
        ResourceCollaborator::create(['resource_type' => 'device_template', 'resource_id' => $templateId, 'user_id' => $viewer->id, 'permission' => 'view', 'granted_by' => $creator->id]);

        $this->actingAs($viewer)->postJson("/api/device-templates/{$templateId}/publication-submissions")->assertNotFound();
        $submissionId = $this->actingAs($creator)->postJson("/api/device-templates/{$templateId}/publication-submissions")->assertCreated()->json('data.id');
        foreach (['approve', 'request-changes', 'reject'] as $action) {
            $this->actingAs($creator)->postJson("/api/admin/template-publication-submissions/{$submissionId}/{$action}", ['note' => 'No'])->assertForbidden();
        }
        $this->assertSame('submitted', ResourcePublicationSubmission::findOrFail($submissionId)->status);
    }

    public function test_published_catalog_and_application_use_approved_snapshot_not_newer_draft(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'publication-catalog']);
        $creator = $this->user($organization);
        $admin = $this->user($organization, 'staff', true);
        $staff = $this->user($organization, 'staff');
        $templateId = $this->template($creator, 'Approved name');
        $this->actingAs($creator)->postJson("/api/device-templates/{$templateId}/parameters", ['name' => 'Approved temperature', 'key' => 'temperature', 'data_type' => 'number'])->assertOk();
        $approvedRevision = ResourceRevision::where(['resource_type' => 'device_template', 'resource_id' => $templateId])->latest('revision_number')->firstOrFail();
        $submissionId = $this->actingAs($creator)->postJson("/api/device-templates/{$templateId}/publication-submissions")->assertCreated()->json('data.id');
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$submissionId}/claim")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$submissionId}/approve")->assertOk();
        $this->actingAs($creator)->patchJson("/api/device-templates/{$templateId}", ['name' => 'Unpublished name'])->assertOk();
        $parameter = DeviceTemplate::findOrFail($templateId)->parameters()->firstOrFail();
        $this->actingAs($creator)->patchJson("/api/device-templates/{$templateId}/parameters/{$parameter->id}", ['name' => 'Unpublished temperature'])->assertOk();

        $this->actingAs($staff)->getJson('/api/device-templates/published')->assertOk()->assertJsonPath('data.0.name', 'Approved name')->assertJsonPath('data.0.approvedRevision.number', $approvedRevision->revision_number);
        $this->actingAs($staff)->getJson("/api/device-templates/{$templateId}")->assertNotFound();

        $device = Device::create(['organization_id' => $organization->id, 'name' => 'Device', 'external_id' => 'published-device', 'type' => 'sensor', 'protocol' => 'mqtt']);
        DeviceAccessAssignment::create(['device_id' => $device->id, 'user_id' => $staff->id, 'access_level' => 'full_access']);
        $this->actingAs($staff)->postJson("/api/devices/{$device->id}/apply-template", ['template_id' => $templateId])->assertOk();
        $this->assertDatabaseHas('device_parameters', ['device_id' => $device->id, 'key' => 'temperature', 'name' => 'Approved temperature']);
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'device_template_id' => $templateId, 'device_template_revision_id' => $approvedRevision->id]);
    }

    public function test_changes_requested_and_rejection_preserve_existing_approved_revision(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'publication-decisions']);
        $creator = $this->user($organization);
        $admin = $this->user($organization, 'staff', true);
        $templateId = $this->template($creator);
        $first = $this->actingAs($creator)->postJson("/api/device-templates/{$templateId}/publication-submissions")->assertCreated()->json('data.id');
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$first}/claim")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$first}/approve")->assertOk();
        $approvedId = ResourcePublicationState::where('resource_id', $templateId)->value('approved_revision_id');

        $this->actingAs($creator)->patchJson("/api/device-templates/{$templateId}", ['description' => 'Revision two'])->assertOk();
        $second = $this->actingAs($creator)->postJson("/api/device-templates/{$templateId}/publication-submissions")->assertCreated()->json('data.id');
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$second}/claim")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$second}/request-changes", ['note' => 'Document the threshold.'])->assertOk()->assertJsonPath('data.status', 'changes_requested');
        $this->assertSame($approvedId, ResourcePublicationState::where('resource_id', $templateId)->value('approved_revision_id'));

        $this->actingAs($creator)->patchJson("/api/device-templates/{$templateId}", ['description' => 'Revision three'])->assertOk();
        $third = $this->actingAs($creator)->postJson("/api/device-templates/{$templateId}/publication-submissions")->assertCreated()->json('data.id');
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$third}/claim")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/template-publication-submissions/{$third}/reject", ['note' => 'Not suitable for publication.'])->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertSame($approvedId, ResourcePublicationState::where('resource_id', $templateId)->value('approved_revision_id'));
        $this->assertDatabaseHas('notifications', ['type' => 'template.publication.changes_requested', 'user_id' => $creator->id]);
        $this->assertDatabaseHas('notifications', ['type' => 'template.publication.rejected', 'user_id' => $creator->id]);
    }
}
