<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Subscription;

class AdminBillingController
    extends Controller
{
    public function organizations(
        Request $request
    ): JsonResponse {

        /*
         * IMPORTANT:
         *
         * This endpoint must only be accessible
         * to platform administrators.
         */

        $organizations = Organization::query()

            ->with([
                'subscription.plan'
            ])

            ->withCount([
                'devices',
                'users',
                'dashboards'
            ])

            ->get()

            ->map(function ($organization) {

                return [

                    'id' =>
                        $organization->id,

                    'name' =>
                        $organization->name,

                    'planId' =>
                        $organization
                            ->subscription
                            ?->plan
                            ?->id,

                    'planName' =>
                        $organization
                            ->subscription
                            ?->plan
                            ?->name
                            ?? 'Free',

                    'subscriptionStatus' =>
                        $organization
                            ->subscription
                            ?->status
                            ?? 'expired',

                    'devices' =>
                        $organization
                            ->devices_count,

                    'users' =>
                        $organization
                            ->users_count,

                    'dashboards' =>
                        $organization
                            ->dashboards_count,

                    'createdAt' =>
                        $organization
                            ->created_at
                            ?->toISOString(),

                ];

            });

        return response()->json(
            $organizations
        );
    }


    public function stats(
        Request $request
    ): JsonResponse {

        $total =
            Organization::count();


        $active =
            Organization::whereHas(

                'subscription',

                function ($query) {

                    $query->where(
                        'status',
                        'active'
                    );

                }

            )->count();


        $pastDue =
            Organization::whereHas(

                'subscription',

                function ($query) {

                    $query->where(
                        'status',
                        'past_due'
                    );

                }

            )->count();


        $lifetime =
            Organization::whereHas(

                'subscription.plan',

                function ($query) {

                    $query->where(
                        'interval',
                        'lifetime'
                    );

                }

            )->count();


        return response()->json([

            'totalOrganizations' =>
                $total,

            'activeSubscriptions' =>
                $active,

            'cancelledSubscriptions' =>
                Organization::whereHas(

                    'subscription',

                    function ($query) {

                        $query->where(
                            'status',
                            'cancelled'
                        );

                    }

                )->count(),

            'pastDueSubscriptions' =>
                $pastDue,

            'lifetimeSubscriptions' =>
                $lifetime,

            'monthlyRecurringRevenue' =>
                (float) Subscription::query()->whereIn('status',['active','trialing'])->whereHas('plan',fn($q)=>$q->whereIn('interval',['monthly','yearly']))->with('plan')->get()->sum(fn($s)=>$s->plan->interval === 'yearly' ? ((float)$s->plan->price)/12 : (float)$s->plan->price),

        ]);

    }
}
