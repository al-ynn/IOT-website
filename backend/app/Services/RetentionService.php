<?php

namespace App\Services;

use App\Models\CrashReport;
use App\Models\OperationalEvent;
use App\Models\ReportRun;
use App\Models\TelemetryRecord;
use Illuminate\Support\Facades\Storage;

class RetentionService
{
    public function __construct(private SystemSettingsService $settings) {}

    public function apply(): array
    {
        return [
            'telemetry_records' => $this->deleteModels(TelemetryRecord::class, 'recorded_at', $this->settings->integer('telemetry_retention_days')),
            'operational_events' => $this->deleteModels(OperationalEvent::class, 'occurred_at', $this->settings->integer('operational_event_retention_days')),
            'crash_reports' => $this->deleteModels(CrashReport::class, 'received_at', $this->settings->integer('crash_report_retention_days')),
            'report_artifacts' => $this->expireReportArtifacts(),
        ];
    }

    private function deleteModels(string $model, string $column, int $days): int
    {
        $count = 0;
        $model::query()->where($column, '<', now('UTC')->subDays($days))->orderBy('id')->chunkById(500, function ($records) use (&$count) {
            foreach ($records as $record) {
                $record->delete();
                $count++;
            }
        });

        return $count;
    }

    private function expireReportArtifacts(): int
    {
        $count = 0;
        ReportRun::query()->where('status', 'completed')->whereNotNull('artifact_path')->where('completed_at', '<', now('UTC')->subDays($this->settings->integer('report_artifact_retention_days')))->orderBy('id')->chunkById(100, function ($runs) use (&$count) {
            foreach ($runs as $run) {
                Storage::disk($run->artifact_disk)->delete($run->artifact_path);
                $run->update(['artifact_disk' => null, 'artifact_path' => null, 'artifact_format' => null, 'artifact_size' => null]);
                $count++;
            }
        });

        return $count;
    }
}
