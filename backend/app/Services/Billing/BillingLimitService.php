<?php

namespace App\Services\Billing;

use App\Models\Organization;
use App\Exceptions\BillingLimitExceededException;

class BillingLimitService
{
    public function ensureDeviceAvailable(
        Organization $organization,
        ?int $limit = null
    ): void {

        $current =
            $organization
                ->devices()
                ->count();

        $limit ??= $this->limit($organization, 'device_limit');
        if ($limit >= 0 && $current >= $limit) {
            throw new BillingLimitExceededException('devices', $limit, $current);

        }

    }


    public function ensureUserAvailable(
        Organization $organization,
        ?int $limit = null
    ): void {

        $current =
            $organization
                ->users()
                ->count();

        $limit ??= $this->limit($organization, 'user_limit');
        if ($limit >= 0 && $current >= $limit) {
            throw new BillingLimitExceededException('users', $limit, $current);

        }

    }


    public function ensureDashboardAvailable(
        Organization $organization,
        ?int $limit = null
    ): void {

        $current =
            $organization
                ->dashboards()
                ->count();

        $limit ??= $this->limit($organization, 'dashboard_limit');
        if ($limit >= 0 && $current >= $limit) {
            throw new BillingLimitExceededException('dashboards', $limit, $current);

        }

    }

    public function ensureAutomationAvailable(Organization $organization, ?int $limit = null): void
    {
        $current=$organization->automations()->count();$limit??=$this->limit($organization,'automation_limit');
        if($limit>=0&&$current>=$limit)throw new BillingLimitExceededException('automations',$limit,$current);
    }

    private function limit(Organization $organization, string $column): int
    {
        return (int) ($organization->subscription()->with('plan')->first()?->plan?->{$column} ?? 0);
    }
}
