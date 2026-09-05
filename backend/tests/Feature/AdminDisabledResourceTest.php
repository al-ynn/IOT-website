<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ResourceAttentionState;
use App\Models\ResourceRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminDisabledResourceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organization::create(['name' => 'Disabled Center Org', 'slug' => 'disabled-center-'.uniqid()]);
        $staff = User::factory()->create(['organization_id'=>$org->id,'email'=>'staff'.uniqid().'@disabled.test','role'=>'owner','platform_role'=>null,'status'=>'active']);
        $admin = User::factory()->create(['organization_id'=>$org->id,'email'=>'admin'.uniqid().'@disabled.test','role'=>'owner','platform_role'=>'platform_admin','status'=>'active']);
        $device = $this->actingAs($staff)->postJson('/api/devices',['name'=>'Disabled Pump','type'=>'sensor','serialNumber'=>'DC-'.uniqid(),'protocol'=>'mqtt'])->assertCreated()->json('id');
        $template = $this->actingAs($staff)->postJson('/api/device-templates',['name'=>'Disabled Template'])->assertCreated()->json('data.id');
        return compact('org','staff','admin','device','template');
    }

    public function test_admin_gets_canonical_disabled_rows_counts_and_filters(): void
    {
        extract($this->fixture());
        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$device}/lifecycle/disable")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/resources/device_template/{$template}/lifecycle/disable")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$device}/attention",['reason_code'=>'configuration_follow_up','note'=>'Confirm configuration.'])->assertCreated();
        $this->actingAs($admin)->getJson('/api/admin/disabled-resources')->assertOk()->assertJsonPath('total',2)->assertJsonCount(2,'data')->assertJsonFragment(['resourceLabel'=>'Disabled Pump','lifecycle'=>'disabled','needsAttention'=>true])->assertJsonMissing(['secret']);
        $this->actingAs($admin)->getJson('/api/admin/disabled-resources/summary')->assertOk()->assertJsonPath('data.totalDisabled',2)->assertJsonPath('data.disabledByType.device',1)->assertJsonPath('data.disabledByType.device_template',1)->assertJsonPath('data.totalArchived',0);
        $this->actingAs($admin)->getJson('/api/admin/disabled-resources?resource_type=device&needs_attention=1&search=Pump')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.resourceId',(string)$device);
        $this->actingAs($admin)->getJson('/api/admin/disabled-resources?resource_type=blueprint')->assertUnprocessable();
    }

    public function test_staff_is_denied_and_active_and_archived_are_distinct(): void
    {
        extract($this->fixture());
        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$device}/lifecycle/disable")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/resources/device_template/{$template}/lifecycle/archive")->assertOk();
        $this->actingAs($staff)->getJson('/api/admin/disabled-resources')->assertForbidden();
        $this->actingAs($staff)->getJson('/api/admin/disabled-resources/summary')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/admin/disabled-resources')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.resourceType','device');
        $this->actingAs($admin)->getJson('/api/admin/disabled-resources?state=archived')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.resourceType','device_template')->assertJsonPath('data.0.lifecycle','archived');
    }

    public function test_restore_from_center_uses_lifecycle_service_without_side_effects(): void
    {
        extract($this->fixture());
        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$device}/attention",['reason_code'=>'other','note'=>'Follow up after restore.'])->assertCreated();
        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$device}/lifecycle/disable")->assertOk();
        $revisionCount = ResourceRevision::count();
        $this->actingAs($admin)->postJson("/api/admin/resources/device/{$device}/lifecycle/restore")->assertOk()->assertJsonPath('data.state','active');
        $this->actingAs($admin)->getJson('/api/admin/disabled-resources')->assertOk()->assertJsonPath('total',0);
        $this->assertDatabaseHas('device_access_assignments',['device_id'=>$device,'user_id'=>$staff->id,'access_level'=>'full_access']);
        $this->assertDatabaseHas('resource_attention_states',['resource_type'=>'device','resource_id'=>$device,'status'=>'open']);
        $this->assertSame($revisionCount, ResourceRevision::count());
        $this->assertSame(1, ResourceAttentionState::where(['resource_type'=>'device','resource_id'=>$device])->count());
    }
}
