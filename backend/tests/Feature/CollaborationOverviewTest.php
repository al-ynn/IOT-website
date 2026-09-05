<?php

namespace Tests\Feature;

use App\Models\{Device,DeviceAccessAssignment,DeviceTemplate,Notification,OperationalEvent,Organization,ResourceCollaborator,ResourceRevision,User};
use App\Services\{MyWorkService,SharedWithMeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CollaborationOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function context(bool $admin=false):array
    {
        $org=Organization::create(['name'=>'Overview Org','slug'=>'overview-'.uniqid()]);
        $user=User::factory()->create(['organization_id'=>$org->id,'status'=>'active','role'=>'staff','platform_role'=>$admin?'platform_admin':null]);
        $other=User::factory()->create(['organization_id'=>$org->id,'status'=>'active','role'=>'staff']);
        return[$org,$user,$other];
    }

    private function revision(string$type,int$id,User$user,int$number=1):ResourceRevision
    {
        return ResourceRevision::create(['resource_type'=>$type,'resource_id'=>$id,'revision_number'=>$number,'created_by'=>$user->id,'change_summary'=>'safe','snapshot_schema_version'=>1,'snapshot'=>['password'=>'never return'],'changed_sections'=>[],'checksum'=>hash('sha256',"$type:$id:$number"),'created_at'=>now()]);
    }

    public function test_route_is_personal_server_derived_and_rejects_scope_overrides():void
    {
        [, $staff]=$this->context();
        $this->getJson('/api/collaboration-overview')->assertUnauthorized();
        $this->actingAs($staff)->getJson('/api/collaboration-overview')->assertOk()->assertJsonPath('context','app_personal');
        foreach(['user_id=2','organization_id=2','admin=true','global=true','provider=App%5CModels%5CUser']as$query)$this->actingAs($staff)->getJson('/api/collaboration-overview?'.$query)->assertUnprocessable();
    }

    public function test_counts_and_previews_delegate_to_canonical_personal_providers():void
    {
        [$org,$user,$other]=$this->context();
        $own=DeviceTemplate::create(['organization_id'=>$org->id,'created_by'=>$user->id,'name'=>'Own Work']);
        $shared=DeviceTemplate::create(['organization_id'=>$org->id,'created_by'=>$other->id,'name'=>'Shared Work']);
        ResourceCollaborator::create(['resource_type'=>'device_template','resource_id'=>$shared->id,'user_id'=>$user->id,'permission'=>'view','granted_by'=>$other->id]);
        for($i=0;$i<7;$i++)Notification::create(['organization_id'=>$org->id,'user_id'=>$user->id,'type'=>'system.notice','category'=>'system','title'=>'Notice '.$i,'message'=>'Safe message','deduplication_key'=>'overview-'.$i]);
        $this->revision('device_template',$own->id,$user);

        $response=$this->actingAs($user)->getJson('/api/collaboration-overview')->assertOk()
            ->assertJsonPath('sections.myWork.count',app(MyWorkService::class)->count($user))
            ->assertJsonPath('sections.sharedWithMe.count',app(SharedWithMeService::class)->count($user))
            ->assertJsonPath('sections.notifications.count',7)
            ->assertJsonCount(1,'sections.myWork.preview')->assertJsonCount(1,'sections.sharedWithMe.preview')->assertJsonCount(5,'sections.notifications.preview')
            ->assertJsonPath('sections.search.destination','/app/search');
        $json=$response->getContent();$this->assertStringNotContainsString('password',$json);$this->assertStringNotContainsString('never return',$json);
    }

    public function test_admin_app_overview_remains_personal_and_runtime_data_is_excluded():void
    {
        [$org,$admin,$other]=$this->context(true);
        $device=Device::create(['organization_id'=>$org->id,'created_by'=>$other->id,'name'=>'Organization Device','external_id'=>'overview-'.uniqid(),'status'=>'offline']);
        $this->revision('device',$device->id,$other);
        OperationalEvent::create(['organization_id'=>$org->id,'device_id'=>$device->id,'event_type'=>'heartbeat','severity'=>'info','title'=>'Runtime','message'=>'Runtime','source'=>'device','occurred_at'=>now()]);
        $this->actingAs($admin)->getJson('/api/collaboration-overview')->assertOk()
            ->assertJsonPath('sections.myWork.count',0)->assertJsonPath('sections.sharedWithMe.count',0)
            ->assertJsonCount(0,'sections.activity.preview')->assertJsonCount(5,'adminShortcuts');
        DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$admin->id,'access_level'=>'viewer','assigned_by'=>$other->id]);
        $this->actingAs($admin)->getJson('/api/collaboration-overview')->assertOk()->assertJsonPath('sections.myWork.count',0)->assertJsonPath('sections.sharedWithMe.count',1);
    }

    public function test_admin_activity_preview_is_personal():void
    {
        [$org,$admin,$other]=$this->context(true);
        $template=DeviceTemplate::create(['organization_id'=>$org->id,'created_by'=>$other->id,'name'=>'Private Template']);
        $this->revision('device_template',$template->id,$other);
        $this->actingAs($admin)->getJson('/api/collaboration-overview')->assertOk()
            ->assertJsonCount(0,'sections.activity.preview');
    }

    public function test_render_is_read_only_and_query_count_is_bounded():void
    {
        [$org,$user,$other]=$this->context();
        $device=Device::create(['organization_id'=>$org->id,'created_by'=>$user->id,'name'=>'Own Device','external_id'=>'own-'.uniqid(),'status'=>'offline']);
        DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$user->id,'access_level'=>'full_access','assigned_by'=>$user->id]);
        $before=['revisions'=>DB::table('resource_revisions')->count(),'states'=>DB::table('resource_revision_states')->count(),'drafts'=>DB::table('resource_drafts')->count(),'shares'=>DB::table('resource_share_requests')->count(),'grants'=>DB::table('resource_collaborators')->count(),'assignments'=>DB::table('device_access_assignments')->count(),'notifications'=>DB::table('notifications')->count()];
        DB::flushQueryLog();DB::enableQueryLog();$this->actingAs($user)->getJson('/api/collaboration-overview')->assertOk();$queries=count(DB::getQueryLog());DB::disableQueryLog();
        if(getenv('PHASE102_REPORT'))fwrite(STDERR,"PHASE102 collaboration_overview_queries={$queries}\n");
        $this->assertLessThan(100,$queries);
        $this->assertSame($before['revisions'],DB::table('resource_revisions')->count());$this->assertSame($before['states'],DB::table('resource_revision_states')->count());$this->assertSame($before['drafts'],DB::table('resource_drafts')->count());$this->assertSame($before['shares'],DB::table('resource_share_requests')->count());$this->assertSame($before['grants'],DB::table('resource_collaborators')->count());$this->assertSame($before['assignments'],DB::table('device_access_assignments')->count());$this->assertSame($before['notifications'],DB::table('notifications')->count());
    }
}
