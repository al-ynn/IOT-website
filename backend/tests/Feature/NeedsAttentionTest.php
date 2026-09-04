<?php

namespace Tests\Feature;

use App\Models\DeviceAccessAssignment;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\ResourceAttentionState;
use App\Models\ResourceCollaborator;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NeedsAttentionTest extends TestCase
{
    use RefreshDatabase;
    private function user(Organization $org,bool $admin=false,string $email='staff@test'):User{return User::factory()->create(['organization_id'=>$org->id,'email'=>$email,'role'=>'owner','platform_role'=>$admin?'platform_admin':null,'status'=>'active']);}
    private function mark(User $admin,string $type,int $id,array $data=[]){return $this->actingAs($admin)->postJson("/api/admin/resources/{$type}/{$id}/attention",$data+['reason_code'=>'configuration_follow_up','note'=>'Confirm configuration mapping.']);}

    public function test_admin_marks_device_once_without_revision_lifecycle_or_access_side_effects():void
    {
        $org=Organization::create(['name'=>'Org','slug'=>'attention-device']);$staff=$this->user($org);$admin=$this->user($org,true,'admin@test');$id=$this->actingAs($staff)->postJson('/api/devices',['name'=>'Pump','type'=>'sensor','serialNumber'=>'A-1','protocol'=>'mqtt'])->assertCreated()->json('id');$revisionCount=ResourceRevision::count();$assignment=DeviceAccessAssignment::where(['device_id'=>$id,'user_id'=>$staff->id])->firstOrFail();$status=\App\Models\Device::findOrFail($id)->status;
        $this->mark($admin,'device',$id)->assertCreated()->assertJsonPath('data.status','open')->assertJsonPath('data.marked_by',$admin->id);
        $this->mark($admin,'device',$id)->assertCreated();
        $this->assertDatabaseCount('resource_attention_states',1);$this->assertSame($revisionCount,ResourceRevision::count());$this->assertSame('full_access',$assignment->fresh()->access_level);$this->assertDatabaseHas('devices',['id'=>$id,'status'=>$status]);
        $this->actingAs($staff)->postJson("/api/admin/resources/device/{$id}/attention",['reason_code'=>'other','note'=>'No'])->assertForbidden();
    }

    public function test_update_clear_and_explicit_reopen_are_safe():void
    {
        $org=Organization::create(['name'=>'Org','slug'=>'attention-transition']);$staff=$this->user($org);$admin=$this->user($org,true,'admin2@test');$id=$this->actingAs($staff)->postJson('/api/devices',['name'=>'Pump','type'=>'sensor','serialNumber'=>'A-2','protocol'=>'mqtt'])->assertCreated()->json('id');$this->mark($admin,'device',$id);$revisions=ResourceRevision::count();
        $this->actingAs($admin)->patchJson("/api/admin/resources/device/{$id}/attention",['reason_code'=>'data_quality_follow_up','note'=>'Check labels.'])->assertOk()->assertJsonPath('data.updated_by',$admin->id);
        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$id}/attention/clear")->assertOk()->assertJsonPath('data.status','resolved')->assertJsonPath('data.resolved_by',$admin->id);
        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$id}/attention/clear")->assertOk();
        $this->actingAs($admin)->patchJson("/api/admin/resources/device/{$id}/attention",['reason_code'=>'other','note'=>'Stale'])->assertStatus(409);
        $this->mark($admin,'device',$id,['reason_code'=>'other','note'=>'Explicitly reopen.'])->assertCreated()->assertJsonPath('data.status','open');$this->assertSame($revisions,ResourceRevision::count());
    }

    public function test_reason_note_type_and_missing_resource_validation():void
    {
        $org=Organization::create(['name'=>'Org','slug'=>'attention-validation']);$admin=$this->user($org,true);$staff=$this->user($org,false,'validation@test');$device=$this->actingAs($staff)->postJson('/api/devices',['name'=>'Validation','type'=>'sensor','serialNumber'=>'A-5','protocol'=>'mqtt'])->assertCreated()->json('id');
        $this->mark($admin,'blueprint',1)->assertUnprocessable();$this->mark($admin,'device',999)->assertNotFound();
        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$device}/attention",['reason_code'=>'urgent','note'=>'No'])->assertUnprocessable();
        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$device}/attention",['reason_code'=>'other','note'=>str_repeat('x',1001)])->assertUnprocessable();
    }

    public function test_device_notifications_and_my_work_require_current_full_access():void
    {
        $org=Organization::create(['name'=>'Org','slug'=>'attention-recipients']);$full=$this->user($org,false,'full@test');$viewer=$this->user($org,false,'viewer@test');$admin=$this->user($org,true,'admin3@test');$id=$this->actingAs($full)->postJson('/api/devices',['name'=>'Pump','type'=>'sensor','serialNumber'=>'A-3','protocol'=>'mqtt'])->assertCreated()->json('id');DeviceAccessAssignment::create(['device_id'=>$id,'user_id'=>$viewer->id,'access_level'=>'viewer','assigned_by'=>$admin->id]);$this->mark($admin,'device',$id);
        $this->assertDatabaseHas('notifications',['user_id'=>$full->id,'type'=>'resource.needs_attention','requires_action'=>1]);$this->assertDatabaseMissing('notifications',['user_id'=>$viewer->id,'type'=>'resource.needs_attention']);
        $this->actingAs($full)->getJson('/api/my-work/needs-attention')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.capabilities.canAct',true);
        $this->actingAs($viewer)->getJson('/api/my-work/needs-attention')->assertOk()->assertJsonCount(0,'data');
        DeviceAccessAssignment::where(['device_id'=>$id,'user_id'=>$full->id])->delete();$this->actingAs($full)->getJson('/api/my-work/needs-attention')->assertOk()->assertJsonCount(0,'data');
        $notification=Notification::where('user_id',$full->id)->firstOrFail();$this->actingAs($full)->postJson("/api/notifications/{$notification->id}/read")->assertOk();$this->assertDatabaseHas('resource_attention_states',['resource_type'=>'device','resource_id'=>$id,'status'=>'open']);
    }

    public function test_template_edit_collaborator_is_actionable_but_viewer_is_not():void
    {
        $org=Organization::create(['name'=>'Org','slug'=>'attention-template']);$creator=$this->user($org,false,'creator@test');$viewer=$this->user($org,false,'view@test');$admin=$this->user($org,true,'admin4@test');$id=$this->actingAs($creator)->postJson('/api/device-templates',['name'=>'Template'])->assertCreated()->json('data.id');ResourceCollaborator::create(['resource_type'=>'device_template','resource_id'=>$id,'user_id'=>$viewer->id,'permission'=>'view','granted_by'=>$creator->id]);$this->mark($admin,'device_template',$id,['reason_code'=>'governance_follow_up'])->assertCreated();
        $this->actingAs($creator)->getJson('/api/my-work/needs-attention')->assertOk()->assertJsonCount(1,'data');$this->actingAs($viewer)->getJson('/api/my-work/needs-attention')->assertOk()->assertJsonCount(0,'data');$this->assertDatabaseHas('notifications',['user_id'=>$creator->id,'resource_type'=>'device_template']);$this->assertDatabaseMissing('notifications',['user_id'=>$viewer->id,'resource_type'=>'device_template']);
    }

    public function test_admin_queue_filters_count_and_resolved_exclusion():void
    {
        $org=Organization::create(['name'=>'Org','slug'=>'attention-queue']);$staff=$this->user($org);$admin=$this->user($org,true,'admin5@test');$device=$this->actingAs($staff)->postJson('/api/devices',['name'=>'Pump','type'=>'sensor','serialNumber'=>'A-4','protocol'=>'mqtt'])->assertCreated()->json('id');$template=$this->actingAs($staff)->postJson('/api/device-templates',['name'=>'Boiler'])->assertCreated()->json('data.id');$this->mark($admin,'device',$device);$this->mark($admin,'device_template',$template,['reason_code'=>'governance_follow_up']);
        $this->actingAs($admin)->getJson('/api/admin/needs-attention')->assertOk()->assertJsonCount(2,'data');$this->actingAs($admin)->getJson('/api/admin/needs-attention?resource_type=device_template&reason=governance_follow_up&search=Boiler')->assertOk()->assertJsonCount(1,'data');$this->actingAs($admin)->getJson('/api/admin/needs-attention/count')->assertOk()->assertJsonPath('count',2);
        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$device}/attention/clear");$this->actingAs($admin)->getJson('/api/admin/needs-attention')->assertOk()->assertJsonCount(1,'data');$this->actingAs($staff)->getJson('/api/admin/needs-attention')->assertForbidden();
    }
}
