<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\Analytics\AnalyticsService;
use App\Services\Billing\BillingEntitlementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsService $analytics, private BillingEntitlementService $billing) {}

    public function summary(Request $request)
    {
        $organization = $this->access($request);
        [$from, $to] = $this->range($request);
        return response()->json($this->analytics->summary($organization, $from, $to));
    }

    public function device(Request $request, string $device)
    {
        $organization = $this->access($request);
        $ownedDevice = $organization->devices()->findOrFail($device);
        [$from, $to] = $this->range($request);
        return response()->json($this->analytics->device($ownedDevice, $from, $to));
    }

    public function telemetry(Request $request, string $metric)
    {
        $organization = $this->access($request);
        $data = $request->validate(['deviceId' => 'required|integer', 'range' => 'sometimes|in:1h,6h,12h,24h,7d,30d,custom', 'from' => 'required_if:range,custom|date', 'to' => 'required_if:range,custom|date', 'interval' => 'sometimes|in:minute,hour,day', 'aggregation' => 'sometimes|in:average,minimum,maximum,sum,count']);
        if (!preg_match('/^[A-Za-z0-9_.:-]{1,100}$/', $metric)) throw ValidationException::withMessages(['metric' => 'The metric format is invalid.']);
        $device = $organization->devices()->findOrFail($data['deviceId']);
        if (!$device->telemetryRecords()->where('key', $metric)->exists()) throw ValidationException::withMessages(['metric' => 'The selected metric does not exist for this device.']);
        [$from, $to] = $this->range($request);
        return response()->json($this->analytics->series($device, $metric, $from, $to, $data['interval'] ?? 'hour', $data['aggregation'] ?? 'average'));
    }

    private function access(Request $request): Organization
    {
        $organization = $request->user()?->organization;
        abort_unless($organization && $request->user()->hasOrganizationPermission('analytics.view'), 403);
        $this->billing->requireFeature($organization, 'analytics.advanced');
        return $organization;
    }

    private function range(Request $request): array
    {
        $data = $request->validate(['range' => 'sometimes|in:1h,6h,12h,24h,7d,30d,custom', 'from' => 'required_if:range,custom|date', 'to' => 'required_if:range,custom|date']);
        $to = isset($data['to']) ? Carbon::parse($data['to'])->utc() : now()->utc();
        $range = $data['range'] ?? '24h';
        $from = $range === 'custom' ? Carbon::parse($data['from'])->utc() : $to->copy()->sub(['1h' => '1 hour', '6h' => '6 hours', '12h' => '12 hours', '24h' => '24 hours', '7d' => '7 days', '30d' => '30 days'][$range]);
        if (!$from->isBefore($to)) throw ValidationException::withMessages(['from' => 'The start time must be before the end time.']);
        if ($from->diffInDays($to) > 30) throw ValidationException::withMessages(['from' => 'The analytics range may not exceed 30 days.']);
        return [$from, $to];
    }
}
