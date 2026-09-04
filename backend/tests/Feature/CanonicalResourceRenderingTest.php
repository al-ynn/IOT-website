<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalResourceRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_full_access_and_admin_dtos_identify_the_same_device(): void
    {
        $org=Organization::create(['name'=>'Canonical','slug'=>'canonical-render']);
        $viewer=User::factory()->create(['organization_id'=>$org->id,'role'=>'staff','status'=>'active']);
        $full=User::factory()->create(['organization_id'=>$org->id,'role'=>'staff','status'=>'active']);
        $admin=User::factory()->create(['organization_id'=>$org->id,'role'=>'staff','platform_role'=>'platform_admin','status'=>'active']);
        $device=Device::create(['organization_id'=>$org->id,'name'=>'Shared Pump','external_id'=>'shared-pump','type'=>'sensor','protocol'=>'mqtt','status'=>'online']);
        DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$viewer->id,'access_level'=>'viewer']);
        DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$full->id,'access_level'=>'full_access']);
        $viewerDto=$this->actingAs($viewer)->getJson("/api/devices/{$device->id}")->assertOk()->assertJsonPath('canonicalId',(string)$device->id)->assertJsonPath('capabilities.canEdit',false)->json();
        $fullDto=$this->actingAs($full)->getJson("/api/devices/{$device->id}")->assertOk()->assertJsonPath('canonicalId',(string)$device->id)->assertJsonPath('capabilities.canEdit',true)->json();
        $adminDto=$this->actingAs($admin)->getJson("/api/admin/devices/{$device->id}")->assertOk()->assertJsonPath('data.device.canonicalId',(string)$device->id)->assertJsonPath('data.device.capabilities.canManageAccess',true)->json('data.device');
        foreach(['id','name','type','protocol','status'] as $field){$this->assertSame($viewerDto[$field],$fullDto[$field]);$this->assertSame($viewerDto[$field],$adminDto[$field]);}
    }

    public function test_comment_notification_routes_into_canonical_workspace_tab(): void
    {
        $org=Organization::create(['name'=>'Links','slug'=>'canonical-links']);$user=User::factory()->create(['organization_id'=>$org->id,'role'=>'staff','status'=>'active']);
        $device=Device::create(['organization_id'=>$org->id,'name'=>'Comment Device','external_id'=>'comment-device','type'=>'sensor','protocol'=>'mqtt']);DeviceAccessAssignment::create(['device_id'=>$device->id,'user_id'=>$user->id,'access_level'=>'viewer']);
        $notice=\App\Models\Notification::create(['organization_id'=>$org->id,'user_id'=>$user->id,'type'=>'comment.mentioned','schema_version'=>1,'category'=>'mentions','resource_type'=>'device','resource_id'=>$device->id,'data'=>['thread_id'=>18],'title'=>'Mention','message'=>'Mentioned','severity'=>'info']);
        $this->actingAs($user)->getJson('/api/notifications')->assertOk()->assertJsonPath('data.0.id',(string)$notice->id)->assertJsonPath('data.0.deepLink',"/app/devices/{$device->id}?tab=comments&thread=18");
    }
}
