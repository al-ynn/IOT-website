<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\DashboardWidget;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_default_dashboard_is_personal_and_owner_scoped(): void
    {
        $org=$this->org('one');$staff=$this->user($org);$other=$this->user($org,'other@example.test');
        $dashboard=$this->actingAs($staff)->getJson('/api/dashboards/default')->assertOk()->assertJsonPath('scope','personal')->json();
        $this->actingAs($other)->getJson('/api/dashboards/'.$dashboard['id'])->assertNotFound();
    }

    public function test_viewer_can_save_real_device_widget_but_unassigned_and_cross_org_devices_are_rejected(): void
    {
        $org=$this->org('one');$staff=$this->user($org);$assigned=$this->device($org,'assigned');$unassigned=$this->device($org,'unassigned');
        DeviceAccessAssignment::create(['device_id'=>$assigned->id,'user_id'=>$staff->id,'access_level'=>'viewer']);
        $id=$this->actingAs($staff)->getJson('/api/dashboards/default')->json('id');
        $this->actingAs($staff)->putJson("/api/dashboards/$id",$this->payload($assigned->id))->assertOk()->assertJsonPath('widgets.0.settings.datasource.deviceId',(string)$assigned->id);
        $this->actingAs($staff)->putJson("/api/dashboards/$id",$this->payload($unassigned->id))->assertUnprocessable();
        $foreign=$this->device($this->org('two'),'foreign');
        $this->actingAs($staff)->putJson("/api/dashboards/$id",$this->payload($foreign->id))->assertUnprocessable();
    }

    public function test_assignment_removal_immediately_redacts_widget_source(): void
    {
        $org=$this->org('one');$staff=$this->user($org);$device=$this->device($org,'assigned');$assignment=DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$staff->id,'access_level'=>'full_access']);
        $id=$this->actingAs($staff)->getJson('/api/dashboards/default')->json('id');
        $this->actingAs($staff)->putJson("/api/dashboards/$id",$this->payload($device->id))->assertOk();
        $assignment->delete();
        $this->actingAs($staff)->getJson("/api/dashboards/$id")->assertOk()->assertJsonPath('widgets.0.available',false)->assertJsonPath('widgets.0.settings.datasource',null);
    }

    public function test_personal_dashboard_source_authorization_is_batched_for_many_widgets(): void
    {
        $org=$this->org('one');$staff=$this->user($org);$device=$this->device($org,'batched');
        DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$staff->id,'access_level'=>'viewer']);
        $id=$this->actingAs($staff)->getJson('/api/dashboards/default')->json('id');
        DashboardWidget::create(['id'=>(string)Str::uuid(),'dashboard_id'=>$id,'widget_type'=>'metric','title'=>'Metric 0','layout'=>['x'=>0,'y'=>0,'w'=>3,'h'=>3],'configuration'=>['deviceId'=>$device->id,'telemetryKey'=>'temperature'],'position'=>0]);
        DB::flushQueryLog();DB::enableQueryLog();
        $this->actingAs($staff)->getJson("/api/dashboards/$id")->assertOk()->assertJsonCount(1,'widgets');
        $oneWidgetQueries=count(DB::getQueryLog());DB::disableQueryLog();
        for($index=1;$index<25;$index++)DashboardWidget::create(['id'=>(string)Str::uuid(),'dashboard_id'=>$id,'widget_type'=>'metric','title'=>'Metric '.$index,'layout'=>['x'=>0,'y'=>$index*3,'w'=>3,'h'=>3],'configuration'=>['deviceId'=>$device->id,'telemetryKey'=>'temperature'],'position'=>$index]);
        DB::flushQueryLog();DB::enableQueryLog();
        $this->actingAs($staff)->getJson("/api/dashboards/$id")->assertOk()->assertJsonCount(25,'widgets');
        $queries=count(DB::getQueryLog());DB::disableQueryLog();

        if(getenv('PHASE102_REPORT'))fwrite(STDERR,"PHASE102 dashboard_widgets_1_queries={$oneWidgetQueries} dashboard_widgets_25_queries={$queries}\n");
        $this->assertLessThanOrEqual($oneWidgetQueries+1,$queries);
    }

    public function test_registry_rejects_unknown_types_invalid_layout_ranges_and_extra_configuration(): void
    {
        $org=$this->org('one');$staff=$this->user($org);$device=$this->device($org,'assigned');DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$staff->id,'access_level'=>'viewer']);$id=$this->actingAs($staff)->getJson('/api/dashboards/default')->json('id');
        $bad=$this->payload($device->id);$bad['widgets'][0]['type']='html';$this->actingAs($staff)->putJson("/api/dashboards/$id",$bad)->assertUnprocessable();
        $bad=$this->payload($device->id);$bad['widgets'][0]['layout']['w']=99;$this->actingAs($staff)->putJson("/api/dashboards/$id",$bad)->assertUnprocessable();
        $bad=$this->payload($device->id);$bad['widgets'][0]['settings']['timeRange']='all';$this->actingAs($staff)->putJson("/api/dashboards/$id",$bad)->assertUnprocessable();
    }

    public function test_admin_global_dashboard_is_per_admin_and_staff_is_denied(): void
    {
        $org=$this->org('one');$admin1=$this->user($org,'admin1@example.test',true);$admin2=$this->user($org,'admin2@example.test',true);$staff=$this->user($org);
        $first=$this->actingAs($admin1)->getJson('/api/admin/dashboards/global')->assertOk()->assertJsonPath('scope','admin_global')->json('id');
        $second=$this->actingAs($admin2)->getJson('/api/admin/dashboards/global')->assertOk()->json('id');
        $this->assertNotSame($first,$second);
        $this->actingAs($staff)->getJson('/api/admin/dashboards/global')->assertForbidden();
        $payload=['name'=>'Global Operations','widgets'=>[['id'=>(string)Str::uuid(),'type'=>'global_device_summary','settings'=>['title'=>'Devices'],'layout'=>['x'=>0,'y'=>0,'w'=>6,'h'=>3]]]];
        $this->actingAs($admin1)->putJson('/api/admin/dashboards/global',$payload)->assertOk()->assertJsonPath('widgets.0.type','global_device_summary');
    }

    private function payload(int $deviceId): array { return ['name'=>'My Dashboard','organization_id'=>999,'widgets'=>[['id'=>(string)Str::uuid(),'type'=>'metric','settings'=>['title'=>'Temperature','datasource'=>['deviceId'=>(string)$deviceId,'telemetryKey'=>'temperature','unit'=>'C'],'timeRange'=>'24h'],'layout'=>['x'=>0,'y'=>0,'w'=>3,'h'=>3]]]]; }
    private function org(string $slug): Organization { return Organization::create(['name'=>ucfirst($slug),'slug'=>$slug]); }
    private function user(Organization $org,string $email='staff@example.test',bool $admin=false): User { return User::factory()->create(['organization_id'=>$org->id,'email'=>$email,'role'=>'staff','platform_role'=>$admin?'platform_admin':null,'status'=>'active']); }
    private function device(Organization $org,string $external): Device { return Device::create(['organization_id'=>$org->id,'name'=>ucfirst($external),'external_id'=>$external,'type'=>'sensor','protocol'=>'mqtt','status'=>'online']); }
}
