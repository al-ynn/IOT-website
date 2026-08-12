<?php

namespace App\Services\Billing;

use App\Exceptions\FeatureNotAvailableException;
use App\Models\Organization;

class BillingEntitlementService
{
    public function hasFeature(
        Organization $organization,
        string $feature
    ): bool {

        $subscription =
            $organization
                ->subscription()
                ->with('plan')
                ->first();

        if (!$subscription) {
            return false;
        }

        if (!in_array(
            $subscription->status,
            [
                'active',
                'trialing'
            ],
            true
        )) {
            return false;
        }

        if ($subscription->plan?->interval !== 'lifetime'
            && $subscription->current_period_end
            && $subscription->current_period_end->isPast()) {
            return false;
        }

        $plan = $subscription->plan;

        if (!$plan) {
            return false;
        }

        return in_array(
            $feature,
            $plan->features ?? [],
            true
        );
    }


    public function requireFeature(
        Organization $organization,
        string $feature
    ): void {

        if (!$this->hasFeature(
            $organization,
            $feature
        )) {

            throw new FeatureNotAvailableException(
                $feature
            );
        }
    }
}
