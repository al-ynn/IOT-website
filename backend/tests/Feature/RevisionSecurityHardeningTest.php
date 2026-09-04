<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\Organization;
use App\Models\Report;
use App\Models\ResourceRevision;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RevisionSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function revision(string $type,int $id,array $snapshot,int $number=1):ResourceRevision
    {
        return ResourceRevision::create(['resource_type'=>$type,'resource_id'=>$id,'revision_number'=>$number,'change_summary'=>'Safe revision','snapshot_schema_version'=>1,'snapshot'=>$snapshot,'changed_sections'=>['metadata'],'checksum'=>hash('sha256',json_encode($snapshot)),'created_at'=>now()]);
    }

    public function test_raw_revision_ids_are_bound_after_current_resource_authorization():void
    {
        $org=Organization::create(['name'=>'Revision Security','slug'=>'revision-security']);
        $viewer=User::factory()->create(['organization_id'=>$org->id,'role'=>'staff','status'=>'active']);
        $a=Device::create(['organization_id'=>$org->id,'name'=>'A','external_id'=>'revision-a','type'=>'sensor','protocol'=>'https']);
        $b=Device::create(['organization_id'=>$org->id,'name'=>'B','external_id'=>'revision-b','type'=>'sensor','protocol'=>'https']);
        DeviceAccessAssignment::create(['user_id'=>$viewer->id,'device_id'=>$a->id,'access_level'=>'viewer']);
        $a1=$this->revision('device',$a->id,['metadata'=>['name'=>'A'],'dashboard'=>null,'parameters'=>[]]);
        $b1=$this->revision('device',$b->id,['metadata'=>['name'=>'B'],'dashboard'=>null,'parameters'=>[]]);

        $base="/api/collaboration/resources/device/{$a->id}";
        $this->actingAs($viewer)->getJson("{$base}/revisions/{$b1->id}")->assertNotFound();
        $this->actingAs($viewer)->getJson("{$base}/revisions/{$b1->id}/compare-previous")->assertNotFound();
        $this->actingAs($viewer)->getJson("{$base}/revision-comparison?from_revision_id={$a1->id}&to_revision_id={$b1->id}")->assertNotFound();
        DeviceAccessAssignment::where(['user_id'=>$viewer->id,'device_id'=>$a->id])->delete();
        $this->actingAs($viewer)->getJson("{$base}/revisions/{$a1->id}")->assertNotFound();
    }

    public function test_revision_detail_redacts_secrets_and_protected_source_identifiers():void
    {
        $org=Organization::create(['name'=>'Redaction','slug'=>'revision-redaction']);
        $admin=User::factory()->create(['organization_id'=>$org->id,'role'=>'staff','platform_role'=>'platform_admin','status'=>'active']);
        $device=Device::create(['organization_id'=>$org->id,'name'=>'D','external_id'=>'revision-d','type'=>'sensor','protocol'=>'https']);
        $revision=$this->revision('device',$device->id,['metadata'=>['name'=>'D','authorization'=>'Bearer secret'],'dashboard'=>['widgets'=>[['id'=>'w','configuration'=>['deviceId'=>'999','telemetryKey'=>'pressure','accessToken'=>'secret','title'=>'Safe']]]],'parameters'=>[]]);
        $response=$this->actingAs($admin)->getJson("/api/collaboration/resources/device/{$device->id}/revisions/{$revision->id}")->assertOk();
        $encoded=json_encode($response->json('snapshot'));
        foreach(['Bearer secret','999','pressure','accessToken','authorization'] as $forbidden)$this->assertStringNotContainsString($forbidden,$encoded);
        $this->assertStringContainsString('Safe',$encoded);
    }

    public function test_webhook_and_report_history_use_domain_safe_snapshot_shapes():void
    {
        $org=Organization::create(['name'=>'Domain Redaction','slug'=>'domain-redaction']);
        $admin=User::factory()->create(['organization_id'=>$org->id,'role'=>'staff','platform_role'=>'platform_admin','status'=>'active']);
        $webhook=Webhook::create(['organization_id'=>$org->id,'name'=>'Hook','url'=>'https://user:pass@example.test/private','enabled'=>false,'event_types'=>[],'signing_secret'=>'secret','secret_prefix'=>'abcd','created_by'=>$admin->id]);
        $webhookRevision=$this->revision('webhook',$webhook->id,['configuration'=>['name'=>'Hook','url'=>$webhook->url,'event_types'=>[],'signingSecret'=>'secret']]);
        $this->actingAs($admin)->getJson("/api/collaboration/resources/webhook/{$webhook->id}/revisions/{$webhookRevision->id}")->assertOk()->assertJsonPath('snapshot.configuration.urlConfigured',true)->assertJsonMissing(['url'=>$webhook->url])->assertJsonMissing(['signingSecret'=>'secret']);

        $report=Report::create(['organization_id'=>$org->id,'name'=>'Report','report_type'=>'device_telemetry','configuration'=>[],'created_by'=>$admin->id]);
        $reportRevision=$this->revision('report',$report->id,['metadata'=>['name'=>'Report'],'configuration'=>['device_ids'=>[10,11],'metric_keys'=>['temperature'],'apiKey'=>'secret']]);
        $this->actingAs($admin)->getJson("/api/collaboration/resources/report/{$report->id}/revisions/{$reportRevision->id}")->assertOk()->assertJsonPath('snapshot.configuration.deviceCount',2)->assertJsonPath('snapshot.configuration.metricCount',1)->assertJsonMissing(['device_ids'=>[10,11]])->assertJsonMissing(['apiKey'=>'secret']);
    }
}