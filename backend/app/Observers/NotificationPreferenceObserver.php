<?php
namespace App\Observers;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\NotificationRuleRegistry;
use App\Services\NotificationPreferenceService;
final class NotificationPreferenceObserver
{
    public function creating(Notification $notification): bool
    {
        if (!app(NotificationRuleRegistry::class)->known((string) $notification->type)) return true;
        $user = User::find($notification->user_id); if (!$user) return false;
        return app(NotificationPreferenceService::class)->enabled($user, (string) $notification->type, (bool) data_get($notification->data, 'reminder', false));
    }
}
