<?php

namespace App\Services;

use App\Contracts\ReportGenerator;
use App\Services\Reports\DeviceSummaryReportGenerator;
use App\Services\Reports\DeviceTelemetryReportGenerator;
use App\Services\Reports\OperationalEventsReportGenerator;

class ReportGeneratorRegistry
{
    private array $generators;

    public function __construct(DeviceTelemetryReportGenerator $telemetry, DeviceSummaryReportGenerator $summary, OperationalEventsReportGenerator $events)
    {
        $this->generators = [$telemetry->type() => $telemetry, $summary->type() => $summary, $events->type() => $events];
    }

    public function for(string $type): ReportGenerator
    {
        return $this->generators[$type] ?? throw new \InvalidArgumentException('Unsupported Report generator.');
    }
}
