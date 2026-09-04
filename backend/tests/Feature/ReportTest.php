<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportJob;
use App\Models\Device;
use App\Models\DeviceAccessAssignment;
use App\Models\OperationalEvent;
use App\Models\Organization;
use App\Models\Report;
use App\Models\ReportRun;
use App\Models\TelemetryRecord;
use App\Models\User;
use App\Services\ReportGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function org(string $slug): Organization
    {
        return Organization::create(['name' => $slug, 'slug' => $slug]);
    }

    private function user(Organization $org, string $role = 'owner'): User
    {
        return User::factory()->create(['organization_id' => $org->id, 'role' => $role, 'status' => 'active']);
    }

    private function device(Organization $org, string $name): Device
    {
        return $org->devices()->create(['name' => $name, 'external_id' => str($name)->slug().uniqid(), 'status' => 'online', 'type' => 'sensor', 'protocol' => 'mqtt', 'location' => 'Plant 1']);
    }

    private function assign(User $user, Device $device, string $level = 'viewer'): void
    {
        DeviceAccessAssignment::create(['user_id' => $user->id, 'device_id' => $device->id, 'access_level' => $level]);
    }

    private function config(Device $device, array $extra = []): array
    {
        return ['device_ids' => [$device->id], 'metric_keys' => ['temperature'], 'date_range_mode' => 'relative', 'relative_range' => 'last_7_days', ...$extra];
    }

    private function create(User $user, Device $device, string $type = 'device_telemetry', ?array $config = null): array
    {
        return $this->actingAs($user)->postJson('/api/reports', ['name' => 'Operations report', 'description' => 'Real data', 'report_type' => $type, 'configuration' => $config ?? ($type === 'device_summary' ? ['device_ids' => [$device->id]] : $this->config($device))])->assertCreated()->json('data');
    }

    public function test_definition_authorization_tenancy_and_injection(): void
    {
        $a = $this->org('reports-a');
        $b = $this->org('reports-b');
        $viewer = $this->user($a, 'staff');
        $foreign = $this->user($b);
        $assigned = $this->device($a, 'Assigned');
        $hidden = $this->device($a, 'Hidden');
        $outside = $this->device($b, 'Outside');
        $this->assign($viewer, $assigned, 'viewer');
        $this->getJson('/api/reports')->assertUnauthorized();
        $created = $this->create($viewer, $assigned);
        $row = Report::findOrFail($created['id']);
        $this->assertSame($a->id, $row->organization_id);
        $this->assertSame($viewer->id, $row->created_by);
        $this->actingAs($viewer)->postJson('/api/reports', ['name' => 'Attack', 'report_type' => 'device_summary', 'organization_id' => $b->id, 'created_by' => $foreign->id, 'configuration' => ['device_ids' => [$assigned->id]]])->assertUnprocessable();
        $this->actingAs($viewer)->postJson('/api/reports', ['name' => 'Hidden', 'report_type' => 'device_summary', 'configuration' => ['device_ids' => [$hidden->id]]])->assertUnprocessable();
        $this->actingAs($viewer)->postJson('/api/reports', ['name' => 'Foreign', 'report_type' => 'device_summary', 'configuration' => ['device_ids' => [$outside->id]]])->assertUnprocessable();
        $this->actingAs($foreign)->getJson('/api/reports/'.$row->id)->assertNotFound();
        $this->actingAs($foreign)->getJson('/api/reports')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_type_specific_configuration_and_query_bounds(): void
    {
        $org = $this->org('reports-config');
        $user = $this->user($org);
        $device = $this->device($org, 'Pump');
        $this->assign($user, $device);
        foreach (['unknown', 'crash_predictions'] as $type) {
            $this->actingAs($user)->postJson('/api/reports', ['name' => 'Bad', 'report_type' => $type, 'configuration' => ['device_ids' => [$device->id]]])->assertUnprocessable();
        }$this->actingAs($user)->postJson('/api/reports', ['name' => 'Injected', 'report_type' => 'device_summary', 'configuration' => ['device_ids' => [$device->id], 'sql' => 'DROP TABLE devices']])->assertUnprocessable();
        $this->actingAs($user)->postJson('/api/reports', ['name' => 'Long', 'report_type' => 'device_telemetry', 'configuration' => ['device_ids' => [$device->id], 'date_range_mode' => 'absolute', 'from' => now()->subDays(40)->toISOString(), 'to' => now()->subDay()->toISOString()]])->assertUnprocessable();
        $this->actingAs($user)->postJson('/api/reports', ['name' => 'Metric', 'report_type' => 'device_telemetry', 'configuration' => $this->config($device, ['metric_keys' => ['temperature', 'unsafe metric']])])->assertUnprocessable();
    }

    public function test_run_is_queued_and_revalidates_assignment_before_generation(): void
    {
        Queue::fake();
        Storage::fake('local');
        $org = $this->org('reports-runtime');
        $user = $this->user($org, 'staff');
        $device = $this->device($org, 'Pump');
        $this->assign($user, $device, 'viewer');
        $report = $this->create($user, $device);
        $response = $this->actingAs($user)->postJson('/api/reports/'.$report['id'].'/runs')->assertAccepted()->assertJsonPath('data.status', 'pending');
        Queue::assertPushed(GenerateReportJob::class);
        DeviceAccessAssignment::where('user_id', $user->id)->where('device_id', $device->id)->delete();
        $run = ReportRun::findOrFail($response->json('data.id'));
        (new GenerateReportJob($run->id))->handle(app(ReportGenerationService::class));
        $this->assertSame('failed', $run->refresh()->status);
        $this->assertSame('generation_failed', $run->failure_code);
        $this->assertSame('Report generation failed safely.', $run->failure_message);
    }

    public function test_telemetry_csv_is_real_private_filtered_and_formula_safe(): void
    {
        Queue::fake();
        Storage::fake('local');
        $org = $this->org('reports-csv');
        $user = $this->user($org);
        $device = $this->device($org, '=Danger');
        $hidden = $this->device($org, 'Hidden');
        $this->assign($user, $device, 'viewer');
        TelemetryRecord::create(['device_id' => $device->id, 'key' => 'temperature', 'value' => 21.5, 'unit' => '°C', 'recorded_at' => now()->subHour()]);
        TelemetryRecord::create(['device_id' => $device->id, 'key' => 'humidity', 'value' => 50, 'unit' => '%', 'recorded_at' => now()->subHour()]);
        TelemetryRecord::create(['device_id' => $hidden->id, 'key' => 'temperature', 'value' => 99, 'unit' => '°C', 'recorded_at' => now()->subHour()]);
        $report = $this->create($user, $device);
        $runId = $this->actingAs($user)->postJson('/api/reports/'.$report['id'].'/runs')->assertAccepted()->json('data.id');
        (new GenerateReportJob($runId))->handle(app(ReportGenerationService::class));
        $run = ReportRun::findOrFail($runId);
        $this->assertSame('completed', $run->status);
        $this->assertSame(1, $run->row_count);
        Storage::disk('local')->assertExists($run->artifact_path);
        $csv = Storage::disk('local')->get($run->artifact_path);
        $this->assertStringContainsString("'=Danger", $csv);
        $this->assertStringContainsString('temperature', $csv);
        $this->assertStringContainsString('21.5', $csv);
        $this->assertStringContainsString('°C', $csv);
        $this->assertStringNotContainsString('humidity', $csv);
        $this->assertStringNotContainsString($hidden->external_id, $csv);
        $this->assertStringNotContainsString(',99,', $csv);
        $this->actingAs($user)->get('/api/reports/'.$report['id'].'/runs/'.$run->id.'/download')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_device_summary_and_operational_event_generators_use_real_safe_data(): void
    {
        Queue::fake();
        Storage::fake('local');
        $org = $this->org('reports-generators');
        $user = $this->user($org);
        $device = $this->device($org, 'Pump');
        $this->assign($user, $device, 'full_access');
        OperationalEvent::create(['organization_id' => $org->id, 'device_id' => $device->id, 'device_bound' => true, 'source' => 'device', 'event_type' => 'device_crash_reported', 'severity' => 'error', 'message' => 'Safe event', 'context' => ['secret' => 'must-not-export'], 'occurred_at' => now()]);
        foreach ([['device_summary', ['device_ids' => [$device->id]]], ['operational_events', ['device_ids' => [$device->id], 'severity' => 'error', 'date_range_mode' => 'relative', 'relative_range' => 'last_24_hours']]] as [$type,$config]) {
            $report = $this->create($user, $device, $type, $config);
            $runId = $this->actingAs($user)->postJson('/api/reports/'.$report['id'].'/runs')->assertAccepted()->json('data.id');
            (new GenerateReportJob($runId))->handle(app(ReportGenerationService::class));
            $run = ReportRun::findOrFail($runId);
            $this->assertSame('completed', $run->status);
            $csv = Storage::disk('local')->get($run->artifact_path);
            $this->assertStringContainsString('Pump', $csv);
            $this->assertStringNotContainsString('must-not-export', $csv);
        }
    }

    public function test_run_snapshot_is_stable_after_definition_edit(): void
    {
        Queue::fake();
        $org = $this->org('reports-snapshot');
        $user = $this->user($org);
        $device = $this->device($org, 'Pump');
        $this->assign($user, $device);
        $report = $this->create($user, $device);
        $runId = $this->actingAs($user)->postJson('/api/reports/'.$report['id'].'/runs')->assertAccepted()->json('data.id');
        $snapshot = ReportRun::findOrFail($runId)->resolved_configuration;
        $this->actingAs($user)->patchJson('/api/reports/'.$report['id'], ['configuration' => $this->config($device, ['relative_range' => 'last_30_days']), 'baseRevisionId' => \App\Models\ResourceRevision::where(['resource_type' => 'report', 'resource_id' => $report['id']])->latest('revision_number')->value('id')])->assertOk();
        $this->assertSame('last_7_days', $snapshot['relative_range']);
        $this->assertSame($snapshot, ReportRun::findOrFail($runId)->resolved_configuration);
    }

    public function test_download_revalidates_access_and_nested_ownership(): void
    {
        Queue::fake();
        Storage::fake('local');
        $org = $this->org('reports-download');
        $user = $this->user($org);
        $a = $this->device($org, 'A');
        $b = $this->device($org, 'B');
        $this->assign($user, $a);
        $this->assign($user, $b);
        $reportA = $this->create($user, $a, 'device_summary');
        $reportB = $this->create($user, $b, 'device_summary');
        $runId = $this->actingAs($user)->postJson('/api/reports/'.$reportA['id'].'/runs')->assertAccepted()->json('data.id');
        (new GenerateReportJob($runId))->handle(app(ReportGenerationService::class));
        $this->actingAs($user)->get('/api/reports/'.$reportB['id'].'/runs/'.$runId.'/download')->assertNotFound();
        DeviceAccessAssignment::where('user_id', $user->id)->where('device_id', $a->id)->delete();
        $this->actingAs($user)->get('/api/reports/'.$reportA['id'].'/runs/'.$runId.'/download')->assertUnprocessable();
    }

    public function test_active_report_cannot_be_deleted_and_completed_history_is_soft_retained(): void
    {
        Queue::fake();
        $org = $this->org('reports-delete');
        $user = $this->user($org);
        $device = $this->device($org, 'Pump');
        $this->assign($user, $device);
        $report = $this->create($user, $device);
        $runId = $this->actingAs($user)->postJson('/api/reports/'.$report['id'].'/runs')->assertAccepted()->json('data.id');
        $this->actingAs($user)->deleteJson('/api/reports/'.$report['id'])->assertUnprocessable();
        ReportRun::findOrFail($runId)->update(['status' => 'completed']);
        $this->actingAs($user)->deleteJson('/api/reports/'.$report['id'])->assertNoContent();
        $this->assertSoftDeleted('reports', ['id' => $report['id']]);
        $this->assertDatabaseHas('report_runs', ['id' => $runId]);
    }

    public function test_worker_level_failure_cannot_leave_report_pending_or_running(): void
    {
        Queue::fake();
        $org = $this->org('reports-worker-failure');
        $user = $this->user($org);
        $device = $this->device($org, 'Pump');
        $this->assign($user, $device);
        $report = $this->create($user, $device);
        $runId = $this->actingAs($user)->postJson('/api/reports/'.$report['id'].'/runs')->assertAccepted()->json('data.id');
        $job = new GenerateReportJob($runId);
        $job->failed(new \RuntimeException('worker stopped'));
        $run = ReportRun::findOrFail($runId);
        $this->assertSame('failed', $run->status);
        $this->assertSame('job_failed', $run->failure_code);
        $this->assertSame('Report generation failed safely.', $run->failure_message);
    }
}
