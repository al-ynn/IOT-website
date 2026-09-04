<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Dashboard;
use App\Models\Device;
use App\Models\DeviceTemplate;
use App\Models\FirmwareArtifact;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Report;
use App\Models\User;
use App\Models\Webhook;
use App\Services\ResourceLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class GlobalRecentlyCreatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_gets_one_globally_ordered_row_per_inventory_resource(): void
    {
        Carbon::setTestNow('2026-08-28 12:00:00');
        [$organization, $admin, $creator] = $this->actors();
        $device = Device::create(['organization_id'=>$organization->id,'created_by'=>$creator->id,'name'=>'Device','external_id'=>'REC-1','type'=>'sensor','protocol'=>'mqtt','created_at'=>now()->subDays(7)]);
        DeviceTemplate::create(['organization_id'=>$organization->id,'created_by'=>$creator->id,'name'=>'Template','created_at'=>now()->subDays(6)]);
        Automation::create(['organization_id'=>$organization->id,'name'=>'Automation','created_by'=>$creator->id,'updated_by'=>$creator->id,'created_at'=>now()->subDays(5)]);
        Report::create(['organization_id'=>$organization->id,'name'=>'Report','report_type'=>'device_summary','configuration'=>[],'created_by'=>$creator->id,'created_at'=>now()->subDays(4)]);
        Webhook::create(['organization_id'=>$organization->id,'name'=>'Webhook','url'=>'https://example.test','event_types'=>[],'signing_secret'=>'secret-not-visible','secret_prefix'=>'sec','created_by'=>$creator->id,'created_at'=>now()->subDays(3)]);
        Location::create(['organization_id'=>$organization->id,'name'=>'Location','normalized_name'=>'location','created_by'=>$creator->id,'created_at'=>now()->subDays(2)]);
        FirmwareArtifact::create(['organization_id'=>$organization->id,'uploaded_by'=>$creator->id,'name'=>'Firmware','version'=>'1','storage_disk'=>'local','storage_path'=>'a','original_filename'=>'a','mime_type'=>'application/octet-stream','size_bytes'=>1,'sha256'=>str_repeat('a',64),'created_at'=>now()->subDay()]);
        foreach ([["devices",$device->id,7],["device_templates",DeviceTemplate::first()->id,6],["automations",Automation::first()->id,5],["reports",Report::first()->id,4],["webhooks",Webhook::first()->id,3],["locations",Location::first()->id,2],["firmware_artifacts",FirmwareArtifact::first()->id,1]] as [$table,$id,$days]) DB::table($table)->where("id",$id)->update(["created_at"=>now()->subDays($days)]);
        Dashboard::create(['organization_id'=>$organization->id,'owner_user_id'=>$creator->id,'name'=>'Private Dashboard','scope_type'=>'personal','created_by'=>$creator->id,'updated_by'=>$creator->id]);

        $response=$this->actingAs($admin)->getJson('/api/admin/recently-created?per_page=25')->assertOk()->assertJsonCount(7,'data');
        $this->assertSame(['firmware','location','webhook','report','automation','device_template','device'], collect($response->json('data'))->pluck('resourceType')->all());
        $response->assertJsonPath('data.0.actor.id',(string)$creator->id)->assertJsonPath('data.0.resourceLink','/admin/resources/firmware/'.FirmwareArtifact::first()->id);
        $this->assertStringNotContainsString('Private Dashboard',$response->getContent());
        $this->assertStringNotContainsString('secret-not-visible',$response->getContent());
        $device->update(['name'=>'Renamed','updated_at'=>now()]);
        $this->assertSame('firmware',$this->actingAs($admin)->getJson('/api/admin/recently-created')->json('data.0.resourceType'));
    }

    public function test_filters_are_server_side_and_staff_and_unknown_types_fail_closed(): void
    {
        Carbon::setTestNow('2026-08-28 12:00:00');
        [$organization,$admin,$creator]=$this->actors();
        $old=Device::create(['organization_id'=>$organization->id,'created_by'=>$creator->id,'name'=>'Old Pump','external_id'=>'OLD','type'=>'sensor','protocol'=>'mqtt','created_at'=>now()->subDays(10)]);
        Device::create(['organization_id'=>$organization->id,'created_by'=>$creator->id,'name'=>'Recent Pump','external_id'=>'NEW','type'=>'sensor','protocol'=>'mqtt','created_at'=>now()->subHours(2)]);
        DB::table("devices")->where("id",$old->id)->update(["created_at"=>now()->subDays(10)]);
        app(ResourceLifecycleService::class)->disable($admin,'device',$old->id);

        $this->actingAs($admin)->getJson('/api/admin/recently-created?resource_type=device&window=24h&search=Recent&lifecycle=active')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.resourceLabel','Recent Pump');
        $this->actingAs($admin)->getJson('/api/admin/recently-created?resource_type=device&lifecycle=disabled')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.occurredAt','2026-08-18T12:00:00.000000Z');
        $this->actingAs($creator)->getJson('/api/admin/recently-created')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/admin/recently-created?resource_type=App%5CModels%5CUser')->assertUnprocessable();
        $this->actingAs($admin)->getJson('/api/admin/recently-created?window=1year')->assertUnprocessable();
    }

    private function actors(): array
    {
        $organization=Organization::create(['name'=>'Recent Org','slug'=>'recent-org']);
        $admin=User::factory()->create(['organization_id'=>$organization->id,'role'=>'staff','platform_role'=>'platform_admin','status'=>'active']);
        $creator=User::factory()->create(['organization_id'=>$organization->id,'role'=>'owner','status'=>'active']);
        return [$organization,$admin,$creator];
    }
}