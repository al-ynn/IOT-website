<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\FirmwareArtifact;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FirmwareOtaTest extends TestCase
{
    use RefreshDatabase;
    private function org(string $slug):Organization{return Organization::create(['name'=>$slug,'slug'=>$slug]);}
    private function user(Organization $org,string $role='owner'):User{return User::factory()->create(['organization_id'=>$org->id,'role'=>$role,'status'=>'active']);}
    private function device(Organization $org,string $name,string $type='pump',string $protocol='mqtt'):Device{return $org->devices()->create(['name'=>$name,'external_id'=>str($name)->slug().uniqid(),'type'=>$type,'protocol'=>$protocol]);}
    private function assign(User $user,Device $device,string $level):void{DeviceAccessAssignment::create(['user_id'=>$user->id,'device_id'=>$device->id,'access_level'=>$level]);}
    private function upload(User $user,string $name='pump.bin',array $extra=[]){return $this->actingAs($user)->post('/api/firmware/artifacts',['name'=>'Pump firmware','version'=>'1.0.0','firmware'=>UploadedFile::fake()->create($name,4,'application/octet-stream'),...$extra],['Accept'=>'application/json']);}
    private function approve(User $editor,int|string $artifactId):void{$admin=User::factory()->create(['organization_id'=>$editor->organization_id,'role'=>'owner','platform_role'=>'platform_admin','status'=>'active']);$submission=$this->actingAs($editor)->postJson("/api/firmware/artifacts/{$artifactId}/release-submissions")->assertCreated()->json('data.submissionId');$this->actingAs($admin)->postJson("/api/admin/firmware-submissions/{$submission}/claim")->assertOk();$this->actingAs($admin)->postJson("/api/admin/firmware-submissions/{$submission}/approve")->assertOk();}

    public function test_private_upload_is_authorized_scoped_hashed_and_server_derived():void
    {
        Storage::fake('local');$a=$this->org('firmware-a');$b=$this->org('firmware-b');$owner=$this->user($a);$foreign=$this->user($b);$staff=$this->user($a,'staff');
        $this->getJson('/api/firmware/artifacts')->assertUnauthorized();$this->actingAs($staff)->getJson('/api/firmware/artifacts')->assertOk();$this->upload($staff)->assertForbidden();
        $response=$this->upload($owner,'../../evil.bin',['organization_id'=>$b->id,'uploaded_by'=>$foreign->id,'storage_path'=>'../../public/evil.bin','sha256'=>'fake','size_bytes'=>999])->assertCreated();$id=$response->json('data.id');$artifact=FirmwareArtifact::findOrFail($id);
        $this->assertSame($a->id,$artifact->organization_id);$this->assertSame($owner->id,$artifact->uploaded_by);$this->assertNotSame('fake',$artifact->sha256);$this->assertSame(64,strlen($artifact->sha256));$this->assertNotSame(999,$artifact->size_bytes);$this->assertStringStartsWith('firmware/'.$a->id.'/',$artifact->storage_path);$this->assertStringNotContainsString('..',$artifact->storage_path);$this->assertSame('evil.bin',$artifact->original_filename);Storage::disk('local')->assertExists($artifact->storage_path);
        $this->actingAs($foreign)->getJson("/api/firmware/artifacts/$id")->assertNotFound();$this->actingAs($foreign)->get("/api/firmware/artifacts/$id/download")->assertNotFound();
    }

    public function test_upload_rejects_disallowed_and_oversized_files():void
    {
        Storage::fake('local');$org=$this->org('firmware-validation');$owner=$this->user($org);$this->upload($owner,'malware.exe')->assertUnprocessable();$this->actingAs($owner)->post('/api/firmware/artifacts',['name'=>'Large','version'=>'1','firmware'=>UploadedFile::fake()->create('large.bin',11000)],['Accept'=>'application/json'])->assertUnprocessable();$this->assertDatabaseCount('firmware_artifacts',0);
    }

    public function test_download_is_authenticated_and_binary_metadata_is_immutable():void
    {
        Storage::fake('local');$org=$this->org('firmware-download');$owner=$this->user($org);$id=$this->upload($owner)->assertCreated()->json('data.id');$artifact=FirmwareArtifact::findOrFail($id);$this->actingAs($owner)->get("/api/firmware/artifacts/$id/download")->assertOk()->assertHeader('x-content-sha256',$artifact->sha256);
        $this->actingAs($owner)->patchJson("/api/firmware/artifacts/$id",['description'=>'Updated','storage_path'=>'public/attack','sha256'=>'injected','size_bytes'=>1,'version'=>'9.9.9'])->assertOk()->assertJsonPath('data.description','Updated');$artifact->refresh();$this->assertNotSame('public/attack',$artifact->storage_path);$this->assertNotSame('injected',$artifact->sha256);$this->assertSame('1.0.0',$artifact->version);
    }

    public function test_deployment_requires_full_access_compatibility_and_is_atomic():void
    {
        Storage::fake('local');$org=$this->org('firmware-deploy');$other=$this->org('firmware-other');$owner=$this->user($org);$artifactId=$this->upload($owner,'pump.bin',['device_type'=>'pump','protocol'=>'mqtt'])->assertCreated()->json('data.id');$full=$this->device($org,'Full');$viewer=$this->device($org,'Viewer');$unassigned=$this->device($org,'Hidden');$incompatible=$this->device($org,'Sensor','sensor','http');$foreign=$this->device($other,'Foreign');$this->assign($owner,$full,'full_access');$this->assign($owner,$viewer,'viewer');$this->assign($owner,$incompatible,'full_access');
        $url='/api/firmware/deployments';$this->actingAs($owner)->postJson($url,['firmware_artifact_id'=>$artifactId,'device_ids'=>[$full->id,$viewer->id]])->assertUnprocessable();$this->assertDatabaseCount('firmware_deployments',0);$this->actingAs($owner)->postJson($url,['firmware_artifact_id'=>$artifactId,'device_ids'=>[$unassigned->id]])->assertUnprocessable();$this->actingAs($owner)->postJson($url,['firmware_artifact_id'=>$artifactId,'device_ids'=>[$foreign->id]])->assertUnprocessable();$this->actingAs($owner)->postJson($url,['firmware_artifact_id'=>$artifactId,'device_ids'=>[$full->id,$incompatible->id]])->assertUnprocessable();$this->actingAs($owner)->postJson($url,['firmware_artifact_id'=>$artifactId,'device_ids'=>[$full->id,$full->id]])->assertUnprocessable();$this->assertDatabaseCount('firmware_deployment_devices',0);
    }

    public function test_valid_deployment_is_honestly_unavailable_and_never_fakes_success():void
    {
        Storage::fake('local');$org=$this->org('firmware-honest');$owner=$this->user($org);$artifactId=$this->upload($owner)->assertCreated()->json('data.id');$this->approve($owner,$artifactId);$device=$this->device($org,'Pump');$this->assign($owner,$device,'full_access');$response=$this->actingAs($owner)->postJson('/api/firmware/deployments',['firmware_artifact_id'=>$artifactId,'device_ids'=>[$device->id],'status'=>'completed','completed_at'=>now(),'target_status'=>'completed'])->assertCreated()->assertJsonPath('data.status','delivery_unavailable')->assertJsonPath('data.targets.0.status','delivery_unavailable');$id=$response->json('data.id');$this->assertDatabaseHas('firmware_deployments',['id'=>$id,'status'=>'delivery_unavailable']);$this->assertDatabaseHas('operational_events',['firmware_deployment_id'=>$id,'event_type'=>'firmware_delivery_unavailable']);$this->assertDatabaseMissing('operational_events',['firmware_deployment_id'=>$id,'event_type'=>'firmware_deployment_completed']);$this->assertNull($device->refresh()->firmware_version);
    }

    public function test_artifact_deletion_cleans_unused_file_but_preserves_deployment_history():void
    {
        Storage::fake('local');$org=$this->org('firmware-delete');$owner=$this->user($org);$unused=$this->upload($owner,'unused.bin',['version'=>'1'])->assertCreated()->json('data.id');$path=FirmwareArtifact::find($unused)->storage_path;$this->actingAs($owner)->deleteJson("/api/firmware/artifacts/$unused")->assertNoContent();Storage::disk('local')->assertMissing($path);
        $used=$this->upload($owner,'used.bin',['version'=>'2'])->assertCreated()->json('data.id');$this->approve($owner,$used);$device=$this->device($org,'Pump');$this->assign($owner,$device,'full_access');$this->actingAs($owner)->postJson('/api/firmware/deployments',['firmware_artifact_id'=>$used,'device_ids'=>[$device->id]])->assertCreated();$this->actingAs($owner)->deleteJson("/api/firmware/artifacts/$used")->assertUnprocessable();$this->assertDatabaseHas('firmware_artifacts',['id'=>$used]);$this->assertDatabaseHas('devices',['id'=>$device->id]);
    }
}
