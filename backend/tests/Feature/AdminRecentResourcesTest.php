<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Location;
use App\Models\Organization;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminRecentResourcesTest extends TestCase
{
    use RefreshDatabase;

    private function user(Organization $org, bool $admin = false): User
    {
        return User::factory()->create(['organization_id' => $org->id, 'role' => 'owner', 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']);
    }

    public function test_created_is_admin_current_organization_cross_domain_and_staff_is_denied(): void
    {
        $a=Organization::create(['name'=>'A','slug'=>'recent-a']);$b=Organization::create(['name'=>'B','slug'=>'recent-b']);$staffA=$this->user($a);$staffB=$this->user($b);$admin=$this->user($a,true);
        $device=$this->actingAs($staffA)->postJson('/api/devices',['name'=>'Pump','type'=>'sensor','serialNumber'=>'R-1','protocol'=>'mqtt'])->assertCreated()->json('id');
        $template=$this->actingAs($staffB)->postJson('/api/device-templates',['name'=>'Boiler Template'])->assertCreated()->json('data.id');
        $this->actingAs($admin)->getJson('/api/admin/recently-created')->assertOk()->assertJsonCount(1,'data')->assertJsonMissing(['resourceType'=>'device_template'])->assertJsonFragment(['resourceType'=>'device','resourceId'=>(string)$device]);
        $this->actingAs($admin)->getJson('/api/admin/recently-created?resource_type=device&search=Pump')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.resourceLink',"/admin/resources/device/{$device}");
        $this->actingAs($staffA)->getJson('/api/admin/recently-created')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/admin/recently-created?resource_type=App%5CModels%5CDevice')->assertUnprocessable();
        $this->assertNotNull($template);
    }

    public function test_updated_uses_latest_non_baseline_revision_and_changed_sections(): void
    {
        $org=Organization::create(['name'=>'Org','slug'=>'recent-updated']);$staff=$this->user($org);$admin=$this->user($org,true);
        $device=$this->actingAs($staff)->postJson('/api/devices',['name'=>'Pump','type'=>'sensor','serialNumber'=>'R-2','protocol'=>'mqtt'])->assertCreated()->json('id');
        $template=$this->actingAs($staff)->postJson('/api/device-templates',['name'=>'Template'])->assertCreated()->json('data.id');
        $this->actingAs($staff)->patchJson("/api/devices/{$device}",['name'=>'Pump Updated','location_id'=>Location::create(['organization_id'=>$org->id,'name'=>'Bay 2','normalized_name'=>'bay 2'])->id])->assertOk();
        $this->actingAs($staff)->patchJson("/api/device-templates/{$template}",['description'=>'Meaningful'])->assertOk();
        $this->actingAs($admin)->getJson('/api/admin/recently-updated')->assertOk()->assertJsonCount(2,'data')->assertJsonPath('data.0.revision.number',2)->assertJsonStructure(['data'=>[['revision'=>['summary','changedSections'],'actor'=>['id','name']]]]);
        $this->actingAs($admin)->getJson('/api/admin/recently-updated?resource_type=device_template')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.resourceId',(string)$template);
    }

    public function test_runtime_and_governance_noise_do_not_change_meaningful_update_order(): void
    {
        $org=Organization::create(['name'=>'Org','slug'=>'recent-noise']);$staff=$this->user($org);$admin=$this->user($org,true);
        $device=$this->actingAs($staff)->postJson('/api/devices',['name'=>'Pump','type'=>'sensor','serialNumber'=>'R-3','protocol'=>'mqtt'])->assertCreated()->json('id');
        $this->actingAs($staff)->patchJson("/api/devices/{$device}",['name'=>'Pump Updated'])->assertOk();
        $revision=ResourceRevision::where(['resource_type'=>'device','resource_id'=>$device])->latest('id')->firstOrFail();
        Device::whereKey($device)->update(['last_seen'=>now()->addDay(),'status'=>'offline']);
        $this->actingAs($admin)->getJson('/api/admin/recently-updated')->assertOk()->assertJsonPath('data.0.revision.id',(string)$revision->id)->assertJsonPath('data.0.occurredAt',$revision->created_at->toISOString());
        $this->actingAs($admin)->getJson('/api/admin/recently-updated?resource_type=blueprint')->assertUnprocessable();
    }

    public function test_baseline_only_resources_are_not_falsely_updated_and_role_loss_applies(): void
    {
        $org=Organization::create(['name'=>'Org','slug'=>'recent-baseline']);$staff=$this->user($org);$admin=$this->user($org,true);
        $this->actingAs($staff)->postJson('/api/devices',['name'=>'Baseline','type'=>'sensor','serialNumber'=>'R-4','protocol'=>'mqtt'])->assertCreated();
        $this->actingAs($admin)->getJson('/api/admin/recently-updated')->assertOk()->assertJsonCount(0,'data');
        $admin->update(['platform_role'=>null]);
        $this->actingAs($admin->fresh())->getJson('/api/admin/recently-updated')->assertForbidden();
    }
}
