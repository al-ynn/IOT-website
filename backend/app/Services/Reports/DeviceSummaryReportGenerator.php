<?php

namespace App\Services\Reports;

use App\Contracts\ReportGenerator;
use App\Models\Device;
use App\Models\ReportRun;

class DeviceSummaryReportGenerator implements ReportGenerator
{
    public function type(): string
    {
        return 'device_summary';
    }

    public function headers(): array
    {
        return ['Device', 'Identifier', 'Status', 'Location', 'Template', 'Type', 'Protocol', 'Last Seen', 'Assigned Staff'];
    }

    public function rows(ReportRun $run): iterable
    {
        $query = Device::query()->leftJoin('device_templates', 'device_templates.id', '=', 'devices.device_template_id')->leftJoin('locations', 'locations.id', '=', 'devices.location_id')->whereIn('devices.id', $run->resolved_configuration['device_ids'])->withCount('accessAssignments')->select('devices.*', 'device_templates.name as template_name', 'locations.name as location_name')->orderBy('devices.name');
        foreach ($query->cursor() as $device) {
            yield [$device->name, $device->external_id, $device->status, $device->location_name, $device->template_name, $device->type, $device->protocol, $device->last_seen?->toISOString(), $device->access_assignments_count];
        }
    }
}
