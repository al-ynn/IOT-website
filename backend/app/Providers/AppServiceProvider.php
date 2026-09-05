<?php

namespace App\Providers;

use App\Contracts\FirmwareDeliveryDriver;
use App\Contracts\DnsResolver;
use App\Services\UnavailableFirmwareDeliveryDriver;
use App\Services\SystemDnsResolver;
use App\Models\OperationalEvent;
use App\Models\Notification;
use App\Observers\NotificationPreferenceObserver;
use App\Services\WebhookEventRouter;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FirmwareDeliveryDriver::class, UnavailableFirmwareDeliveryDriver::class);
        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Notification::observe(NotificationPreferenceObserver::class);
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(hash('sha256', strtolower((string) $request->input('email')).'|'.$request->ip())));
        RateLimiter::for('device-api',fn(Request $request)=>Limit::perMinute(120)->by(hash('sha256',(string)$request->bearerToken().'|'.$request->ip())));
        OperationalEvent::created(function (OperationalEvent $event): void {
            try {
                app(WebhookEventRouter::class)->routeOperationalEvent($event);
            } catch (\Throwable $error) {
                Log::warning('Webhook routing failed without affecting the source operation.', ['event_id' => $event->id, 'exception' => get_class($error)]);
            }
        });
    }
}
