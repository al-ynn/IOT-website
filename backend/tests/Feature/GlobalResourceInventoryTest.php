<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Dashboard;
use App\Models\Device;
use App\Models\DeviceParameter;
use App\Models\DeviceTemplate;
use App\Models\FirmwareArtifact;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Report;
use App\Models\User;
use App\Models\Webhook;
use App\Services\ResourceLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GlobalResourceInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_inventory_normalizes_only_safe_top_level_resources(): void
    {
        [$organization, $admin, $creator] = $this->actors('matrix');
        $device = Device::create(['organization_id'=>$organization->id,'created_by'=>$creator->id,'name'=>'Pump','external_id'=>'INV-1','type'=>'sensor','protocol'=>'mqtt']);
        DeviceTemplate::create(['organization_id'=>$organization->id,'created_by'=>$creator->id,'name'=>'Pump Template']);
        Automation::create(['organization_id'=>$organization->id,'name'=>'Alarm Rule','created_by'=>$creator->id,'updated_by'=>$creator->id]);
        Report::create(['organization_id'=>$organization->id,'name'=>'Fleet Report','report_type'=>'device_summary','configuration'=>[],'created_by'=>$creator->id]);
        Webhook::create(['organization_id'=>$organization->id,'name'=>'Ops Hook','url'=>'https://example.test/hook','event_types'=>['automation.failed'],'signing_secret'=>'never-return-this-secret','secret_prefix'=>'never','created_by'=>$creator->id]);
        Location::create(['organization_id'=>$organization->id,'name'=>'Plant A','normalized_name'=>'plant a','created_by'=>$creator->id]);
        FirmwareArtifact::create(['organization_id'=>$organization->id,'uploaded_by'=>$creator->id,'name'=>'Pump Firmware','version'=>'1.0.0','storage_disk'=>'local','storage_path'=>'firmware/a.bin','original_filename'=>'a.bin','mime_type'=>'application/octet-stream','size_bytes'=>10,'sha256'=>str_repeat('a',64)]);
        Dashboard::create(['organization_id'=>$organization->id,'owner_user_id'=>$creator->id,'name'=>'Private Dashboard','scope_type'=>'personal','created_by'=>$creator->id,'updated_by'=>$creator->id]);
        Dashboard::create(['organization_id'=>$organization->id,'device_id'=>$device->id,'name'=>'Device Dashboard','scope_type'=>'device','created_by'=>$creator->id,'updated_by'=>$creator->id]);
        DeviceParameter::create(['device_id'=>$device->id,'name'=>'Pressure','key'=>'pressure','data_type'=>'number']);

        $response = $this->actingAs($admin)->getJson('/api/admin/resources/inventory?per_page=100')->assertOk();
        $response->assertJsonCount(7, 'data');
        $this->assertSame(['automation','device','device_template','firmware','location','report','webhook'], collect($response->json('data'))->pluck('resourceType')->sort()->values()->all());
        $payload = $response->getContent();
        $this->assertStringNotContainsString('Private Dashboard', $payload);
        $this->assertStringNotContainsString('Device Dashboard', $payload);
        $this->assertStringNotContainsString('Pressure', $payload);
        $this->assertStringNotContainsString('never-return-this-secret', $payload);
        $response->assertJsonPath('data.1.capabilities.canOpen', true);
        $this->actingAs($admin)->getJson('/api/admin/resources/inventory/webhook/'.Webhook::firstOrFail()->id)
            ->assertOk()->assertJsonMissingPath('data.signing_secret')->assertJsonPath('data.resourceType', 'webhook');
    }

    public function test_filters_and_pagination_are_server_side_and_lifecycle_is_canonical(): void
    {
        [$first, $admin, $creator] = $this->actors('first');
        [$second] = $this->actors('second');
        $disabled = Device::create(['organization_id'=>$first->id,'created_by'=>$creator->id,'name'=>'Disabled Pump','external_id'=>'INV-D','type'=>'sensor','protocol'=>'mqtt','status'=>'online']);
        Device::create(['organization_id'=>$first->id,'created_by'=>$creator->id,'name'=>'Active Pump','external_id'=>'INV-A','type'=>'sensor','protocol'=>'mqtt','status'=>'offline']);
        Device::create(['organization_id'=>$second->id,'name'=>'Foreign Pump','external_id'=>'INV-F','type'=>'sensor','protocol'=>'mqtt','status'=>'offline']);
        app(ResourceLifecycleService::class)->disable($admin, 'device', $disabled->id);

        $this->actingAs($admin)->getJson('/api/admin/resources/inventory?resource_type=device&organization_id='.$first->id.'&lifecycle=disabled&q=Disabled&per_page=25')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.label', 'Disabled Pump')->assertJsonPath('data.0.lifecycle', 'disabled');
        $this->actingAs($admin)->getJson('/api/admin/resources/inventory?resource_type=device&lifecycle=active&per_page=25')
            ->assertOk()->assertJsonPath('per_page', 25)->assertJsonPath('data.0.lifecycle', 'active');
    }

    public function test_staff_unknown_types_and_query_injection_fail_closed(): void
    {
        [$organization, $admin, $staff] = $this->actors('security');
        Device::create(['organization_id'=>$organization->id,'created_by'=>$staff->id,'name'=>'Safe Device','external_id'=>'INV-S','type'=>'sensor','protocol'=>'mqtt']);

        $this->actingAs($staff)->getJson('/api/admin/resources/inventory')->assertForbidden();
        $this->actingAs($staff)->getJson('/api/admin/resources/inventory/device/1')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/admin/resources/inventory?resource_type=App%5CModels%5CUser')->assertUnprocessable();
        $this->actingAs($admin)->getJson('/api/admin/resources/inventory?sort=created_at%20desc')->assertUnprocessable();
        $this->actingAs($admin)->getJson('/api/admin/resources/inventory?q='.str_repeat('x',101))->assertUnprocessable();
    }

    public function test_metadata_is_admin_only_and_contains_no_fake_domains(): void
    {
        [, $admin, $staff] = $this->actors('metadata');
        $response = $this->actingAs($admin)->getJson('/api/admin/resources/inventory/metadata')->assertOk();
        $keys = collect($response->json('data.resourceTypes'))->pluck('key')->all();
        $this->assertSame(['device','device_template','automation','report','webhook','location','firmware'], $keys);
        $this->assertNotContains('dashboard', $keys);
        $this->assertNotContains('blueprint', $keys);
        $this->assertNotContains('integration', $keys);
        $this->actingAs($staff)->getJson('/api/admin/resources/inventory/metadata')->assertForbidden();
    }

    private function actors(string $suffix): array
    {
        $organization = Organization::create(['name'=>'Inventory '.$suffix,'slug'=>'inventory-'.$suffix]);
        $admin = User::factory()->create(['organization_id'=>$organization->id,'role'=>'staff','platform_role'=>'platform_admin','status'=>'active']);
        $staff = User::factory()->create(['organization_id'=>$organization->id,'role'=>'owner','status'=>'active']);

        return [$organization, $admin, $staff];
    }
}
