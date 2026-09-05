<?php

namespace App\Jobs;

use App\Models\ReportRun;
use App\Services\ReportGenerationService;
use App\Services\ResourceLifecycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    public int $lifecycleGeneration;

    public function __construct(public string $runId, ?int $lifecycleGeneration = null)
    {
        $reportId = ReportRun::query()->whereKey($runId)->value('report_id');
        $this->lifecycleGeneration = $lifecycleGeneration ?? ($reportId ? app(ResourceLifecycleService::class)->generation('report', $reportId) : 1);
    }

    public function handle(ReportGenerationService $service): void
    {
        $run = ReportRun::with(['report', 'requester'])->findOrFail($this->runId);
        if (! $run->report || ! app(ResourceLifecycleService::class)->allowsGeneration('report', $run->report_id, $this->lifecycleGeneration)) {
            $run->update(['status' => 'cancelled', 'failed_at' => now(), 'failure_code' => 'report_lifecycle_stale', 'failure_message' => 'Report lifecycle changed after this run was queued.']);

            return;
        }
        try {
            $service->generate($run);
        } catch (\Throwable $error) {
            $run->update(['status' => 'failed', 'failed_at' => now(), 'failure_code' => 'generation_failed', 'failure_message' => 'Report generation failed safely.']);
            report($error);
        }
    }

    public function failed(\Throwable $error): void
    {
        ReportRun::query()->whereKey($this->runId)->whereIn('status', ['pending', 'running'])->update(['status' => 'failed', 'failed_at' => now(), 'failure_code' => 'job_failed', 'failure_message' => 'Report generation failed safely.', 'updated_at' => now()]);
        report($error);
    }
}
