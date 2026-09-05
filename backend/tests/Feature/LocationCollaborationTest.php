<?php

namespace Tests\Feature;

use App\Models\{Location, Organization, ResourceCollaborator, ResourceRevision, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LocationCollaborationTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $organization): User
    {
        return User::factory()->create(['organization_id'=>$organization->id,'role'=>'staff','status'=>'active']);
    }

    public function test_staff_creation_is_atomic_private_and_revisioned(): void
    {
        $org=Organization::create(['name'=>'Plant','slug'=>'location-collab-create']);$creator=$this->user($org);$other=$this->user($org);
        $id=$this->actingAs($creator)->postJson('/api/locations',['name'=>'Control Room','description'=>'Restricted'])->assertCreated()->assertJsonPath('data.access','edit')->json('data.id');
        $this->assertDatabaseHas('resource_collaborators',['resource_type'=>'location','resource_id'=>$id,'user_id'=>$creator->id,'permission'=>'edit']);
        $revision=ResourceRevision::where(['resource_type'=>'location','resource_id'=>$id])->firstOrFail();
        $this->assertSame(['metadata'=>['name'=>'Control Room','description'=>'Restricted']],$revision->snapshot);
        $this->actingAs($other)->getJson('/api/locations')->assertOk()->assertJsonPath('data.0.description',null);
        $this->actingAs($other)->getJson("/api/locations/$id")->assertNotFound();
        $this->assertDatabaseMissing('device_access_assignments',['user_id'=>$creator->id]);
    }

    public function test_location_share_requires_acceptance_and_does_not_grant_device_access(): void
    {
        $org=Organization::create(['name'=>'Plant','slug'=>'location-collab-share']);$creator=$this->user($org);$viewer=$this->user($org);
        $id=$this->actingAs($creator)->postJson('/api/locations',['name'=>'Lab'])->assertCreated()->json('data.id');
        $share=$this->actingAs($creator)->postJson('/api/shares',['resource_type'=>'location','resource_id'=>$id,'recipient_id'=>$viewer->id,'permission'=>'view'])->assertCreated()->json('data.id');
        $this->actingAs($viewer)->getJson("/api/locations/$id")->assertNotFound();
        $this->actingAs($viewer)->postJson("/api/shares/$share/accept")->assertOk();
        $this->actingAs($viewer)->getJson("/api/locations/$id")->assertOk()->assertJsonPath('data.capabilities.canUpdate',false);
        $this->assertDatabaseMissing('device_access_assignments',['user_id'=>$viewer->id]);
    }

    public function test_edit_revision_comment_and_draft_use_safe_location_fields(): void
    {
        $org=Organization::create(['name'=>'Plant','slug'=>'location-collab-work']);$creator=$this->user($org);$editor=$this->user($org);
        $id=$this->actingAs($creator)->postJson('/api/locations',['name'=>'Lab'])->assertCreated()->json('data.id');
        ResourceCollaborator::create(['resource_type'=>'location','resource_id'=>$id,'user_id'=>$editor->id,'permission'=>'edit','granted_by'=>$creator->id]);
        $this->actingAs($editor)->patchJson("/api/locations/$id",['description'=>'Updated'])->assertOk();
        $this->assertSame(2,ResourceRevision::where(['resource_type'=>'location','resource_id'=>$id])->count());
        $thread=$this->actingAs($editor)->postJson("/api/collaboration/location/$id/threads",['anchor_type'=>'description','body'=>'Please review'])->assertCreated()->json('id');
        $this->assertNotNull($thread);
        $base=ResourceRevision::where(['resource_type'=>'location','resource_id'=>$id])->latest('revision_number')->firstOrFail();
        $this->actingAs($editor)->putJson("/api/collaboration/resources/location/$id/draft",['base_revision_id'=>$base->id,'snapshot'=>['metadata'=>['name'=>'Lab 2','description'=>'Draft']]])->assertOk();
        $this->actingAs($editor)->putJson("/api/collaboration/resources/location/$id/draft",['base_revision_id'=>$base->id,'snapshot'=>['metadata'=>['name'=>'Lab','devices'=>[1]]]])->assertUnprocessable();
    }

    public function test_revocation_removes_workspace_revision_comment_and_draft_access(): void
    {
        $org=Organization::create(['name'=>'Plant','slug'=>'location-collab-revoke']);$creator=$this->user($org);$viewer=$this->user($org);
        $id=$this->actingAs($creator)->postJson('/api/locations',['name'=>'Vault'])->assertCreated()->json('data.id');
        ResourceCollaborator::create(['resource_type'=>'location','resource_id'=>$id,'user_id'=>$viewer->id,'permission'=>'view','granted_by'=>$creator->id]);
        $thread=$this->actingAs($viewer)->postJson("/api/collaboration/location/$id/threads",['body'=>'Visible'])->assertCreated()->json('id');
        ResourceCollaborator::where(['resource_type'=>'location','resource_id'=>$id,'user_id'=>$viewer->id])->delete();
        $this->actingAs($viewer)->getJson("/api/locations/$id")->assertNotFound();
        $this->actingAs($viewer)->getJson("/api/collaboration/resources/location/$id/revisions")->assertNotFound();
        $this->actingAs($viewer)->getJson("/api/collaboration/threads/$thread")->assertNotFound();
        $this->actingAs($viewer)->getJson("/api/collaboration/resources/location/$id/draft")->assertNotFound();
    }
}