<?php

namespace App\Contracts;

use App\Models\ReportRun;

interface ReportGenerator
{
    public function type(): string;

    public function headers(): array;

    public function rows(ReportRun $run): iterable;
}
