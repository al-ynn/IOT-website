<?php

namespace App\Http\Controllers\DeviceApi;

use App\Events\TelemetryUpdated;
use App\Http\Controllers\Controller;
use App\Services\Automation\AutomationTriggerEngine;
use App\Services\TelemetrySchemaService;
use Illuminate\Http\Request;

class DeviceTelemetryController extends Controller
{
    public function store(Request $request, AutomationTriggerEngine $automation, TelemetrySchemaService $schema)
    {
        $device = $request->attributes->get('device');
        $data = $request->validate(['key' => 'required|string|max:100', 'value' => 'present', 'unit' => 'nullable|string|max:50', 'device_id' => 'prohibited']);
        $record = $schema->record($device, $data['key'], $data['value']);
        $eventData = [...$data, 'device_id' => (string) $device->id];
        event(new TelemetryUpdated($eventData));
        $automation->dispatch($device->organization, 'telemetry', ['deviceId' => (string) $device->id, 'field' => $data['key'], $data['key'] => $data['value'], 'value' => $data['value'], 'timestamp' => now()->toISOString()]);

        return response()->json(['message' => 'Telemetry received', 'data' => [...$data, 'device_id' => (string) $device->id, 'recorded_at' => $record->recorded_at->toISOString()]], 201);
    }
}
