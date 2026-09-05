<?php

namespace Tests\Feature;

use App\Models\{Device,DeviceAccessAssignment,DeviceTemplate,Organization,ResourceCollaborator,ResourceDraft,ResourceRevision,User};
use App\Services\MyWorkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PersonalWorkspaceProviderCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $org=Organization::create(['name'=>'Personal Work','slug'=>'personal-work-'.uniqid()]);
        $user=User::factory()->create(['organization_id'=>$org->id,'status'=>'active','role'=>'staff']);
        $other=User::factory()->create(['organization_id'=>$org->id,'status'=>'active','role'=>'staff']);
        return[$org,$user,$other];
    }

    private function revision(string$type,int$id,User$user,int$number,?string$at=null):ResourceRevision
    {
        return ResourceRevision::create(['resource_type'=>$type,'resource_id'=>$id,'revision_number'=>$number,'created_by'=>$user->id,'change_summary'=>'safe','snapshot_schema_version'=>1,'snapshot'=>[],'changed_sections'=>[],'checksum'=>hash('sha256',"$type:$id:$number:$user->id"),'created_at'=>$at??now()]);
    }

    public function test_my_work_requires_current_access_and_a_factual_relationship():void
    {
        [$org,$user,$other]=$this->context();
        $shared=DeviceTemplate::create(['organization_id'=>$org->id,'created_by'=>$other->id,'name'=>'Shared only']);
        ResourceCollaborator::create(['resource_type'=>'device_template','resource_id'=>$shared->id,'user_id'=>$user->id,'permission'=>'view','granted_by'=>$other->id]);
        $created=DeviceTemplate::create(['organization_id'=>$org->id,'created_by'=>$user->id,'name'=>'Created here']);
        $contributed=DeviceTemplate::create(['organization_id'=>$org->id,'created_by'=>$other->id,'name'=>'Contributed here']);
        ResourceCollaborator::create(['resource_type'=>'device_template','resource_id'=>$contributed->id,'user_id'=>$user->id,'permission'=>'view','granted_by'=>$other->id]);
        $revision=$this->revision('device_template',$contributed->id,$user,1);
        ResourceDraft::create(['resource_type'=>'device_template','resource_id'=>$contributed->id,'user_id'=>$user->id,'base_revision_id'=>$revision->id,'snapshot'=>[]]);

        $response=$this->actingAs($user)->getJson('/api/my-work')->assertOk()->assertJsonPath('total',2)->assertJsonMissing(['label'=>'Shared only']);
        $this->assertEqualsCanonicalizing(['Created here','Contributed here'],array_column($response->json('data'),'label'));
        $row=collect($response->json('data'))->firstWhere('label','Contributed here');
        $this->assertEqualsCanonicalizing(['contributor','draft'],$row['relationshipKeys']);
        ResourceCollaborator::where(['resource_type'=>'device_template','resource_id'=>$contributed->id,'user_id'=>$user->id])->delete();
        $this->actingAs($user)->getJson('/api/my-work')->assertOk()->assertJsonPath('total',1)->assertJsonMissing(['label'=>'Contributed here']);
    }

    public function test_device_creation_still_requires_assignment_and_admin_personal_scope_is_not_global():void
    {
        [$org,$user,$other]=$this->context();
        $admin=User::factory()->create(['organization_id'=>$org->id,'status'=>'active','platform_role'=>'platform_admin']);
        $device=Device::create(['organization_id'=>$org->id,'created_by'=>$user->id,'name'=>'Own Device','external_id'=>'work-'.uniqid(),'status'=>'offline']);
        $this->actingAs($user)->getJson('/api/my-work')->assertOk()->assertJsonPath('total',0);
        DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$user->id,'access_level'=>'viewer','assigned_by'=>$other->id]);
        $this->actingAs($user)->getJson('/api/my-work')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.relationshipKeys.0','creator');
        $this->actingAs($admin)->getJson('/api/my-work')->assertOk()->assertJsonPath('total',0);
        $this->actingAs($admin)->getJson('/api/my-work?user_id='.$user->id)->assertUnprocessable();
    }

    public function test_count_preview_filters_dedupe_and_render_have_no_side_effects():void
    {
        [$org,$user]=$this->context();
        $template=DeviceTemplate::create(['organization_id'=>$org->id,'created_by'=>$user->id,'name'=>'Literal %_ Work']);
        $revision=$this->revision('device_template',$template->id,$user,1);
        ResourceDraft::create(['resource_type'=>'device_template','resource_id'=>$template->id,'user_id'=>$user->id,'base_revision_id'=>$revision->id,'snapshot'=>['password'=>'not-presented']]);
        $service=app(MyWorkService::class);
        $this->assertSame(1,$service->count($user));$this->assertCount(1,$service->preview($user,5));
        $this->actingAs($user)->getJson('/api/my-work?q=%25_&relationship=draft')->assertOk()->assertJsonPath('total',1)->assertJsonMissing(['password'=>'not-presented']);
        $this->assertDatabaseCount('resource_revisions',1);$this->assertDatabaseCount('resource_drafts',1);$this->assertDatabaseCount('notifications',0);
    }

    public function test_activity_authorization_precedes_pagination_and_pages_do_not_underfill():void
    {
        [$org,$user,$other]=$this->context();
        $visible=Device::create(['organization_id'=>$org->id,'created_by'=>$other->id,'name'=>'Visible','external_id'=>'visible-'.uniqid(),'status'=>'offline']);
        DeviceAccessAssignment::create(['device_id'=>$visible->id,'user_id'=>$user->id,'access_level'=>'viewer','assigned_by'=>$other->id]);
        $hidden=Device::create(['organization_id'=>$org->id,'created_by'=>$other->id,'name'=>'Hidden','external_id'=>'hidden-'.uniqid(),'status'=>'offline']);
        for($i=1;$i<=25;$i++)$this->revision('device',$hidden->id,$other,$i,now()->addMinutes($i)->toDateTimeString());
        for($i=1;$i<=15;$i++)$this->revision('device',$visible->id,$other,$i,now()->subMinutes($i)->toDateTimeString());
        $page1=$this->actingAs($user)->getJson('/api/activity?category=changes&per_page=10&page=1')->assertOk()->assertJsonCount(10,'data');
        $page2=$this->actingAs($user)->getJson('/api/activity?category=changes&per_page=10&page=2')->assertOk()->assertJsonCount(5,'data');
        $ids=array_merge(array_column($page1->json('data'),'id'),array_column($page2->json('data'),'id'));
        $this->assertCount(15,array_unique($ids));$this->assertTrue(collect(array_merge($page1->json('data'),$page2->json('data')))->every(fn($row)=>$row['resourceLabel']==='Visible'));
        $this->actingAs($user)->getJson('/api/activity?user_id='.$other->id)->assertUnprocessable();
    }

    public function test_section_membership_precedes_pagination():void
    {
        [$org,$user,$other]=$this->context();
        $device=Device::create(['organization_id'=>$org->id,'created_by'=>$other->id,'name'=>'Section Device','external_id'=>'section-'.uniqid(),'status'=>'offline']);
        DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$user->id,'access_level'=>'viewer','assigned_by'=>$other->id]);
        $base=$this->revision('device',$device->id,$other,1,now()->subHour()->toDateTimeString());
        for($i=2;$i<=26;$i++)ResourceRevision::create(['resource_type'=>'device','resource_id'=>$device->id,'revision_number'=>$i,'parent_revision_id'=>$base->id,'created_by'=>$other->id,'change_summary'=>'safe','snapshot_schema_version'=>1,'snapshot'=>[],'changed_sections'=>['metadata'],'checksum'=>hash('sha256',"meta:$i"),'created_at'=>now()->addMinutes($i)]);
        for($i=27;$i<=41;$i++)ResourceRevision::create(['resource_type'=>'device','resource_id'=>$device->id,'revision_number'=>$i,'parent_revision_id'=>$base->id,'created_by'=>$other->id,'change_summary'=>'safe','snapshot_schema_version'=>1,'snapshot'=>[],'changed_sections'=>['parameters'],'checksum'=>hash('sha256',"parameters:$i"),'created_at'=>now()->subMinutes($i)]);
        $this->actingAs($user)->getJson('/api/activity?resource_type=device&section=parameters&per_page=10&page=1')->assertOk()->assertJsonCount(10,'data');
        $this->actingAs($user)->getJson('/api/activity?resource_type=device&section=parameters&per_page=10&page=2')->assertOk()->assertJsonCount(5,'data');
    }
}
