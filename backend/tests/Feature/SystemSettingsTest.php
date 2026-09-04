<?php

namespace Tests\Feature;

use App\Models\CrashReport;
use App\Models\OperationalEvent;
use App\Models\Organization;
use App\Models\Report;
use App\Models\ReportRun;
use App\Models\TelemetryRecord;
use App\Models\User;
use App\Services\RetentionService;
use App\Services\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $organization = \App\Models\Organization::firstOrCreate(['slug' => 'system-settings'], ['name' => 'System Settings']);
        return User::factory()->create(['organization_id' => $organization->id, 'role' => $role === 'admin' ? 'owner' : $role, 'platform_role' => $role === 'admin' ? 'platform_admin' : null, 'status' => 'active']);
    }

    public function test_only_admin_can_read_and_manage_registered_settings(): void
    {
        $staff = $this->user('staff');
        $admin = $this->user('admin');
        $this->getJson('/api/admin/system-settings')->assertUnauthorized();
        $this->actingAs($staff)->getJson('/api/admin/system-settings')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/admin/system-settings')->assertOk()->assertJsonFragment(['key' => 'provisioning_session_expiry_minutes', 'value' => 30, 'is_overridden' => false]);
        $this->actingAs($admin)->patchJson('/api/admin/system-settings/pricing.plan_limit', ['value' => 12])->assertNotFound();
        $this->assertDatabaseCount('system_settings', 0);
    }

    public function test_types_bounds_actor_cache_and_reset_are_server_authoritative(): void
    {
        $admin = $this->user('admin');
        foreach (['abc', true, ['unsafe']] as $invalid) {
            $this->actingAs($admin)->patchJson('/api/admin/system-settings/provisioning_session_expiry_minutes', ['value' => $invalid])->assertUnprocessable();
        }
        $this->actingAs($admin)->patchJson('/api/admin/system-settings/provisioning_session_expiry_minutes', ['value' => 4])->assertUnprocessable();
        $this->actingAs($admin)->patchJson('/api/admin/system-settings/provisioning_session_expiry_minutes', ['value' => 1441])->assertUnprocessable();
        $this->actingAs($admin)->patchJson('/api/admin/system-settings/provisioning_session_expiry_minutes', ['value' => 45, 'updated_by' => 999])->assertUnprocessable();
        $service = app(SystemSettingsService::class);
        $this->assertSame(30, $service->integer('provisioning_session_expiry_minutes'));
        $this->actingAs($admin)->patchJson('/api/admin/system-settings/provisioning_session_expiry_minutes', ['value' => 45])->assertOk()->assertJsonPath('data.value', 45);
        $this->assertSame(45, $service->integer('provisioning_session_expiry_minutes'));
        $this->assertDatabaseHas('system_settings', ['key' => 'provisioning_session_expiry_minutes', 'updated_by' => $admin->id]);
        $this->actingAs($admin)->postJson('/api/admin/system-settings/provisioning_session_expiry_minutes/reset')->assertOk()->assertJsonPath('data.value', 30);
        $this->assertSame(30, $service->integer('provisioning_session_expiry_minutes'));
        $this->assertDatabaseMissing('system_settings', ['key' => 'provisioning_session_expiry_minutes']);
    }

    public function test_provisioning_and_report_domains_consume_overrides(): void
    {
        $admin = $this->user('admin');
        $org = Organization::create(['name' => 'Settings Org', 'slug' => 'settings-org']);
        $owner = User::factory()->create(['organization_id' => $org->id, 'role' => 'owner', 'status' => 'active']);
        $device = $org->devices()->create(['name' => 'Pump', 'external_id' => 'settings-pump', 'type' => 'pump', 'protocol' => 'mqtt']);
        $owner->deviceAccessAssignments()->create(['device_id' => $device->id, 'access_level' => 'viewer']);
        $this->actingAs($admin)->patchJson('/api/admin/system-settings/provisioning_session_expiry_minutes', ['value' => 45])->assertOk();
        $before = now();
        $this->actingAs($owner)->postJson('/api/provisioning-sessions', ['name' => 'Runtime policy'])->assertCreated();
        $this->assertDatabaseHas('provisioning_sessions', ['organization_id' => $org->id]);
        $expiry = $org->provisioningSessions()->latest()->firstOrFail()->expires_at;
        $this->assertTrue($expiry->between($before->copy()->addMinutes(44), $before->copy()->addMinutes(46)));
        $this->actingAs($admin)->patchJson('/api/admin/system-settings/report_max_date_range_days', ['value' => 7])->assertOk();
        $this->actingAs($owner)->postJson('/api/reports', ['name' => 'Too wide', 'report_type' => 'device_telemetry', 'configuration' => ['device_ids' => [$device->id], 'date_range_mode' => 'absolute', 'from' => now()->subDays(9)->toISOString(), 'to' => now()->subDay()->toISOString()]])->assertUnprocessable();
    }

    public function test_retention_deletes_only_expired_records_and_expires_only_old_report_files(): void
    {
        Storage::fake('local');
        $admin = $this->user('admin');
        $org = Organization::create(['name' => 'Retention Org', 'slug' => 'retention-org']);
        $device = $org->devices()->create(['name' => 'Sensor', 'external_id' => 'retention-sensor', 'type' => 'sensor', 'protocol' => 'mqtt']);
        foreach (['telemetry_retention_days', 'operational_event_retention_days', 'crash_report_retention_days', 'report_artifact_retention_days'] as $key) {
            $this->actingAs($admin)->patchJson("/api/admin/system-settings/{$key}", ['value' => 10])->assertOk();
        }
        TelemetryRecord::create(['device_id' => $device->id, 'key' => 'temperature', 'value' => 1, 'recorded_at' => now()->subDays(11)]);
        TelemetryRecord::create(['device_id' => $device->id, 'key' => 'temperature', 'value' => 2, 'recorded_at' => now()->subDays(10)]);
        OperationalEvent::create(['organization_id' => $org->id, 'device_id' => $device->id, 'device_bound' => true, 'source' => 'device', 'event_type' => 'device_crash_reported', 'severity' => 'error', 'title' => 'Old', 'message' => 'Old', 'context' => [], 'occurred_at' => now()->subDays(11)]);
        CrashReport::create(['organization_id' => $org->id, 'device_id' => $device->id, 'crash_type' => 'panic', 'received_at' => now()->subDays(11)]);
        $report = Report::create(['organization_id' => $org->id, 'name' => 'Inventory', 'report_type' => 'device_summary', 'configuration' => ['device_ids' => [$device->id]], 'created_by' => $admin->id]);
        Storage::disk('local')->put('reports/old.csv', 'old');
        $run = ReportRun::create(['report_id' => $report->id, 'organization_id' => $org->id, 'requested_by' => $admin->id, 'status' => 'completed', 'resolved_configuration' => [], 'completed_at' => now()->subDays(11), 'artifact_disk' => 'local', 'artifact_path' => 'reports/old.csv', 'artifact_format' => 'csv', 'artifact_size' => 3]);
        $result = app(RetentionService::class)->apply();
        $this->assertSame(1, $result['telemetry_records']);
        $this->assertDatabaseCount('telemetry_records', 1);
        $this->assertDatabaseCount('operational_events', 0);
        $this->assertDatabaseCount('crash_reports', 0);
        Storage::disk('local')->assertMissing('reports/old.csv');
        $this->assertNull($run->refresh()->artifact_path);
        $this->assertDatabaseHas('devices', ['id' => $device->id]);
    }
}
