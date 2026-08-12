<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingUsageController extends Controller
{
    public function __invoke(
        Request $request
    ): JsonResponse {

        $organization =
            $request->user()
                ->organization;

        abort_unless($organization, 403, 'Organization is required.');

        return response()->json([

            'devices' =>
                $organization
                    ->devices()
                    ->count(),

            'users' =>
                $organization
                    ->users()
                    ->count(),

            'dashboards' =>
                $organization
                    ->dashboards()
                    ->count(),

            'automations' => $organization->automations()->count(),

        ]);

    }
}
