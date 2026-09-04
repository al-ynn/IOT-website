<?php

namespace App\Http\Controllers\DeviceApi;

use App\Http\Controllers\Controller;
use App\Http\Resources\CrashReportResource;
use App\Services\CrashReportService;
use App\Services\SystemSettingsService;
use Illuminate\Http\Request;

class DeviceCrashReportController extends Controller
{
    public function __construct(private CrashReportService $service, private SystemSettingsService $settings) {}

    public function store(Request $request)
    {
        abort_unless($request->isJson(), 415, 'Crash reports require application/json.');
        $maximum = $this->settings->integer('crash_payload_max_kb');
        abort_if(strlen($request->getContent()) > $maximum * 1024, 413, "Crash report payload may not exceed {$maximum} KiB.");
        $data = $request->validate(['device_id' => 'prohibited', 'organization_id' => 'prohibited', 'client_report_id' => 'nullable|string|max:100', 'crash_type' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_.-]*$/'], 'reason' => 'nullable|string|max:500', 'message' => 'nullable|string|max:1000', 'firmware_version' => 'nullable|string|max:100', 'runtime_version' => 'nullable|string|max:100', 'uptime_seconds' => 'nullable|integer|min:0|max:315360000', 'reboot_reason' => 'nullable|string|max:255', 'stack_trace' => 'nullable|string|max:16000', 'context' => 'nullable|array', 'reported_at' => ['nullable', 'date', 'after_or_equal:'.now()->subYears(10)->toISOString(), 'before_or_equal:'.now()->addMinutes(5)->toISOString()]]);
        if (isset($data['context']) && strlen((string) json_encode($data['context'])) > 16384) {
            return response()->json(['message' => 'The context field may not exceed 16 KiB.', 'errors' => ['context' => ['The context field may not exceed 16 KiB.']]], 422);
        }
        [$report, $created] = $this->service->ingest($request->attributes->get('device'), $data);

        return (new CrashReportResource($report))->response()->setStatusCode($created ? 201 : 200);
    }
}
