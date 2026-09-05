<?php

namespace Tests\Feature;

use App\Models\{Dashboard,DashboardWidget,Device,DeviceAccessAssignment,Organization,ResourceCollaborator,ResourceRevision,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DashboardSharingTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org=Organization::create(['name'=>'Dashboard Sharing','slug'=>'dashboard-sharing']);
        $owner=User::factory()->create(['organization_id'=>$org->id,'role'=>'staff','status'=>'active']);
        $viewer=User::factory()->create(['organization_id'=>$org->id,'role'=>'staff','status'=>'active']);
        $other=User::factory()->create(['organization_id'=>$org->id,'role'=>'staff','status'=>'active']);
        $foreignOrg=Organization::create(['name'=>'Foreign','slug'=>'dashboard-sharing-foreign']);
        $foreign=User::factory()->create(['organization_id'=>$foreignOrg->id,'role'=>'staff','status'=>'active']);
        $device=$org->devices()->create(['name'=>'Private Pump','external_id'=>'shared-dashboard-pump','type'=>'sensor','protocol'=>'mqtt','status'=>'online']);
        DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$owner->id,'access_level'=>'full_access']);
        $dashboard=Dashboard::create(['organization_id'=>$org->id,'owner_user_id'=>$owner->id,'name'=>'Operations','scope_type'=>'personal','is_default'=>true,'created_by'=>$owner->id,'updated_by'=>$owner->id]);
        $widget=DashboardWidget::create(['id'=>(string)Str::uuid(),'dashboard_id'=>$dashboard->id,'widget_type'=>'metric','title'=>'Pressure','layout'=>['x'=>0,'y'=>0,'w'=>3,'h'=>3],'configuration'=>['deviceId'=>$device->id,'telemetryKey'=>'pressure'],'position'=>0]);
        return compact('org','owner','viewer','other','foreign','device','dashboard','widget');
    }

    private function invite(User $owner,Dashboard $dashboard,User $viewer,string $permission='view'): int
    {
        return $this->actingAs($owner)->postJson('/api/shares',['resource_type'=>'dashboard','resource_id'=>$dashboard->id,'recipient_id'=>$viewer->id,'permission'=>$permission])->assertCreated()->json('data.id');
    }

    public function test_personal_dashboard_share_supports_view_and_edit_without_admin_approval(): void
    {
        extract($this->fixture());
        $this->actingAs($owner)->getJson("/api/dashboards/{$dashboard->id}")->assertOk()->assertJsonPath('canShare',true);
        $share=$this->invite($owner,$dashboard,$viewer);
        $this->actingAs($viewer)->getJson("/api/dashboards/{$dashboard->id}")->assertNotFound();
        $this->actingAs($viewer)->postJson("/api/shares/{$share}/accept")->assertOk()->assertJsonPath('data.status','approved')->assertJsonPath('data.final_permission','view');
        $this->assertDatabaseHas('resource_collaborators',['resource_type'=>'dashboard','resource_id'=>$dashboard->id,'user_id'=>$viewer->id,'permission'=>'view']);
        $this->assertDatabaseCount('dashboards',1);$this->assertDatabaseCount('dashboard_widgets',1);$this->assertDatabaseHas('resource_revisions',['resource_type'=>'dashboard','resource_id'=>$dashboard->id,'revision_number'=>1]);
    }

    public function test_viewer_gets_same_resource_read_only_with_per_source_redaction(): void
    {
        extract($this->fixture());$share=$this->invite($owner,$dashboard,$viewer);$this->actingAs($viewer)->postJson("/api/shares/{$share}/accept")->assertOk();
        $this->actingAs($viewer)->getJson("/api/dashboards/{$dashboard->id}")->assertOk()->assertJsonPath('id',(string)$dashboard->id)->assertJsonPath('canEdit',false)->assertJsonPath('canShare',false)->assertJsonPath('widgets.0.id',$widget->id)->assertJsonPath('widgets.0.available',false)->assertJsonPath('widgets.0.settings.datasource',null);
        $this->actingAs($viewer)->putJson("/api/dashboards/{$dashboard->id}",['name'=>'Take over','widgets'=>[]])->assertNotFound();
        $this->actingAs($viewer)->postJson("/api/dashboards/{$dashboard->id}/duplicate")->assertNotFound();
        DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$viewer->id,'access_level'=>'viewer']);
        $this->actingAs($viewer)->getJson("/api/dashboards/{$dashboard->id}")->assertOk()->assertJsonPath('widgets.0.available',true)->assertJsonPath('widgets.0.settings.datasource.deviceId',(string)$device->id);
    }

    public function test_shared_with_me_comments_and_immediate_revoke_use_current_grant(): void
    {
        extract($this->fixture());$share=$this->invite($owner,$dashboard,$viewer);$this->actingAs($viewer)->postJson("/api/shares/{$share}/accept")->assertOk();
        $this->actingAs($viewer)->getJson('/api/shared-with-me?resource_type=dashboard')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.resourceId',(string)$dashboard->id);
        $thread=$this->actingAs($viewer)->postJson("/api/collaboration/dashboard/{$dashboard->id}/threads",['anchor_type'=>'dashboard_widget','anchor_key'=>$widget->id,'body'=>'Shared viewer comment'])->assertCreated()->json('id');
        $this->actingAs($other)->postJson("/api/shares/{$share}/revoke")->assertNotFound();
        $this->actingAs($viewer)->postJson("/api/shares/{$share}/revoke")->assertNotFound();
        $this->actingAs($owner)->postJson("/api/shares/{$share}/revoke")->assertOk()->assertJsonPath('data.status','revoked');
        $this->assertDatabaseMissing('resource_collaborators',['resource_type'=>'dashboard','resource_id'=>$dashboard->id,'user_id'=>$viewer->id]);
        $this->assertDatabaseHas('collaboration_threads',['id'=>$thread,'resource_type'=>'dashboard','resource_id'=>$dashboard->id]);
        $this->actingAs($viewer)->getJson("/api/dashboards/{$dashboard->id}")->assertNotFound();
        $this->actingAs($viewer)->getJson("/api/collaboration/threads/{$thread}")->assertNotFound();
        $this->actingAs($viewer)->getJson('/api/shared-with-me?resource_type=dashboard')->assertOk()->assertJsonCount(0,'data');
        $this->assertDatabaseHas('device_access_assignments',['device_id'=>$device->id,'user_id'=>$owner->id]);
        $this->assertDatabaseCount('dashboards',1);$this->assertDatabaseCount('dashboard_widgets',1);$this->assertDatabaseCount('resource_revisions',0);
    }

    public function test_cross_org_nonpersonal_and_stale_acceptance_are_rejected(): void
    {
        extract($this->fixture());
        $this->actingAs($owner)->postJson('/api/shares',['resource_type'=>'dashboard','resource_id'=>$dashboard->id,'recipient_id'=>$foreign->id,'permission'=>'view'])->assertUnprocessable();
        $deviceDashboard=Dashboard::create(['organization_id'=>$org->id,'device_id'=>$device->id,'name'=>'Device','scope_type'=>'device']);
        $this->actingAs($owner)->postJson('/api/shares',['resource_type'=>'dashboard','resource_id'=>$deviceDashboard->id,'recipient_id'=>$viewer->id,'permission'=>'view'])->assertNotFound();
        $global=Dashboard::create(['owner_user_id'=>$owner->id,'name'=>'Global','scope_type'=>'admin_global']);
        $this->actingAs($owner)->postJson('/api/shares',['resource_type'=>'dashboard','resource_id'=>$global->id,'recipient_id'=>$viewer->id,'permission'=>'view'])->assertNotFound();
        $share=$this->invite($owner,$dashboard,$viewer);$viewer->update(['status'=>'inactive']);
        $this->actingAs($viewer)->postJson("/api/shares/{$share}/accept")->assertForbidden();
        $this->assertDatabaseMissing('resource_collaborators',['resource_type'=>'dashboard','resource_id'=>$dashboard->id,'user_id'=>$viewer->id]);
    }
}
