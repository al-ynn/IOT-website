<?php

namespace Tests\Feature;

use App\Models\CrashReport;
use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\DeviceCredential;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrashReportTest extends TestCase
{
    use RefreshDatabase;

    private function org(string $slug): Organization
    {
        return Organization::create(['name' => $slug, 'slug' => $slug]);
    }

    private function user(Organization $org, string $role = 'owner', bool $admin = false): User
    {
        return User::factory()->create(['organization_id' => $org->id, 'role' => $role, 'platform_role' => $admin ? 'platform_admin' : null, 'status' => 'active']);
    }

    private function device(Organization $org, string $name): Device
    {
        return $org->devices()->create(['name' => $name, 'external_id' => str($name)->slug().uniqid(), 'type' => 'sensor', 'protocol' => 'mqtt']);
    }

    private function assign(User $user, Device $device, string $level): void
    {
        DeviceAccessAssignment::create(['user_id' => $user->id, 'device_id' => $device->id, 'access_level' => $level]);
    }

    private function token(User $user, Device $device, array $scopes = ['crash:write']): array
    {
        $this->assign($user, $device, 'full_access');

        return $this->actingAs($user)->postJson("/api/devices/{$device->id}/credentials", ['name' => 'Crash agent', 'scopes' => $scopes])->assertCreated()->json();
    }

    private function payload(array $extra = []): array
    {
        return ['client_report_id' => 'report-1', 'crash_type' => 'hard_fault', 'reason' => 'Invalid memory access', 'message' => 'Runtime stopped', 'firmware_version' => '1.2.4', 'runtime_version' => 'rtos-3', 'uptime_seconds' => 82933, 'reboot_reason' => 'hard_fault', 'stack_trace' => "frame_one\nframe_two", 'context' => ['task' => 'sensor', 'registers' => ['pc' => '0x1234']], ...$extra];
    }

    public function test_device_auth_scope_identity_and_injection_are_enforced(): void
    {
        $org = $this->org('crash-auth');
        $user = $this->user($org);
        $a = $this->device($org, 'A');
        $b = $this->device($org, 'B');
        $token = $this->token($user, $a)['token'];
        $this->postJson('/api/device/crash-reports', $this->payload())->assertUnauthorized();
        $telemetry = $this->token($user, $b, ['telemetry:write'])['token'];
        $this->withToken($telemetry)->postJson('/api/device/crash-reports', $this->payload())->assertForbidden();
        $this->withToken($token)->postJson('/api/device/crash-reports', $this->payload(['device_id' => $b->id]))->assertUnprocessable();
        $this->withToken($token)->postJson('/api/device/crash-reports', $this->payload(['organization_id' => 999]))->assertUnprocessable();
        $this->withToken($token)->postJson('/api/device/crash-reports', $this->payload())->assertCreated()->assertJsonPath('data.device.id', (string) $a->id);
        $this->assertDatabaseHas('crash_reports', ['device_id' => $a->id, 'organization_id' => $org->id]);
        $credential = DeviceCredential::where('device_id', $a->id)->first();
        $credential->update(['revoked_at' => now()]);
        $this->withToken($token)->postJson('/api/device/crash-reports', $this->payload(['client_report_id' => 'second']))->assertUnauthorized();
    }

    public function test_payload_bounds_timestamp_and_json_content_type_are_enforced(): void
    {
        $org = $this->org('crash-validation');
        $user = $this->user($org);
        $device = $this->device($org, 'A');
        $token = $this->token($user, $device)['token'];
        $this->withToken($token)->postJson('/api/device/crash-reports', [])->assertUnprocessable();
        $this->withToken($token)->postJson('/api/device/crash-reports', $this->payload(['stack_trace' => str_repeat('x', 16001)]))->assertUnprocessable();
        $this->withToken($token)->postJson('/api/device/crash-reports', $this->payload(['context' => ['dump' => str_repeat('x', 17000)]]))->assertUnprocessable();
        $this->withToken($token)->postJson('/api/device/crash-reports', $this->payload(['unknown' => str_repeat('x', 70000)]))->assertStatus(413);
        $this->withToken($token)->postJson('/api/device/crash-reports', $this->payload(['reported_at' => now()->addDay()->toISOString()]))->assertUnprocessable();
        $this->withToken($token)->withHeader('Content-Type', 'text/plain')->post('/api/device/crash-reports', [], ['HTTP_AUTHORIZATION' => 'Bearer '.$token])->assertStatus(415);
    }

    public function test_context_is_recursively_sanitized_and_event_is_concise_and_linked(): void
    {
        $org = $this->org('crash-safe');
        $user = $this->user($org);
        $device = $this->device($org, 'Pump');
        $token = $this->token($user, $device)['token'];
        $secret = 'iotd_123e4567-e89b-12d3-a456-426614174000_abcdefghijklmnopqrstuvwxyz123456789ABCDEFG';
        $response = $this->withToken($token)->postJson('/api/device/crash-reports', $this->payload(['context' => ['token' => $secret, 'token_count' => 7, 'nested' => ['Password' => 'p4ss', 'authorization' => 'Bearer abc', 'note' => 'Bearer xyz']], 'stack_trace' => 'device frame '.$secret]))->assertCreated();
        $report = CrashReport::firstOrFail();
        $this->assertSame('[REDACTED]', $report->context['token']);
        $this->assertSame(7, $report->context['token_count']);
        $this->assertSame('[REDACTED]', $report->context['nested']['Password']);
        $this->assertStringNotContainsString('abc', json_encode($report->context));
        $this->assertStringNotContainsString($secret, $report->stack_trace);
        $event = $report->operationalEvent()->firstOrFail();
        $this->assertSame('device', $event->source);
        $this->assertSame('device_crash_reported', $event->event_type);
        $this->assertSame('error', $event->severity);
        $this->assertSame((string) $event->id, $response->json('data.operationalEventId'));
        $this->assertStringNotContainsString('frame', json_encode($event->context));
    }

    public function test_idempotency_is_per_device(): void
    {
        $org = $this->org('crash-idempotency');
        $user = $this->user($org);
        $a = $this->device($org, 'A');
        $b = $this->device($org, 'B');
        $aToken = $this->token($user, $a)['token'];
        $bToken = $this->token($user, $b)['token'];
        $first = $this->withToken($aToken)->postJson('/api/device/crash-reports', $this->payload())->assertCreated()->json('data.id');
        $retry = $this->withToken($aToken)->postJson('/api/device/crash-reports', $this->payload())->assertOk()->json('data.id');
        $this->assertSame($first, $retry);
        $this->withToken($bToken)->postJson('/api/device/crash-reports', $this->payload())->assertCreated();
        $this->assertDatabaseCount('crash_reports', 2);
        $this->assertDatabaseCount('operational_events', 2);
    }

    public function test_staff_visibility_totals_search_and_filters_do_not_leak(): void
    {
        $org = $this->org('crash-visible');
        $foreignOrg = $this->org('crash-foreign');
        $staff = $this->user($org, 'staff');
        $owner = $this->user($org);
        $foreign = $this->user($foreignOrg);
        $visible = $this->device($org, 'Visible');
        $hidden = $this->device($org, 'Hidden');
        $outside = $this->device($foreignOrg, 'Outside');
        $this->assign($staff, $visible, 'viewer');
        foreach ([[$visible, 'visible_reason'], [$hidden, 'hidden_unique_secret'], [$outside, 'foreign_reason']] as [$device,$reason]) {
            CrashReport::create(['organization_id' => $device->organization_id, 'device_id' => $device->id, 'crash_type' => 'panic', 'reason' => $reason, 'received_at' => now()]);
        }$list = $this->actingAs($staff)->getJson('/api/crash-reports')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.reason', 'visible_reason');
        $this->actingAs($staff)->getJson('/api/crash-reports?search=hidden_unique_secret')->assertOk()->assertJsonPath('meta.total', 0);
        $this->actingAs($staff)->getJson('/api/crash-reports?device_id='.$hidden->id)->assertOk()->assertJsonPath('meta.total', 0);
        $this->actingAs($staff)->getJson('/api/crash-reports/'.CrashReport::where('device_id', $hidden->id)->value('id'))->assertNotFound();
        $this->actingAs($foreign)->getJson('/api/crash-reports/'.CrashReport::where('device_id', $visible->id)->value('id'))->assertNotFound();
        $this->assertNotNull($list);
        $this->actingAs($owner)->getJson('/api/admin/crash-reports')->assertForbidden();
    }

    public function test_viewer_full_access_admin_and_immutability_routes(): void
    {
        $a = $this->org('crash-read-a');
        $b = $this->org('crash-read-b');
        $viewer = $this->user($a, 'staff');
        $full = $this->user($a);
        $admin = $this->user($a, 'staff', true);
        $device = $this->device($a, 'Pump');
        $foreignDevice = $this->device($b, 'Remote');
        $this->assign($viewer, $device, 'viewer');
        $this->assign($full, $device, 'full_access');
        $report = CrashReport::create(['organization_id' => $a->id, 'device_id' => $device->id, 'crash_type' => 'panic', 'received_at' => now()]);
        $foreign = CrashReport::create(['organization_id' => $b->id, 'device_id' => $foreignDevice->id, 'crash_type' => 'watchdog', 'received_at' => now()]);
        $this->actingAs($viewer)->getJson('/api/crash-reports/'.$report->id)->assertOk();
        $this->actingAs($full)->getJson('/api/crash-reports/'.$report->id)->assertOk();
        $this->actingAs($admin)->getJson('/api/admin/crash-reports/'.$report->id)->assertOk();
        $this->actingAs($admin)->getJson('/api/admin/crash-reports/'.$foreign->id)->assertNotFound();
        $this->actingAs($full)->postJson('/api/crash-reports', ['crash_type' => 'fake'])->assertStatus(405);
        $this->actingAs($full)->patchJson('/api/crash-reports/'.$report->id, ['reason' => 'changed'])->assertStatus(405);
        $this->actingAs($full)->deleteJson('/api/crash-reports/'.$report->id)->assertStatus(405);
    }

    public function test_device_deletion_cascades_crashes_without_orphans(): void
    {
        $org = $this->org('crash-delete');
        $device = $this->device($org, 'Pump');
        CrashReport::create(['organization_id' => $org->id, 'device_id' => $device->id, 'crash_type' => 'panic', 'received_at' => now()]);
        $device->delete();
        $this->assertDatabaseCount('crash_reports', 0);
    }
}
