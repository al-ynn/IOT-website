<?php

return [
    // A provider name alone does not enable webhooks; an adapter must also bind PaymentProviderInterface.
    'provider' => env('BILLING_PROVIDER'),
];
