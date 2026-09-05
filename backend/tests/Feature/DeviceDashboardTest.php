<?php

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_full_access_and_admin_receive_the_same_canonical_layout(): void
    {
        [$org,$viewer,$full,$device]=$this->context();$admin=$this->user($org,'admin@example.test',true);
        $dashboard=$this->actingAs($full)->getJson("/api/devices/$device->id/dashboard")->assertOk()->json();
        $saved=$this->actingAs($full)->patchJson("/api/devices/$device->id/dashboard",$this->payload($device,$dashboard['layoutVersion']))->assertOk()->assertJsonPath('canEdit',true)->json();
        $viewerData=$this->actingAs($viewer)->getJson("/api/devices/$device->id/dashboard")->assertOk()->assertJsonPath('canEdit',false)->json();
        $adminData=$this->actingAs($admin)->getJson("/api/admin/devices/$device->id/dashboard")->assertOk()->assertJsonPath('canEdit',true)->json();
        $this->assertSame($saved['id'],$viewerData['id']);$this->assertSame($saved['id'],$adminData['id']);$this->assertSame($saved['widgets'],$viewerData['widgets']);$this->assertSame($saved['widgets'],$adminData['widgets']);
        $this->assertSame(1,Dashboard::where('device_id',$device->id)->count());
    }

    public function test_viewer_and_unassigned_staff_cannot_mutate_or_resolve_dashboard(): void
    {
        [$org,$viewer,$full,$device]=$this->context();$version=$this->actingAs($viewer)->getJson("/api/devices/$device->id/dashboard")->json('layoutVersion');
        $this->actingAs($viewer)->patchJson("/api/devices/$device->id/dashboard",$this->payload($device,$version))->assertForbidden();
        $unassigned=$this->user($org,'unassigned@example.test');$this->actingAs($unassigned)->getJson("/api/devices/$device->id/dashboard")->assertNotFound();
        $this->actingAs($unassigned)->patchJson("/api/devices/$device->id/dashboard",$this->payload($device,$version))->assertNotFound();
    }

    public function test_device_dashboard_rejects_foreign_sources_global_widgets_and_sensitive_fields_atomically(): void
    {
        [$org,$viewer,$full,$device]=$this->context();$foreign=$this->device($org,'foreign');$version=$this->actingAs($full)->getJson("/api/devices/$device->id/dashboard")->json('layoutVersion');
        $bad=$this->payload($device,$version);$bad['widgets'][0]['settings']['datasource']['deviceId']=(string)$foreign->id;$this->actingAs($full)->patchJson("/api/devices/$device->id/dashboard",$bad)->assertUnprocessable();
        $bad=$this->payload($device,$version);$bad['widgets'][0]['type']='global_device_summary';$this->actingAs($full)->patchJson("/api/devices/$device->id/dashboard",$bad)->assertUnprocessable();
        $bad=$this->payload($device,$version);$bad['widgets'][0]['settings']['credential']='secret';$this->actingAs($full)->patchJson("/api/devices/$device->id/dashboard",$bad)->assertUnprocessable();
        $this->assertDatabaseCount('dashboard_widgets',0);
    }

    public function test_cross_org_admin_route_is_explicit_while_normal_staff_route_remains_scoped(): void
    {
        [$org,$viewer,$full,$device]=$this->context();$foreignUser=$this->user($this->org('other'),'other@example.test');$admin=$this->user($org,'admin@example.test',true);
        $this->actingAs($foreignUser)->getJson("/api/devices/$device->id/dashboard")->assertNotFound();
        $this->actingAs($admin)->getJson("/api/admin/devices/$device->id/dashboard")->assertOk();
    }

    public function test_stale_save_returns_conflict_and_preserves_newer_canonical_state(): void
    {
        [$org,$viewer,$full,$device]=$this->context();$second=$this->user($org,'second@example.test');DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$second->id,'access_level'=>'full_access']);
        $version=$this->actingAs($full)->getJson("/api/devices/$device->id/dashboard")->json('layoutVersion');
        $this->actingAs($second)->patchJson("/api/devices/$device->id/dashboard",$this->payload($device,$version,'Second save'))->assertOk()->assertJsonPath('layoutVersion',$version+1);
        $this->actingAs($full)->patchJson("/api/devices/$device->id/dashboard",$this->payload($device,$version,'Stale save'))->assertConflict();
        $this->actingAs($viewer)->getJson("/api/devices/$device->id/dashboard")->assertJsonPath('name','Second save')->assertJsonCount(1,'widgets');
    }

    public function test_personal_and_device_dashboards_are_isolated(): void
    {
        [$org,$viewer,$full,$device]=$this->context();$personal=$this->actingAs($full)->getJson('/api/dashboards/default')->assertOk()->json();$deviceDashboard=$this->actingAs($full)->getJson("/api/devices/$device->id/dashboard")->assertOk()->json();
        $this->assertNotSame($personal['id'],$deviceDashboard['id']);$this->assertSame('personal',$personal['scope']);$this->assertSame('device',$deviceDashboard['scope']);
    }

    private function payload(Device $device,int $version,string $name='Device Dashboard'): array {return ['name'=>$name,'layoutVersion'=>$version,'widgets'=>[['id'=>(string)Str::uuid(),'type'=>'status','settings'=>['title'=>'Status','datasource'=>['deviceId'=>(string)$device->id,'telemetryKey'=>'']],'layout'=>['x'=>0,'y'=>0,'w'=>3,'h'=>3]]]];}
    private function context(): array {$org=$this->org('main');$viewer=$this->user($org);$full=$this->user($org,'full@example.test');$device=$this->device($org,'device');DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$viewer->id,'access_level'=>'viewer']);DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$full->id,'access_level'=>'full_access']);return [$org,$viewer,$full,$device];}
    private function org(string $slug): Organization{return Organization::create(['name'=>ucfirst($slug),'slug'=>$slug]);}
    private function user(Organization $org,string $email='viewer@example.test',bool $admin=false): User{return User::factory()->create(['organization_id'=>$org->id,'email'=>$email,'role'=>'staff','platform_role'=>$admin?'platform_admin':null,'status'=>'active']);}
    private function device(Organization $org,string $external): Device{return Device::create(['organization_id'=>$org->id,'name'=>ucfirst($external),'external_id'=>$external,'type'=>'sensor','protocol'=>'mqtt','status'=>'online']);}
}
