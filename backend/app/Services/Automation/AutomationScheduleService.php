<?php

namespace App\Services\Automation;

use App\Models\AutomationSchedule;
use Carbon\Carbon;

class AutomationScheduleService
{
    public function nextRun(array $schedule, mixed $from = null): ?Carbon
    {
        if (!($schedule['enabled'] ?? true)) {
            return null;
        }

        $now = $from ? Carbon::parse($from) : now();

        return match ($schedule['type']) {
            'interval' => $now->copy()->addMinutes($schedule['intervalMinutes']),
            'daily' => $this->nextDaily($now, $schedule['time']),
            'weekly' => $this->nextWeekly($now, $schedule['day'], $schedule['time']),
            'once' => isset($schedule['at']) ? Carbon::parse($schedule['at']) : null,
            default => null,
        };
    }

    public function nextFor(AutomationSchedule $schedule, mixed $from = null): ?Carbon
    {
        return $this->nextRun([
            'type' => $schedule->type,
            'enabled' => $schedule->enabled,
            ...$schedule->configuration,
        ], $from);
    }

    private function nextDaily(Carbon $now, string $time): Carbon
    {
        $next = $now->copy()->setTimeFromTimeString($time);

        return $next->isAfter($now) ? $next : $next->addDay();
    }

    private function nextWeekly(Carbon $now, string $day, string $time): Carbon
    {
        $next = $now->copy()->nextOrSame($day)->setTimeFromTimeString($time);

        return $next->isAfter($now) ? $next : $next->addWeek();
    }
}
