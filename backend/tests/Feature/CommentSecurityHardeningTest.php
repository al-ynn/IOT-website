<?php

namespace Tests\Feature;

use App\Models\CollaborationComment;
use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\DeviceParameter;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CommentSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $org): User
    {
        return User::factory()->create(['organization_id'=>$org->id,'role'=>'staff','status'=>'active']);
    }

    private function device(Organization $org): Device
    {
        return $org->devices()->create(['name'=>'Secure Device','external_id'=>'comment-secure','type'=>'sensor','protocol'=>'https','status'=>'online']);
    }

    private function assign(User $user, Device $device, string $level='viewer'): void
    {
        DeviceAccessAssignment::create(['user_id'=>$user->id,'device_id'=>$device->id,'access_level'=>$level]);
    }

    public function test_mention_candidates_are_resource_scoped_minimal_and_bounded(): void
    {
        $org=Organization::create(['name'=>'Comments','slug'=>'comment-security']);
        $actor=$this->user($org);$eligible=$this->user($org);$unassigned=$this->user($org);
        $admin=User::factory()->create(['organization_id'=>$org->id,'role'=>'staff','platform_role'=>'platform_admin','status'=>'active']);
        $device=$this->device($org);$this->assign($actor,$device);$this->assign($eligible,$device);

        $response=$this->actingAs($actor)->getJson("/api/collaboration/device/{$device->id}/mention-candidates")->assertOk();
        $ids=collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains((string)$actor->id));
        $this->assertTrue($ids->contains((string)$eligible->id));
        $this->assertFalse($ids->contains((string)$unassigned->id));
        $this->assertFalse($ids->contains((string)$admin->id));
        $this->assertSame(['id','name'],array_keys($response->json()[0]));
    }

    public function test_comment_edit_reauthorizes_and_relationship_fields_are_immutable(): void
    {
        $org=Organization::create(['name'=>'Edit','slug'=>'comment-edit']);$author=$this->user($org);$device=$this->device($org);$this->assign($author,$device);
        $thread=$this->actingAs($author)->postJson("/api/collaboration/device/{$device->id}/threads",['body'=>'Original'])->assertCreated();
        $commentId=$thread->json('comments.0.id');
        $this->actingAs($author)->patchJson("/api/collaboration/comments/{$commentId}",['body'=>'Changed','thread_id'=>999])->assertUnprocessable();
        $this->assertDatabaseHas('collaboration_comments',['id'=>$commentId,'body'=>'Original']);
        DeviceAccessAssignment::where(['user_id'=>$author->id,'device_id'=>$device->id])->delete();
        $this->actingAs($author)->patchJson("/api/collaboration/comments/{$commentId}",['body'=>'After revoke'])->assertNotFound();
    }

    public function test_plain_text_validation_and_resolved_reply_are_server_enforced(): void
    {
        $org=Organization::create(['name'=>'Body','slug'=>'comment-body']);$author=$this->user($org);$device=$this->device($org);$this->assign($author,$device,'full_access');
        $this->actingAs($author)->postJson("/api/collaboration/device/{$device->id}/threads",['body'=>"   \n"])->assertUnprocessable();
        $threadId=$this->actingAs($author)->postJson("/api/collaboration/device/{$device->id}/threads",['body'=>'Open'])->assertCreated()->json('id');
        $this->actingAs($author)->postJson("/api/collaboration/threads/{$threadId}/resolve")->assertOk();
        $this->actingAs($author)->postJson("/api/collaboration/threads/{$threadId}/comments",['body'=>'Too late'])->assertUnprocessable();
        $this->assertSame(1,CollaborationComment::where('thread_id',$threadId)->count());
    }

    public function test_parameter_anchor_filter_is_allowlisted_without_revalidating_deleted_anchor(): void
    {
        $org=Organization::create(['name'=>'Anchor','slug'=>'comment-anchor-filter']);$author=$this->user($org);$device=$this->device($org);$this->assign($author,$device);
        $parameter=DeviceParameter::create(['device_id'=>$device->id,'name'=>'Pressure','key'=>'pressure','data_type'=>'number']);
        $this->actingAs($author)->postJson("/api/collaboration/device/{$device->id}/threads",['anchor_type'=>'parameter','anchor_key'=>(string)$parameter->id,'body'=>'Parameter'])->assertCreated();
        $parameter->delete();
        $this->actingAs($author)->getJson("/api/collaboration/device/{$device->id}/threads?anchor_type=parameter&anchor_key={$parameter->id}")->assertOk()->assertJsonCount(1,'data');
        $this->actingAs($author)->getJson("/api/collaboration/device/{$device->id}/threads?anchor_type=css_selector")->assertUnprocessable();
    }
}