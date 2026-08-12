<?php

namespace App\Contracts\Billing;

use App\Services\Billing\VerifiedPaymentEventData;
use Illuminate\Http\Request;

interface PaymentProviderInterface
{
    /** Verify signature/authenticity and map provider identifiers to trusted local IDs. */
    public function verify(Request $request): VerifiedPaymentEventData;
}
