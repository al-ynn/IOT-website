<?php

namespace App\Services;

use App\Models\Report;
use App\Models\User;
use App\Services\Admin\DeviceAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportConfigurationService
{
    public function __construct(private DeviceAccessService $access, private SystemSettingsService $settings) {}

    public function validate(User $user, string $type, array $config, bool $resolve = false): array
    {
        abort_unless(in_array($type, Report::TYPES, true), 422, 'Unsupported report type.');
        $allowed = ['device_summary' => ['device_ids'], 'device_telemetry' => ['device_ids', 'metric_keys', 'date_range_mode', 'relative_range', 'from', 'to'], 'operational_events' => ['device_ids', 'severity', 'source', 'date_range_mode', 'relative_range', 'from', 'to']][$type];
        $unknown = array_diff(array_keys($config), $allowed);
        if ($unknown) {
            throw ValidationException::withMessages(['configuration' => ['Unsupported configuration fields: '.implode(', ', $unknown).'.']]);
        }
        $rules = ['device_ids' => 'required|array|min:1|max:50', 'device_ids.*' => 'required|integer|distinct'];
        if ($type === 'device_telemetry') {
            $rules += ['metric_keys' => 'nullable|array|max:25', 'metric_keys.*' => ['string', 'distinct', 'max:100', 'regex:/^[A-Za-z0-9_.:-]+$/']];
        }
        if ($type === 'operational_events') {
            $rules += ['severity' => ['nullable', Rule::in(['info', 'warning', 'error'])], 'source' => ['nullable', Rule::in(['automation', 'provisioning', 'firmware', 'webhook', 'device'])]];
        }
        if ($type !== 'device_summary') {
            $rules += ['date_range_mode' => ['required', Rule::in(['relative', 'absolute'])], 'relative_range' => ['nullable', Rule::in(['last_24_hours', 'last_7_days', 'last_30_days'])], 'from' => 'nullable|date', 'to' => 'nullable|date|after:from'];
        }
        $clean = Validator::make($config, $rules)->validate();
        $ids = array_values(array_unique(array_map('intval', $clean['device_ids'])));
        $visible = $this->access->accessibleDevices($user)->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($visible) !== count($ids)) {
            throw ValidationException::withMessages(['configuration.device_ids' => ['One or more Devices are unavailable.']]);
        }
        $clean['device_ids'] = $ids;
        if ($type !== 'device_summary') {
            [$from, $to] = $this->dates($clean);
            if ($resolve) {
                $clean['resolved_from'] = $from->toISOString();
                $clean['resolved_to'] = $to->toISOString();
            }
        }

        return $clean;
    }

    public function assertCurrentAccess(User $user, array $configuration): void
    {
        $ids = array_values(array_unique(array_map('intval', $configuration['device_ids'] ?? [])));
        $visible = $this->access->accessibleDevices($user)->whereIn('id', $ids)->count();
        if ($visible !== count($ids)) {
            throw ValidationException::withMessages(['configuration.device_ids' => ['One or more Devices are no longer available.']]);
        }
    }

    private function dates(array $config): array
    {
        $to = CarbonImmutable::now('UTC');
        if ($config['date_range_mode'] === 'relative') {
            $from = match ($config['relative_range'] ?? null) {
                'last_24_hours' => $to->subDay(),'last_7_days' => $to->subDays(7),'last_30_days' => $to->subDays(30),default => throw ValidationException::withMessages(['configuration.relative_range' => ['A supported relative range is required.']])
            };
        } else {
            if (empty($config['from']) || empty($config['to'])) {
                throw ValidationException::withMessages(['configuration' => ['Absolute reports require from and to.']]);
            }
            $from = CarbonImmutable::parse($config['from'])->utc();
            $to = CarbonImmutable::parse($config['to'])->utc();
            if ($to->isFuture()) {
                throw ValidationException::withMessages(['configuration.to' => ['The report end must not be in the future.']]);
            }
        }
        $maximum = $this->settings->integer('report_max_date_range_days');
        if ($from->diffInDays($to) > $maximum) {
            throw ValidationException::withMessages(['configuration' => ["Report ranges may not exceed {$maximum} days."]]);
        }

        return [$from, $to];
    }
}
