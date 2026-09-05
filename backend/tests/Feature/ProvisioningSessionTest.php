<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\DeviceTemplate;
use App\Models\OperationalEvent;
use App\Models\Organization;
use App\Models\ProvisioningSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProvisioningSessionTest extends TestCase
{
    use RefreshDatabase;

    private function organization(string $slug): Organization { return Organization::create(['name' => ucfirst($slug), 'slug' => $slug]); }
    private function user(Organization $org, string $role = 'owner', bool $admin = false): User { return User::factory()->create(['organization_id' => $org->id, 'role' => $role, 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']); }
    private function createSession(User $user, array $payload = []): array { return $this->actingAs($user)->postJson('/api/provisioning-sessions', ['name' => 'Pump onboarding', ...$payload])->assertCreated()->json('data'); }
    private function devicePayload(string $identifier = 'pump-001'): array { return ['name' => 'Pump 01', 'serialNumber' => $identifier, 'type' => 'pump', 'protocol' => 'mqtt', 'macAddress' => '00:11:22:33:44:55']; }

    public function test_management_is_authenticated_permission_gated_scoped_and_server_authoritative(): void
    {
        $a=$this->organization('provision-a');$b=$this->organization('provision-b');$owner=$this->user($a);$staff=$this->user($a,'staff');$foreign=$this->user($b);
        $this->getJson('/api/provisioning-sessions')->assertUnauthorized();
        $this->actingAs($staff)->getJson('/api/provisioning-sessions')->assertOk();
        $this->actingAs($staff)->postJson('/api/provisioning-sessions',['name'=>'Blocked'])->assertForbidden();
        $data=$this->createSession($owner,['organization_id'=>$b->id,'initiated_by'=>$foreign->id]);
        $this->assertSame((string)$a->id,$data['organization']['id']);$this->assertSame((string)$owner->id,$data['initiatedBy']['id']);$this->assertSame('pending',$data['status']);
        $this->assertDatabaseCount('devices',0);$this->assertDatabaseCount('device_access_assignments',0);
        $this->actingAs($foreign)->getJson('/api/provisioning-sessions/'.$data['id'])->assertNotFound();
        $this->actingAs($foreign)->postJson('/api/provisioning-sessions/'.$data['id'].'/cancel')->assertNotFound();
        $this->assertArrayNotHasKey('token',$data);$this->assertArrayNotHasKey('code',$data);$this->assertArrayNotHasKey('provisioningCode',$data);
        $this->postJson('/api/device-provisioning/register')->assertNotFound();
    }

    public function test_completion_creates_one_device_applies_template_parameters_and_assigns_staff_atomically(): void
    {
        $org=$this->organization('provision-complete');$owner=$this->user($org);$template=$org->deviceTemplates()->create(['name'=>'Pump schema','device_type'=>'pump','protocol'=>'mqtt','created_by'=>$owner->id]);$template->parameters()->create(['name'=>'Pressure','key'=>'pressure','data_type'=>'number','unit'=>'bar']);
        $session=$this->createSession($owner,['device_template_id'=>$template->id]);
        $response=$this->actingAs($owner)->postJson('/api/provisioning-sessions/'.$session['id'].'/complete',$this->devicePayload())->assertOk()->assertJsonPath('data.status','completed');
        $deviceId=$response->json('data.device.id');
        $this->assertDatabaseHas('devices',['id'=>$deviceId,'organization_id'=>$org->id,'device_template_id'=>$template->id,'external_id'=>'pump-001']);
        $this->assertDatabaseHas('device_parameters',['device_id'=>$deviceId,'key'=>'pressure']);
        $this->assertDatabaseHas('device_access_assignments',['device_id'=>$deviceId,'user_id'=>$owner->id,'access_level'=>'full_access']);
        $this->actingAs($owner)->postJson('/api/provisioning-sessions/'.$session['id'].'/complete',$this->devicePayload('pump-002'))->assertUnprocessable();
        $this->assertSame(1,Device::count());
    }

    public function test_cancel_fail_and_terminal_transition_rules_are_enforced_with_safe_events(): void
    {
        $org=$this->organization('provision-states');$owner=$this->user($org);$cancelled=$this->createSession($owner);$this->actingAs($owner)->postJson('/api/provisioning-sessions/'.$cancelled['id'].'/cancel')->assertOk()->assertJsonPath('data.status','cancelled');$this->actingAs($owner)->postJson('/api/provisioning-sessions/'.$cancelled['id'].'/complete',$this->devicePayload())->assertUnprocessable();
        $failed=$this->createSession($owner);$message='token=abc C:\\private\\vendor\\trace.php';$this->actingAs($owner)->postJson('/api/provisioning-sessions/'.$failed['id'].'/fail',['failure_code'=>'registration_failed','failure_message'=>$message])->assertOk()->assertJsonPath('data.status','failed');
        $event=OperationalEvent::where('provisioning_session_id',$failed['id'])->firstOrFail();$this->assertSame('provisioning_session_failed',$event->event_type);$this->assertStringNotContainsString('abc',$event->message);$this->assertStringNotContainsString('vendor',$event->message);$this->assertStringNotContainsString('abc',json_encode($event->context));
        $this->actingAs($owner)->postJson('/api/provisioning-sessions/'.$failed['id'].'/cancel')->assertUnprocessable();$this->assertDatabaseCount('devices',0);
    }

    public function test_expiry_is_server_side_scheduled_and_prevents_completion(): void
    {
        Carbon::setTestNow('2026-08-21 10:00:00');$org=$this->organization('provision-expiry');$owner=$this->user($org);$session=$this->createSession($owner);Carbon::setTestNow('2026-08-21 10:31:00');$this->artisan('provisioning:expire')->assertSuccessful();
        $this->assertDatabaseHas('provisioning_sessions',['id'=>$session['id'],'status'=>'expired']);$this->assertDatabaseHas('operational_events',['provisioning_session_id'=>$session['id'],'event_type'=>'provisioning_session_expired']);$this->actingAs($owner)->postJson('/api/provisioning-sessions/'.$session['id'].'/complete',$this->devicePayload())->assertUnprocessable();$this->assertDatabaseCount('devices',0);Carbon::setTestNow();
    }

    public function test_cross_org_templates_duplicate_identifiers_and_admin_global_boundary_are_safe(): void
    {
        $a=$this->organization('provision-security-a');$b=$this->organization('provision-security-b');$owner=$this->user($a);$other=$this->user($b);$admin=$this->user($a,'staff',true);$foreignTemplate=$b->deviceTemplates()->create(['name'=>'Foreign','created_by'=>$other->id]);
        $this->actingAs($owner)->postJson('/api/provisioning-sessions',['device_template_id'=>$foreignTemplate->id])->assertNotFound();
        $a->devices()->create(['name'=>'Existing','external_id'=>'duplicate','type'=>'sensor','protocol'=>'mqtt']);$session=$this->createSession($owner);$this->actingAs($owner)->postJson('/api/provisioning-sessions/'.$session['id'].'/complete',$this->devicePayload('duplicate'))->assertUnprocessable();$this->assertDatabaseHas('provisioning_sessions',['id'=>$session['id'],'status'=>'pending']);
        $foreign=$this->createSession($other);$this->actingAs($owner)->getJson('/api/admin/provisioning-sessions')->assertForbidden();$this->actingAs($admin)->getJson('/api/admin/provisioning-sessions')->assertOk()->assertJsonPath('meta.total',1);$this->actingAs($admin)->getJson('/api/admin/provisioning-sessions/'.$foreign['id'])->assertNotFound();
        $adminSession=$this->createSession($admin);$deviceId=$this->actingAs($admin)->postJson('/api/provisioning-sessions/'.$adminSession['id'].'/complete',$this->devicePayload('admin-device'))->assertOk()->json('data.device.id');$this->assertFalse(DeviceAccessAssignment::where('device_id',$deviceId)->exists());
    }
}
