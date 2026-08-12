<?php

namespace App\Services\Billing;

use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentEvent as PaymentEventModel;

class BillingVerificationService
{
    public function handle(VerifiedPaymentEventData $event): void
{
    if (!$event->verified || $event->eventId === '') {
        throw new \InvalidArgumentException('Only verified provider events may be processed.');
    }
    DB::transaction(function () use ($event) {

        $persisted = PaymentEventModel::firstOrCreate(['event_id'=>$event->eventId],[
            'event_type'=>$event->eventType,'transaction_id'=>$event->transactionId,'organization_id'=>$event->organizationId,
            'plan_id'=>$event->planId,'amount'=>$event->amount,'currency'=>$event->currency,'status'=>$event->status??'received','payload'=>$event->payload,
        ]);

        if (!$persisted->wasRecentlyCreated) {
            return;
        }

        $organization =
            $this->findOrganization(
                $event
            );

        if (!$organization) {

            throw new \RuntimeException(
                'Organization could not be resolved.'
            );
        }

        match ($event->eventType) {
            'payment.succeeded', 'subscription.activated', 'subscription.renewed' => $this->activateSubscription($organization, $event),
            'subscription.cancelled' => $this->updateStatus($organization, 'cancelled'),
            'payment.failed' => $this->updateStatus($organization, 'past_due'),
            'payment.refunded', 'subscription.expired' => $this->updateStatus($organization, 'expired'),
            default => null,
        };

        PaymentEventModel::where('event_id',$event->eventId)->update(['processed_at'=>now()]);
    });
}


    protected function findOrganization(
        VerifiedPaymentEventData $event
    ): ?Organization {

        if (!$event->organizationId) {
            return null;
        }

        return Organization::find(
            $event->organizationId
        );
    }


    protected function activateSubscription(
        Organization $organization,
        VerifiedPaymentEventData $event
    ): void {

        $plan=$event->planId ? \App\Models\Plan::find($event->planId) : null;
        if (!$plan) {
            throw new \RuntimeException('Verified event references an invalid plan.');
        }
        if ($event->status !== 'paid' || $event->amount === null || abs($event->amount-(float)$plan->price)>0.001 || strtoupper((string)$event->currency)!==strtoupper($plan->currency)) {
            throw new \RuntimeException('Verified payment details do not match the selected plan.');
        }
        $periodEnd=match($plan->interval){'monthly'=>now()->addMonth(),'yearly'=>now()->addYear(),'lifetime'=>null};

        Subscription::updateOrCreate(

            [
                'organization_id' =>
                    $organization->id,
            ],

            [
                'plan_id' =>
                    $event->planId,

                'status' =>
                    'active',

                'provider_transaction_id' =>
                    $event->transactionId,

                'cancel_at_period_end' =>
                    false,
                'current_period_start'=>now(),
                'current_period_end'=>$periodEnd,
            ]
        );
    }

    protected function updateStatus(Organization $organization, string $status): void
    {
        $organization->subscription()->latest()->first()?->update(['status'=>$status]);
    }
}
