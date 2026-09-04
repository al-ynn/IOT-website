<?php

namespace Tests\Feature;

use App\Models\DeviceAccessAssignment;
use App\Models\DeviceTemplate;
use App\Models\Organization;
use App\Models\ResourceCollaborator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTemplateCollaborationTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $organization, string $role = 'owner'): User
    {
        return User::factory()->create(['organization_id' => $organization->id, 'role' => $role, 'status' => 'active']);
    }

    public function test_template_share_grants_original_resource_with_view_edit_vocabulary(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'template-collaboration']);
        $creator = $this->user($organization);
        $viewer = $this->user($organization, 'staff');
        $editor = $this->user($organization, 'staff');
        $template = DeviceTemplate::create(['organization_id' => $organization->id, 'name' => 'Canonical', 'created_by' => $creator->id]);

        $viewerShare = $this->actingAs($creator)->postJson('/api/shares', ['resource_type' => 'device_template', 'resource_id' => $template->id, 'recipient_id' => $viewer->id, 'permission' => 'view'])->assertCreated()->assertJsonPath('data.status', 'pending_recipient')->json('data.id');
        $this->actingAs($viewer)->postJson("/api/shares/{$viewerShare}/accept")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->actingAs($viewer)->getJson("/api/device-templates/{$template->id}")->assertOk()->assertJsonPath('data.capabilities.canEdit', false);
        $this->actingAs($viewer)->patchJson("/api/device-templates/{$template->id}", ['name' => 'Denied'])->assertNotFound();

        $editorShare = $this->actingAs($creator)->postJson('/api/shares', ['resource_type' => 'device_template', 'resource_id' => $template->id, 'recipient_id' => $editor->id, 'permission' => 'edit'])->assertCreated()->json('data.id');
        $this->actingAs($editor)->postJson("/api/shares/{$editorShare}/accept")->assertOk();
        $this->actingAs($editor)->patchJson("/api/device-templates/{$template->id}", ['name' => 'Shared edit'])->assertOk()->assertJsonPath('data.id', (string) $template->id);

        $this->assertSame('Shared edit', $template->refresh()->name);
        $this->assertSame(3, ResourceCollaborator::where('resource_type', 'device_template')->where('resource_id', $template->id)->count());
        $this->assertSame(0, DeviceAccessAssignment::count());
        $this->assertSame(0, DeviceTemplate::where('name', 'like', 'Copy%')->count());
    }

    public function test_template_sharing_rejects_cross_org_and_device_permissions(): void
    {
        $organizationA = Organization::create(['name' => 'A', 'slug' => 'template-a']);
        $organizationB = Organization::create(['name' => 'B', 'slug' => 'template-b']);
        $creator = $this->user($organizationA);
        $foreign = $this->user($organizationB, 'staff');
        $local = $this->user($organizationA, 'staff');
        $template = DeviceTemplate::create(['organization_id' => $organizationA->id, 'name' => 'Private', 'created_by' => $creator->id]);

        $this->actingAs($creator)->postJson('/api/shares', ['resource_type' => 'device_template', 'resource_id' => $template->id, 'recipient_id' => $foreign->id, 'permission' => 'view'])->assertUnprocessable();
        foreach (['viewer', 'full_access', 'review'] as $permission) {
            $this->actingAs($creator)->postJson('/api/shares', ['resource_type' => 'device_template', 'resource_id' => $template->id, 'recipient_id' => $local->id, 'permission' => $permission])->assertUnprocessable()->assertJsonValidationErrors('permission');
        }
        $this->actingAs($local)->getJson("/api/device-templates/{$template->id}")->assertNotFound();
    }

    public function test_template_changes_create_immutable_revision_history(): void
    {
        $organization = Organization::create(['name' => 'Org', 'slug' => 'template-revisions']);
        $creator = $this->user($organization);
        $id = $this->actingAs($creator)->postJson('/api/device-templates', ['name' => 'Versioned'])->assertCreated()->json('data.id');

        $this->actingAs($creator)->patchJson("/api/device-templates/{$id}", ['description' => 'Updated'])->assertOk();
        $this->actingAs($creator)->postJson("/api/device-templates/{$id}/parameters", ['name' => 'Temperature', 'key' => 'temperature', 'data_type' => 'number'])->assertOk();
        $this->actingAs($creator)->getJson("/api/collaboration/resources/device_template/{$id}/revisions")->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.revisionNumber', 3);
        $this->assertDatabaseHas('resource_revisions', ['resource_type' => 'device_template', 'resource_id' => $id, 'revision_number' => 1]);
    }
}
