<?php

namespace App\Exceptions;

use RuntimeException;

class FeatureNotAvailableException extends RuntimeException
{
    public function __construct(
        string $feature
    ) {
        parent::__construct(
            "Feature [{$feature}] is not available on the current plan."
        );
    }
}