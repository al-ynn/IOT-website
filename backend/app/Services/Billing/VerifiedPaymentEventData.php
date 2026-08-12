<?php

namespace App\Services\Billing;

class VerifiedPaymentEventData
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly ?string $transactionId,
        public readonly ?string $organizationId,
        public readonly ?string $planId,
        public readonly bool $verified,
        public readonly ?float $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $status = null,
        public readonly array $payload = [],
    ) {}
}
