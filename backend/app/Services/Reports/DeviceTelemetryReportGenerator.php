<?php

namespace App\Services\Reports;

use App\Contracts\ReportGenerator;
use App\Models\ReportRun;
use App\Models\TelemetryRecord;

class DeviceTelemetryReportGenerator implements ReportGenerator
{
    public function type(): string
    {
        return 'device_telemetry';
    }

    public function headers(): array
    {
        return ['Recorded At', 'Device', 'Identifier', 'Metric', 'Value', 'Unit'];
    }

    public function rows(ReportRun $run): iterable
    {
        $c = $run->resolved_configuration;
        $query = TelemetryRecord::query()->join('devices', 'devices.id', '=', 'telemetry_records.device_id')->whereIn('telemetry_records.device_id', $c['device_ids'])->whereBetween('telemetry_records.recorded_at', [$c['resolved_from'], $c['resolved_to']])->when($c['metric_keys'] ?? null, fn ($q, $keys) => $q->whereIn('telemetry_records.key', $keys))->select('telemetry_records.*', 'devices.name as device_name', 'devices.external_id as device_identifier')->orderBy('telemetry_records.recorded_at')->orderBy('telemetry_records.id');
        foreach ($query->cursor() as $row) {
            yield [$row->recorded_at->toISOString(), $row->device_name, $row->device_identifier, $row->key, $row->value, $row->unit];
        }
    }
}
