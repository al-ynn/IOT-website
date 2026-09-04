<?php

namespace Tests\Feature;

use App\Jobs\NotifyAdminsOfDeviceCreated;
use App\Models\Device;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;
    private function user(Organization $org, bool $admin=false): User { return User::factory()->create(['organization_id'=>$org->id,'role'=>'staff','platform_role'=>$admin?'platform_admin':null,'status'=>'active']); }
    private function notice(User $user, array $extra=[]): Notification { return Notification::create([...['organization_id'=>$user->organization_id,'user_id'=>$user->id,'type'=>'legacy.notification','category'=>'updates','title'=>'Safe title','message'=>'Safe body','severity'=>'info'],...$extra]); }

    public function test_user_lists_only_owned_notifications_newest_first_and_count_is_server_authoritative(): void
    {
        $org=Organization::create(['name'=>'Org','slug'=>'org']);$one=$this->user($org);$two=$this->user($org);
        $old=$this->notice($one);$old->forceFill(['created_at'=>now()->subHour()])->save();$new=$this->notice($one,['title'=>'Newest']);$this->notice($two,['title'=>'Foreign']);
        $this->actingAs($one)->getJson('/api/notifications')->assertOk()->assertJsonCount(2,'data')->assertJsonPath('data.0.id',(string)$new->id);
        $this->actingAs($one)->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('count',2);
    }

    public function test_mark_one_and_dismiss_enforce_recipient_ownership(): void
    {
        $org=Organization::create(['name'=>'Org','slug'=>'org']);$one=$this->user($org);$two=$this->user($org);$mine=$this->notice($one);$foreign=$this->notice($two);
        $this->actingAs($one)->postJson("/api/notifications/{$mine->id}/read")->assertOk()->assertJsonPath('data.isRead',true);
        $this->actingAs($one)->postJson("/api/notifications/{$foreign->id}/read")->assertNotFound();
        $this->actingAs($one)->postJson("/api/notifications/{$mine->id}/dismiss")->assertNoContent();
        $this->assertNotNull($mine->refresh()->dismissed_at);
    }

    public function test_mark_all_changes_only_authenticated_users_notifications(): void
    {
        $org=Organization::create(['name'=>'Org','slug'=>'org']);$one=$this->user($org);$two=$this->user($org);$this->notice($one);$foreign=$this->notice($two);
        $this->actingAs($one)->postJson('/api/notifications/read-all')->assertOk()->assertJsonPath('updated',1);
        $this->assertNotNull(Notification::where('user_id',$one->id)->first()->read_at);$this->assertNull($foreign->refresh()->read_at);
    }

    public function test_device_created_payload_is_structured_safe_retry_idempotent_and_does_not_change_device(): void
    {
        $org=Organization::create(['name'=>'<script>Org</script>','slug'=>'org']);$staff=$this->user($org);$admin=$this->user($org,true);
        $device=Device::create(['organization_id'=>$org->id,'created_by'=>$staff->id,'name'=>'<script>alert(1)</script>','external_id'=>'X','type'=>'sensor','protocol'=>'mqtt']);
        $job=new NotifyAdminsOfDeviceCreated($device->id);$job->handle();$job->handle();
        $this->assertDatabaseCount('notifications',1);
        $response=$this->actingAs($admin)->getJson('/api/notifications')->assertOk()->assertJsonPath('data.0.eventType','device.created')->assertJsonPath('data.0.category','updates')->assertJsonPath('data.0.actor.id',(string)$staff->id)->assertJsonPath('data.0.resource.id',(string)$device->id)->assertJsonPath('data.0.deepLink',"/admin/devices/{$device->id}")->assertJsonPath('data.0.requiresAction',false);
        $encoded=json_encode($response->json('data.0'));foreach(['credential','authorization','access_token','webhook_secret','provisioning_secret'] as $secret)$this->assertStringNotContainsString($secret,strtolower($encoded));
        $this->actingAs($admin)->postJson('/api/notifications/read-all')->assertOk();$this->assertSame('offline',$device->refresh()->status);
    }

    public function test_notification_possession_does_not_grant_resource_access_and_unknown_event_has_no_action(): void
    {
        $a=Organization::create(['name'=>'A','slug'=>'a']);$b=Organization::create(['name'=>'B','slug'=>'b']);$staff=$this->user($b);$device=Device::create(['organization_id'=>$a->id,'name'=>'A device','external_id'=>'A1','type'=>'sensor','protocol'=>'mqtt']);
        $notice=$this->notice($staff,['type'=>'evil.class','resource_type'=>'device','resource_id'=>$device->id,'action_url'=>'javascript:alert(1)']);
        $this->actingAs($staff)->getJson('/api/notifications')->assertOk()->assertJsonPath('data.0.eventType','unknown')->assertJsonPath('data.0.deepLink',null)->assertJsonCount(0,'data.0.actions');
        $this->actingAs($staff)->getJson("/api/devices/{$device->id}")->assertNotFound();
    }
}
