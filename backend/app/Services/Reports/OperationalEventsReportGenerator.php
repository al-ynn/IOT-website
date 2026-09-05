<?php

namespace App\Services\Reports;

use App\Contracts\ReportGenerator;
use App\Models\OperationalEvent;
use App\Models\ReportRun;

class OperationalEventsReportGenerator implements ReportGenerator
{
    public function type(): string
    {
        return 'operational_events';
    }

    public function headers(): array
    {
        return ['Occurred At', 'Severity', 'Source', 'Event Type', 'Device', 'Message'];
    }

    public function rows(ReportRun $run): iterable
    {
        $c = $run->resolved_configuration;
        $query = OperationalEvent::query()->leftJoin('devices', 'devices.id', '=', 'operational_events.device_id')->where('operational_events.organization_id', $run->organization_id)->whereIn('operational_events.device_id', $c['device_ids'])->whereBetween('operational_events.occurred_at', [$c['resolved_from'], $c['resolved_to']])->when($c['severity'] ?? null, fn ($q, $v) => $q->where('operational_events.severity', $v))->when($c['source'] ?? null, fn ($q, $v) => $q->where('operational_events.source', $v))->select('operational_events.*', 'devices.name as device_name')->orderBy('operational_events.occurred_at')->orderBy('operational_events.id');
        foreach ($query->cursor() as $event) {
            yield [$event->occurred_at->toISOString(), $event->severity, $event->source, $event->event_type, $event->device_name, $event->message];
        }
    }
}
