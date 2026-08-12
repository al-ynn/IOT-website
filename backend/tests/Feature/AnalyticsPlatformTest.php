<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\TelemetryRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnalyticsPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function context(array $features = ['analytics.advanced'], string $role = 'owner'): array
    {
        $plan = Plan::create(['id' => 'analytics-'.uniqid(), 'name' => 'Analytics', 'description' => '', 'price' => 49, 'currency' => 'USD', 'interval' => 'monthly', 'features' => $features, 'device_limit' => 10, 'user_limit' => 10, 'dashboard_limit' => 10, 'automation_limit' => 10, 'active' => true, 'is_default' => false]);
        $organization = Organization::create(['name' => 'Analytics Org', 'slug' => 'analytics-'.uniqid()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => $role]);
        $organization->subscriptions()->create(['plan_id' => $plan->id, 'status' => 'active', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        return [$organization, $user, $plan];
    }

    private function device(Organization $organization, string $name = 'Sensor', string $status = 'online')
    {
        return $organization->devices()->create(['name' => $name, 'external_id' => uniqid('sensor-'), 'type' => 'sensor', 'protocol' => 'mqtt', 'status' => $status]);
    }

    private function record($device, string $key, float $value, string $at, ?string $unit = null): TelemetryRecord
    {
        return TelemetryRecord::create(['device_id' => $device->id, 'key' => $key, 'value' => $value, 'unit' => $unit, 'recorded_at' => Carbon::parse($at)]);
    }

    public function test_analytics_requires_authentication_permission_and_entitlement(): void
    {
        $this->getJson('/api/analytics/summary')->assertUnauthorized();
        [, $guest] = $this->context(role: 'guest');
        $this->actingAs($guest)->getJson('/api/analytics/summary')->assertForbidden();
        [, $free] = $this->context([]);
        $this->actingAs($free)->getJson('/api/analytics/summary')->assertForbidden()->assertJson(['code' => 'FEATURE_NOT_AVAILABLE']);
        [, $premium] = $this->context();
        $this->actingAs($premium)->getJson('/api/analytics/summary')->assertOk();
    }

    public function test_summary_is_real_range_filtered_and_organization_scoped(): void
    {
        Carbon::setTestNow('2026-08-11 12:00:00');
        [$organization, $user] = $this->context();
        $online = $this->device($organization, 'Online', 'online');
        $this->device($organization, 'Offline', 'offline');
        $this->record($online, 'temperature', 10, '2026-08-11 10:00:00', 'C');
        $this->record($online, 'temperature', 20, '2026-08-11 11:00:00', 'C');
        $this->record($online, 'temperature', 999, '2026-08-01 11:00:00', 'C');
        [$foreign] = $this->context();
        $this->record($this->device($foreign), 'temperature', 500, '2026-08-11 11:00:00', 'C');
        $this->actingAs($user)->getJson('/api/analytics/summary?range=24h')->assertOk()->assertJsonPath('totalDevices', 2)->assertJsonPath('onlineDevices', 1)->assertJsonPath('offlineDevices', 1)->assertJsonPath('telemetryRecords', 2)->assertJsonPath('metrics.0.metric', 'temperature')->assertJsonPath('metrics.0.average', 15)->assertJsonPath('metrics.0.minimum', 10)->assertJsonPath('metrics.0.maximum', 20)->assertJsonPath('metrics.0.latest', 20);
    }

    public function test_series_supports_all_aggregations_statistics_and_chronological_points(): void
    {
        Carbon::setTestNow('2026-08-11 12:00:00');
        [$organization, $user] = $this->context();$device = $this->device($organization);
        $this->record($device, 'power', 10, '2026-08-11 10:05:00', 'W');$this->record($device, 'power', 20, '2026-08-11 10:30:00', 'W');$this->record($device, 'power', 30, '2026-08-11 11:00:00', 'W');
        foreach (['average' => 15, 'minimum' => 10, 'maximum' => 20, 'sum' => 30, 'count' => 2] as $aggregation => $firstPoint) {
            $response = $this->actingAs($user)->getJson("/api/analytics/telemetry/power?deviceId=$device->id&range=24h&interval=hour&aggregation=$aggregation")->assertOk()->assertJsonPath('statistics.count', 3)->assertJsonPath('statistics.average', 20)->assertJsonPath('statistics.minimum', 10)->assertJsonPath('statistics.maximum', 30)->assertJsonPath('statistics.sum', 60)->assertJsonPath('statistics.latest', 30)->assertJsonPath('points.0.value', $firstPoint)->assertJsonCount(2, 'points');
            $this->assertLessThan($response->json('points.1.timestamp'), $response->json('points.0.timestamp'));
        }
    }

    public function test_device_analytics_and_metric_discovery_use_only_real_telemetry(): void
    {
        [$organization, $user] = $this->context();$device = $this->device($organization);$empty = $this->device($organization, 'Empty');
        $this->record($device, 'humidity', 40, now()->subHour()->toDateTimeString(), '%');
        $this->actingAs($user)->getJson("/api/analytics/devices/$device->id")->assertOk()->assertJsonPath('device.id', (string) $device->id)->assertJsonPath('telemetryRecords', 1)->assertJsonPath('metrics.0.metric', 'humidity');
        $this->actingAs($user)->getJson("/api/analytics/devices/$empty->id")->assertOk()->assertJsonPath('telemetryRecords', 0)->assertJsonCount(0, 'metrics');
    }

    public function test_foreign_device_and_telemetry_never_leak(): void
    {
        [$organizationA, $userA] = $this->context();$deviceA = $this->device($organizationA);$this->record($deviceA, 'temperature', 10, now()->subHour()->toDateTimeString());
        [$organizationB] = $this->context();$deviceB = $this->device($organizationB);$this->record($deviceB, 'temperature', 999, now()->subHour()->toDateTimeString());
        $this->actingAs($userA)->getJson("/api/analytics/devices/$deviceB->id")->assertNotFound();
        $this->actingAs($userA)->getJson("/api/analytics/telemetry/temperature?deviceId=$deviceB->id")->assertNotFound();
        $this->actingAs($userA)->getJson('/api/analytics/summary')->assertOk()->assertJsonPath('telemetryRecords', 1)->assertJsonPath('metrics.0.maximum', 10);
    }

    public function test_invalid_metric_interval_range_and_large_queries_are_rejected(): void
    {
        [$organization, $user] = $this->context();$device = $this->device($organization);$this->record($device, 'temperature', 10, now()->subHour()->toDateTimeString());
        $this->actingAs($user)->getJson("/api/analytics/telemetry/not%20safe?deviceId=$device->id")->assertUnprocessable()->assertJsonValidationErrors('metric');
        $this->actingAs($user)->getJson("/api/analytics/telemetry/missing?deviceId=$device->id")->assertUnprocessable()->assertJsonValidationErrors('metric');
        $this->actingAs($user)->getJson("/api/analytics/telemetry/temperature?deviceId=$device->id&interval=year")->assertUnprocessable()->assertJsonValidationErrors('interval');
        $this->actingAs($user)->getJson('/api/analytics/summary?range=custom&from=2026-08-11T12:00:00Z&to=2026-08-10T12:00:00Z')->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->actingAs($user)->getJson('/api/analytics/summary?range=custom&from=2026-01-01T00:00:00Z&to=2026-08-01T00:00:00Z')->assertUnprocessable()->assertJsonValidationErrors('from');
    }

    public function test_billing_downgrade_immediately_removes_analytics_access(): void
    {
        [$organization, $user] = $this->context();$this->actingAs($user)->getJson('/api/analytics/summary')->assertOk();
        $free = Plan::create(['id' => 'free-downgrade', 'name' => 'Free', 'description' => '', 'price' => 0, 'currency' => 'USD', 'interval' => 'monthly', 'features' => [], 'device_limit' => 1, 'user_limit' => 1, 'dashboard_limit' => 1, 'automation_limit' => 0, 'active' => true, 'is_default' => true]);
        $organization->subscription()->update(['plan_id' => $free->id]);
        $this->actingAs($user)->getJson('/api/analytics/summary')->assertForbidden()->assertJson(['code' => 'FEATURE_NOT_AVAILABLE']);
    }

    public function test_telemetry_ingestion_persists_the_record_used_by_analytics(): void
    {
        [$organization, $user] = $this->context(['analytics.advanced', 'telemetry.basic']);$device = $this->device($organization);
        $this->actingAs($user)->postJson('/api/telemetry', ['device_id' => (string) $device->id, 'key' => 'voltage', 'value' => 230.5, 'unit' => 'V'])->assertOk();
        $this->assertDatabaseHas('telemetry_records', ['device_id' => $device->id, 'key' => 'voltage', 'value' => 230.5]);
        $this->actingAs($user)->getJson("/api/analytics/telemetry/voltage?deviceId=$device->id&range=1h&interval=minute&aggregation=average")->assertOk()->assertJsonPath('statistics.latest', 230.5)->assertJsonPath('points.0.value', 230.5);
    }
}
