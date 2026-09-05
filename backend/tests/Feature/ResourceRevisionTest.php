<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\CollaborationThread;
use App\Models\Organization;
use App\Models\Location;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ResourceRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_baseline_and_meaningful_mutations_create_one_safe_immutable_chain(): void
    {
        $org=Organization::create(['name'=>'Plant','slug'=>'plant']);$user=$this->user($org);
        $id=$this->actingAs($user)->postJson('/api/devices',['name'=>'Pump','type'=>'sensor','serialNumber'=>'P-1','protocol'=>'mqtt'])->assertCreated()->json('id');
        $this->assertDatabaseHas('resource_revisions',['resource_type'=>'device','resource_id'=>$id,'revision_number'=>1,'created_by'=>$user->id]);
        $this->actingAs($user)->patchJson("/api/devices/$id",['location_id'=>Location::create(['organization_id'=>$org->id,'name'=>'Bay 2','normalized_name'=>'bay 2'])->id])->assertOk();
        $this->actingAs($user)->postJson("/api/devices/$id/parameters",['name'=>'Pressure','key'=>'pressure','data_type'=>'number','unit'=>'bar'])->assertCreated();
        $this->assertSame([1,2,3],ResourceRevision::where('resource_type','device')->where('resource_id',$id)->pluck('revision_number')->all());
        $snapshot=ResourceRevision::latest('id')->firstOrFail()->snapshot;
        $encoded=strtolower(json_encode($snapshot));
        foreach(['last_seen','telemetry','credential','assignment','comment','notification','authorization','password','secret'] as $forbidden)$this->assertStringNotContainsString($forbidden,$encoded);
        $first=ResourceRevision::where('resource_id',$id)->firstOrFail();$this->expectException(LogicException::class);$first->update(['change_summary'=>'forged']);
    }

    public function test_history_is_paginated_and_reauthorized_against_the_current_device(): void
    {
        $org=Organization::create(['name'=>'Plant','slug'=>'plant']);$viewer=$this->user($org,'viewer@test');$full=$this->user($org,'full@test');$other=$this->user(Organization::create(['name'=>'Other','slug'=>'other']),'other@test');$admin=$this->user($org,'admin@test',true);
        $device=Device::create(['organization_id'=>$org->id,'name'=>'Pump','external_id'=>'P-2','type'=>'sensor','protocol'=>'mqtt']);
        DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$viewer->id,'access_level'=>'viewer']);DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$full->id,'access_level'=>'full_access']);
        app(\App\Services\ResourceRevisionService::class)->recordDevice($device,$full,'Initial Device configuration');
        $url="/api/collaboration/resources/device/$device->id/revisions";
        $viewerData=$this->actingAs($viewer)->getJson($url)->assertOk()->assertJsonPath('data.0.revisionNumber',1)->json('data.0.id');
        $this->actingAs($full)->getJson($url)->assertOk()->assertJsonPath('data.0.id',$viewerData);
        $this->actingAs($admin)->getJson($url)->assertOk()->assertJsonPath('data.0.id',$viewerData);
        $this->actingAs($other)->getJson($url)->assertNotFound();
        DeviceAccessAssignment::where('device_id',$device->id)->where('user_id',$viewer->id)->delete();$this->actingAs($viewer)->getJson($url)->assertNotFound();
        $this->actingAs($full)->getJson("$url/999999")->assertNotFound();
    }

    public function test_identical_snapshot_is_a_no_op_and_sequence_is_unique(): void
    {
        $org=Organization::create(['name'=>'Plant','slug'=>'plant']);$user=$this->user($org);$device=Device::create(['organization_id'=>$org->id,'name'=>'Pump','external_id'=>'P-3','type'=>'sensor','protocol'=>'mqtt']);$service=app(\App\Services\ResourceRevisionService::class);
        $one=$service->recordDevice($device,$user,'Baseline');$same=$service->recordDevice($device,$user,'No-op');$this->assertSame($one->id,$same->id);$this->assertDatabaseCount('resource_revisions',1);
        $thread=CollaborationThread::create(['resource_type'=>'device','resource_id'=>$device->id,'created_by'=>$user->id]);$this->assertSame($one->id,$thread->anchor_revision_id);$this->assertDatabaseCount('resource_revisions',1);
        $this->expectException(\Illuminate\Database\QueryException::class);ResourceRevision::create(['resource_type'=>'device','resource_id'=>$device->id,'revision_number'=>1,'change_summary'=>'duplicate','snapshot_schema_version'=>1,'snapshot'=>[],'changed_sections'=>[],'checksum'=>str_repeat('a',64),'created_at'=>now()]);
    }

    private function user(Organization $org,string $email='staff@test',bool $admin=false):User{return User::factory()->create(['organization_id'=>$org->id,'email'=>$email,'role'=>'staff','platform_role'=>$admin?'platform_admin':null,'status'=>'active']);}
}
