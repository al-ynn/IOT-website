<?php

namespace App\Console\Commands;

use App\Services\ProvisioningSessionService;
use Illuminate\Console\Command;

class ExpireProvisioningSessions extends Command
{
    protected $signature = 'provisioning:expire';
    protected $description = 'Expire pending provisioning sessions whose technical lifetime elapsed';
    public function handle(ProvisioningSessionService $service): int { $this->info($service->expireDue().' provisioning session(s) expired.'); return self::SUCCESS; }
}
