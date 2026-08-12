<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DevelopmentUserSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            return;
        }

        DB::transaction(function (): void {
            $freePlan = Plan::query()->where('id', 'free')->where('active', true)->firstOrFail();
            $organization = Organization::updateOrCreate(
                ['slug' => 'iot-platform-demo'],
                ['name' => 'IoT Platform Demo Organization', 'status' => 'active']
            );

            User::updateOrCreate(
                ['email' => 'customer@iot-platform.test'],
                [
                    'name' => 'IoT Platform Customer',
                    'password' => Hash::make('Password123!'),
                    'organization_id' => $organization->id,
                    'role' => 'owner',
                    'platform_role' => null,
                    'status' => 'active',
                ]
            );

            User::updateOrCreate(
                ['email' => 'admin@iot-platform.test'],
                [
                    'name' => 'IoT Platform Admin',
                    'password' => Hash::make('Admin123!'),
                    'organization_id' => null,
                    'role' => 'viewer',
                    'platform_role' => 'platform_admin',
                    'status' => 'active',
                ]
            );

            $organization->subscriptions()->updateOrCreate(
                ['organization_id' => $organization->id],
                [
                    'plan_id' => $freePlan->id,
                    'status' => 'active',
                    'provider_transaction_id' => null,
                    'cancel_at_period_end' => false,
                    'current_period_start' => now(),
                    'current_period_end' => null,
                ]
            );
        });
    }
}
