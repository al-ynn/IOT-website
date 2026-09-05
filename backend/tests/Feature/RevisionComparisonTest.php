<?php

namespace Tests\Feature;

use App\Models\CollaborationThread;
use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\Organization;
use App\Models\ResourceRevision;
use App\Models\User;
use App\Services\RevisionComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class RevisionComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_roles_compare_structured_safe_device_changes(): void
    {
        [$device,$viewer,$full,$admin,$from,$to]=$this->context();
        foreach ([$viewer,$full,$admin] as $user) {
            $this->actingAs($user)->getJson($this->compareUrl($device,$from,$to))->assertOk()
                ->assertJsonPath('resource.id',(string)$device->id)
                ->assertJsonPath('fromRevision.revisionNumber',1)
                ->assertJsonPath('toRevision.revisionNumber',2)
                ->assertJsonFragment(['identity'=>'metadata:name','changeType'=>'changed'])
                ->assertJsonFragment(['identity'=>'dashboard_widget:20','changeType'=>'added'])
                ->assertJsonFragment(['identity'=>'dashboard_widget:10','changeType'=>'changed'])
                ->assertJsonFragment(['identity'=>'parameter:200','changeType'=>'added'])
                ->assertJsonFragment(['identity'=>'parameter:100','changeType'=>'changed'])
                ->assertJsonMissing(['field'=>'last_seen'])
                ->assertJsonMissing(['field'=>'telemetry'])
                ->assertJsonMissing(['secret'=>'do-not-render']);
        }
    }

    public function test_different_resource_cross_org_and_unassigned_pairs_fail_safely(): void
    {
        [$device,$viewer,,,$from,$to,$org]=$this->context();
        $other=Device::create(['organization_id'=>$org->id,'name'=>'Other','type'=>'sensor','serial_number'=>'OTHER','protocol'=>'mqtt']);
        $foreign=$this->revision($other,1,$this->snapshot('Other'));
        $this->actingAs($viewer)->getJson($this->compareUrl($device,$from,$foreign))->assertNotFound();
        $outsider=User::factory()->create(['organization_id'=>Organization::create(['name'=>'Other org','slug'=>'other-org'])->id,'role'=>'staff','status'=>'active']);
        $this->actingAs($outsider)->getJson($this->compareUrl($device,$from,$to))->assertNotFound();
        DeviceAccessAssignment::where('device_id',$device->id)->where('user_id',$viewer->id)->delete();
        $this->actingAs($viewer)->getJson($this->compareUrl($device,$from,$to))->assertNotFound();
    }

    public function test_previous_and_accepted_latest_comparison_do_not_mutate_history(): void
    {
        [$device,$viewer,,,$from,$to]=$this->context();
        $count=ResourceRevision::count();
        $this->actingAs($viewer)->getJson("/api/collaboration/resources/device/$device->id/revisions/$to->id/compare-previous")->assertOk()->assertJsonPath('fromRevision.id',(string)$from->id);
        $this->actingAs($viewer)->getJson("/api/collaboration/resources/device/$device->id/review-changes")->assertOk()->assertJsonPath('summary.intermediateRevisions.0.id',(string)$to->id)->assertJsonPath('summary.contributors.0.name',$to->author->name);
        $this->assertSame($count,ResourceRevision::count());
        $this->expectException(LogicException::class);$from->update(['change_summary'=>'mutated']);
    }

    public function test_unsupported_schema_and_suspicious_legacy_fields_are_safe(): void
    {
        [$device,$viewer,,,$from,$to]=$this->context();
        $snapshot=$to->snapshot;$snapshot['metadata']['authorization']='Bearer secret';$snapshot['dashboard']['widgets'][0]['configuration']['access_token']='secret';
        ResourceRevision::withoutEvents(fn()=>$to->newQuery()->whereKey($to->id)->update(['snapshot'=>json_encode($snapshot)]));
        $this->actingAs($viewer)->getJson($this->compareUrl($device,$from,$to))->assertOk()->assertJsonMissing(['authorization'=>'Bearer secret'])->assertJsonMissing(['access_token'=>'secret']);
        ResourceRevision::withoutEvents(fn()=>$to->newQuery()->whereKey($to->id)->update(['snapshot_schema_version'=>999]));
        $this->actingAs($viewer)->getJson($this->compareUrl($device,$from,$to))->assertUnprocessable()->assertJsonValidationErrors('revisions');
    }

    public function test_comment_anchor_context_is_data_driven(): void
    {
        [$device,$viewer,,,$from]=$this->context();
        $changed=CollaborationThread::create(['resource_type'=>'device','resource_id'=>$device->id,'anchor_type'=>'dashboard_widget','anchor_key'=>'10','anchor_revision_id'=>$from->id,'created_by'=>$viewer->id]);
        $unchanged=CollaborationThread::create(['resource_type'=>'device','resource_id'=>$device->id,'anchor_type'=>'dashboard_widget','anchor_key'=>'999','anchor_revision_id'=>$from->id,'created_by'=>$viewer->id]);
        $legacy=CollaborationThread::create(['resource_type'=>'device','resource_id'=>$device->id,'anchor_type'=>'resource','anchor_revision_id'=>null,'created_by'=>$viewer->id]);
        CollaborationThread::withoutEvents(fn()=>CollaborationThread::whereKey($legacy->id)->update(['anchor_revision_id'=>null]));$legacy->refresh();
        $service=app(RevisionComparisonService::class);
        $this->assertSame('changed',$service->threadContext($viewer,$changed)['status']);
        $this->assertSame('unchanged',$service->threadContext($viewer,$unchanged)['status']);
        $this->assertNull($service->threadContext($viewer,$legacy));
    }

    private function context(): array
    {
        $org=Organization::create(['name'=>'Plant','slug'=>'plant']);
        $viewer=$this->user($org,'viewer@test');$full=$this->user($org,'full@test');
        $admin=User::factory()->create(['organization_id'=>$org->id,'platform_role'=>'platform_admin','role'=>'admin','status'=>'active']);
        $author=$this->user($org,'author@test');
        $device=Device::create(['organization_id'=>$org->id,'name'=>'Pump','type'=>'sensor','serial_number'=>'P-38','protocol'=>'mqtt']);
        DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$viewer->id,'access_level'=>'viewer']);
        DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$full->id,'access_level'=>'full_access']);
        $from=$this->revision($device,1,$this->snapshot('Pump'),$author);
        $toSnapshot=$this->snapshot('Main Pump');
        $toSnapshot['dashboard']['widgets'][0]['layout']=['x'=>4,'y'=>0,'w'=>6,'h'=>4];
        $toSnapshot['dashboard']['widgets'][0]['configuration']['timeRange']='7d';
        $toSnapshot['dashboard']['widgets'][]=['id'=>20,'type'=>'gauge','title'=>'Pressure','layout'=>['x'=>0,'y'=>3,'w'=>3,'h'=>3],'configuration'=>['metric'=>'pressure']];
        $toSnapshot['parameters'][0]['unit']='F';
        $toSnapshot['parameters'][]=['id'=>200,'name'=>'Pressure limit','key'=>'pressure_limit','dataType'=>'number','unit'=>'psi','description'=>null];
        $toSnapshot['metadata']['secret']='do-not-render';$toSnapshot['telemetry']=['temperature'=>99];$toSnapshot['last_seen']='volatile';
        $to=$this->revision($device,2,$toSnapshot,$author,$from);
        foreach([$viewer,$full] as $user) \App\Models\ResourceRevisionState::create(['resource_type'=>'device','resource_id'=>$device->id,'user_id'=>$user->id,'accepted_revision_id'=>$from->id,'seen_latest_revision_id'=>$from->id,'last_reviewed_revision_id'=>$from->id]);
        return[$device,$viewer,$full,$admin,$from,$to,$org];
    }
    private function snapshot(string $name):array{return ['metadata'=>['name'=>$name,'type'=>'sensor','protocol'=>'mqtt','location'=>null,'templateId'=>null],'dashboard'=>['id'=>1,'name'=>'Operations','description'=>null,'widgets'=>[['id'=>10,'type'=>'line','title'=>'Temperature','layout'=>['x'=>0,'y'=>0,'w'=>4,'h'=>3],'configuration'=>['metric'=>'temperature','timeRange'=>'24h']]]],'parameters'=>[['id'=>100,'name'=>'Temperature','key'=>'temperature','dataType'=>'number','unit'=>'C','description'=>'Ambient']]];}
    private function revision(Device $device,int $number,array $snapshot,?User $author=null,?ResourceRevision $parent=null):ResourceRevision{return ResourceRevision::create(['resource_type'=>'device','resource_id'=>$device->id,'revision_number'=>$number,'parent_revision_id'=>$parent?->id,'created_by'=>$author?->id,'change_summary'=>"Revision $number",'snapshot_schema_version'=>1,'snapshot'=>$snapshot,'changed_sections'=>['metadata','dashboard','parameters'],'checksum'=>hash('sha256',json_encode($snapshot)),'created_at'=>now()]);}
    private function compareUrl(Device $device,ResourceRevision $from,ResourceRevision $to):string{return "/api/collaboration/resources/device/$device->id/revision-comparison?from_revision_id=$from->id&to_revision_id=$to->id";}
    private function user(Organization $org,string $email):User{return User::factory()->create(['organization_id'=>$org->id,'email'=>$email,'role'=>'staff','status'=>'active']);}
}
