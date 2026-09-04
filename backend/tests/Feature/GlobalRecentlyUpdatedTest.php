<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Device;
use App\Models\DeviceTemplate;
use App\Models\FirmwareArtifact;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Report;
use App\Models\ResourceRevision;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class GlobalRecentlyUpdatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_inventory_types_use_latest_allowlisted_post_creation_revision(): void
    {
        Carbon::setTestNow('2026-08-28 12:00:00');
        [$org,$admin,$actor]=$this->actors();
        $resources=[
            'device'=>Device::create(['organization_id'=>$org->id,'created_by'=>$actor->id,'name'=>'Device','external_id'=>'UP-1','type'=>'sensor','protocol'=>'mqtt']),
            'device_template'=>DeviceTemplate::create(['organization_id'=>$org->id,'created_by'=>$actor->id,'name'=>'Template']),
            'automation'=>Automation::create(['organization_id'=>$org->id,'name'=>'Automation','created_by'=>$actor->id,'updated_by'=>$actor->id]),
            'report'=>Report::create(['organization_id'=>$org->id,'name'=>'Report','report_type'=>'device_summary','configuration'=>[],'created_by'=>$actor->id]),
            'webhook'=>Webhook::create(['organization_id'=>$org->id,'name'=>'Webhook','url'=>'https://example.test','event_types'=>[],'signing_secret'=>'not-visible','secret_prefix'=>'x','created_by'=>$actor->id]),
            'location'=>Location::create(['organization_id'=>$org->id,'name'=>'Location','normalized_name'=>'location','created_by'=>$actor->id]),
            'firmware'=>FirmwareArtifact::create(['organization_id'=>$org->id,'uploaded_by'=>$actor->id,'name'=>'Firmware','version'=>'1','storage_disk'=>'local','storage_path'=>'a','original_filename'=>'a','mime_type'=>'application/octet-stream','size_bytes'=>1,'sha256'=>str_repeat('a',64)]),
        ];
        $sections=['device'=>'metadata','device_template'=>'parameters','automation'=>'actions','report'=>'configuration','webhook'=>'configuration','location'=>'metadata','firmware'=>'metadata'];
        foreach($resources as $type=>$resource){$base=$this->revision($type,$resource->id,1,null,$actor,[$sections[$type]],now()->subDays(2));$this->revision($type,$resource->id,2,$base,$actor,[$sections[$type]],now()->subHour());}

        $response=$this->actingAs($admin)->getJson('/api/admin/recently-updated?per_page=25')->assertOk()->assertJsonCount(7,'data');
        $this->assertSame(['automation','device','device_template','firmware','location','report','webhook'],collect($response->json('data'))->pluck('resourceType')->sort()->values()->all());
        $response->assertJsonPath('data.0.revision.number',2)->assertJsonPath('data.0.actor.id',(string)$actor->id);
        $this->assertStringNotContainsString('not-visible',$response->getContent());
    }

    public function test_creation_unknown_author_and_unknown_sections_fail_closed_and_latest_eligible_wins(): void
    {
        Carbon::setTestNow('2026-08-28 12:00:00');
        [$org,$admin,$actor]=$this->actors();
        $device=Device::create(['organization_id'=>$org->id,'created_by'=>$actor->id,'name'=>'Pump','external_id'=>'UP-2','type'=>'sensor','protocol'=>'mqtt']);
        $base=$this->revision('device',$device->id,1,null,$actor,['metadata'],now()->subDays(3));
        $meaningful=$this->revision('device',$device->id,2,$base,$actor,['parameters'],now()->subDays(2));
        $unknown=$this->revision('device',$device->id,3,$meaningful,$actor,['future_section'],now()->subDay());
        $this->revision('device',$device->id,4,$unknown,null,['metadata'],now());

        $this->actingAs($admin)->getJson('/api/admin/recently-updated?resource_type=device&window=7d')
            ->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.revision.id',(string)$meaningful->id)->assertJsonPath('data.0.revision.changedSections.0','parameters');
        $this->actingAs($actor)->getJson('/api/admin/recently-updated')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/admin/recently-updated?resource_type=App%5CModels%5CDevice')->assertUnprocessable();
    }

    public function test_device_location_only_revision_does_not_advance_meaningful_recency(): void
    {
        [$org,$admin,$actor]=$this->actors();
        $id=$this->actingAs($actor)->postJson('/api/devices',['name'=>'Pump','type'=>'sensor','serialNumber'=>'UP-3','protocol'=>'mqtt'])->assertCreated()->json('id');
        $this->actingAs($actor)->patchJson('/api/devices/'.$id,['name'=>'Pump Updated'])->assertOk();
        $meaningful=ResourceRevision::where(['resource_type'=>'device','resource_id'=>$id])->latest('id')->firstOrFail();
        $location=Location::create(['organization_id'=>$org->id,'name'=>'Bay','normalized_name'=>'bay','created_by'=>$actor->id]);
        $this->actingAs($actor)->patchJson('/api/devices/'.$id,['location_id'=>$location->id])->assertOk();
        $latest=ResourceRevision::where(['resource_type'=>'device','resource_id'=>$id])->latest('id')->firstOrFail();
        $this->assertSame([], $latest->changed_sections);
        $this->actingAs($admin)->getJson('/api/admin/recently-updated?resource_type=device')->assertOk()->assertJsonPath('data.0.revision.id',(string)$meaningful->id);
    }

    private function revision(string$type,int$id,int$number,ResourceRevision|int|null$parent,?User$actor,array$sections,$at): ResourceRevision
    {
        $actorId=$actor instanceof User?$actor->id:null;
        return ResourceRevision::create(['resource_type'=>$type,'resource_id'=>$id,'revision_number'=>$number,'parent_revision_id'=>$parent instanceof ResourceRevision?$parent->id:$parent,'created_by'=>$actorId,'change_summary'=>'Configuration updated','snapshot_schema_version'=>1,'snapshot'=>[],'changed_sections'=>$sections,'checksum'=>hash('sha256',$type.$id.$number),'created_at'=>$at]);
    }

    private function actors(): array
    {
        $org=Organization::create(['name'=>'Updated Org','slug'=>'updated-org'.uniqid()]);
        $admin=User::factory()->create(['organization_id'=>$org->id,'role'=>'staff','platform_role'=>'platform_admin','status'=>'active']);
        $actor=User::factory()->create(['organization_id'=>$org->id,'role'=>'owner','status'=>'active']);
        return[$org,$admin,$actor];
    }
}