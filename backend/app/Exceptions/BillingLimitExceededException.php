<?php

namespace App\Exceptions;

use RuntimeException;

class BillingLimitExceededException extends RuntimeException
{
    public function __construct(public readonly string $resource, public readonly int $limit, public readonly int $current)
    {
        parent::__construct(ucfirst($resource).' limit reached.');
    }
}
