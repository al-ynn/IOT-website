<?php

namespace App\Services\Analytics;

use App\Models\Device;
use App\Models\Organization;
use App\Models\TelemetryRecord;
use App\Models\User;
use App\Services\Admin\DeviceAccessService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function __construct(private DeviceAccessService $deviceAccess) {}

    public function summary(User $user, Carbon $from, Carbon $to): array
    {
        $devices = $this->deviceAccess->accessibleDevices($user);
        $telemetry = TelemetryRecord::query()->whereIn('device_id', (clone $devices)->select('id'))->whereBetween('recorded_at', [$from, $to]);

        return [
            'totalDevices' => (clone $devices)->count(),
            'onlineDevices' => (clone $devices)->where('status', 'online')->count(),
            'offlineDevices' => (clone $devices)->where('status', '!=', 'online')->count(),
            'telemetryRecords' => (clone $telemetry)->count(),
            'metrics' => $this->metricSummaries($telemetry),
            'from' => $from->toISOString(),
            'to' => $to->toISOString(),
        ];
    }

    public function device(Device $device, Carbon $from, Carbon $to): array
    {
        $telemetry = $device->telemetryRecords()->whereBetween('recorded_at', [$from, $to])->getQuery();

        return [
            'device' => ['id' => (string) $device->id, 'name' => $device->name, 'status' => $device->status],
            'telemetryRecords' => (clone $telemetry)->count(),
            'metrics' => $this->metricSummaries($telemetry),
            'from' => $from->toISOString(),
            'to' => $to->toISOString(),
        ];
    }

    public function series(Device $device, string $metric, Carbon $from, Carbon $to, string $interval, string $aggregation): array
    {
        $base = $device->telemetryRecords()->where('key', $metric)->whereBetween('recorded_at', [$from, $to])->getQuery();
        $statistics = (clone $base)->selectRaw('COUNT(*) as aggregate_count, AVG(value) as aggregate_average, MIN(value) as aggregate_minimum, MAX(value) as aggregate_maximum, SUM(value) as aggregate_sum')->first();
        $latest = (clone $base)->latest('recorded_at')->first(['value', 'unit', 'recorded_at']);
        $bucket = $this->bucketExpression($interval);
        $function = ['average' => 'AVG', 'minimum' => 'MIN', 'maximum' => 'MAX', 'sum' => 'SUM', 'count' => 'COUNT'][$aggregation];
        $points = (clone $base)->selectRaw("$bucket as bucket, $function(value) as aggregate_value")
            ->groupBy('bucket')->orderBy('bucket')->limit(1000)->get()
            ->map(fn ($point) => ['timestamp' => Carbon::parse($point->bucket, 'UTC')->toISOString(), 'value' => (float) $point->aggregate_value])->all();

        return [
            'deviceId' => (string) $device->id,
            'metric' => $metric,
            'unit' => $latest?->unit,
            'interval' => $interval,
            'aggregation' => $aggregation,
            'from' => $from->toISOString(),
            'to' => $to->toISOString(),
            'statistics' => [
                'count' => (int) ($statistics?->aggregate_count ?? 0),
                'average' => $statistics?->aggregate_average !== null ? (float) $statistics->aggregate_average : null,
                'minimum' => $statistics?->aggregate_minimum !== null ? (float) $statistics->aggregate_minimum : null,
                'maximum' => $statistics?->aggregate_maximum !== null ? (float) $statistics->aggregate_maximum : null,
                'sum' => $statistics?->aggregate_sum !== null ? (float) $statistics->aggregate_sum : null,
                'latest' => $latest ? (float) $latest->value : null,
                'latestTimestamp' => $latest?->recorded_at?->toISOString(),
            ],
            'points' => $points,
        ];
    }

    private function metricSummaries(Builder $query): array
    {
        $rows = (clone $query)->selectRaw('key, COUNT(*) as aggregate_count, AVG(value) as aggregate_average, MIN(value) as aggregate_minimum, MAX(value) as aggregate_maximum')->groupBy('key')->orderBy('key')->limit(100)->get();
        $latestIds = (clone $query)->selectRaw('MAX(id) as id')->groupBy('key')->limit(100)->pluck('id');
        $latest = TelemetryRecord::query()->whereIn('id', $latestIds)->get(['key', 'value', 'unit', 'recorded_at'])->keyBy('key');

        return $rows->map(function ($row) use ($latest) {
            $last = $latest->get($row->key);
            return ['metric' => $row->key, 'unit' => $last?->unit, 'count' => (int) $row->aggregate_count, 'average' => (float) $row->aggregate_average, 'minimum' => (float) $row->aggregate_minimum, 'maximum' => (float) $row->aggregate_maximum, 'latest' => $last ? (float) $last->value : null, 'latestTimestamp' => $last?->recorded_at?->toISOString()];
        })->all();
    }

    private function bucketExpression(string $interval): string
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') return match ($interval) {'minute' => "strftime('%Y-%m-%d %H:%M:00', recorded_at)", 'hour' => "strftime('%Y-%m-%d %H:00:00', recorded_at)", 'day' => "strftime('%Y-%m-%d 00:00:00', recorded_at)"};
        if ($driver === 'pgsql') return "date_trunc('$interval', recorded_at)";
        return match ($interval) {'minute' => "DATE_FORMAT(recorded_at, '%Y-%m-%d %H:%i:00')", 'hour' => "DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00:00')", 'day' => "DATE_FORMAT(recorded_at, '%Y-%m-%d 00:00:00')"};
    }
}
