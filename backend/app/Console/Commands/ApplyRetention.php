<?php

namespace App\Console\Commands;

use App\Services\RetentionService;
use Illuminate\Console\Command;

class ApplyRetention extends Command
{
    protected $signature = 'platform:apply-retention';

    protected $description = 'Apply registered technical data and artifact retention policies';

    public function handle(RetentionService $retention): int
    {
        foreach ($retention->apply() as $domain => $count) {
            $this->line("{$domain}: {$count}");
        }

        return self::SUCCESS;
    }
}
