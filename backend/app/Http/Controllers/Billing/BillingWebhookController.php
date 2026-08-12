<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Contracts\Billing\PaymentProviderInterface;
use App\Services\Billing\BillingVerificationService;

class BillingWebhookController
    extends Controller
{
    public function handle(
        Request $request,
    ): JsonResponse {
        if (!app()->bound(PaymentProviderInterface::class)) {
            return response()->json(['message'=>'No verified payment provider adapter is configured.','code'=>'BILLING_PROVIDER_NOT_CONFIGURED'],503);
        }
        try {
            $event=app(PaymentProviderInterface::class)->verify($request);
            app(BillingVerificationService::class)->handle($event);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message'=>$exception->getMessage(),'code'=>'INVALID_WEBHOOK_SIGNATURE'],400);
        }
        return response()->json(null,204);
    }
}
