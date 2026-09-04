<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Notifications\NotificationRuleRegistry;
use Illuminate\Validation\ValidationException;

final class NotificationMaterializationService
{
    private const FORBIDDEN_STATE = ['read_at', 'dismissed_at', 'created_at', 'updated_at'];

    public function __construct(
        private NotificationRuleRegistry $rules,
        private NotificationPreferenceService $preferences,
    ) {}

    public function createOnce(User $recipient, string $ruleKey, string $factIdentity, array $attributes, bool $explicitReminder = false): ?Notification
    {
        if (! $this->rules->known($ruleKey)) {
            throw ValidationException::withMessages(['event' => ['Unsupported Notification event.']]);
        }
        if ($recipient->status !== 'active') {
            return null;
        }
        if (! $this->preferences->enabled($recipient, $ruleKey, $explicitReminder)) {
            return null;
        }
        foreach (self::FORBIDDEN_STATE as $field) {
            if (array_key_exists($field, $attributes)) {
                throw ValidationException::withMessages(['notification' => ['Delivery cannot set Notification interaction timestamps.']]);
            }
        }
        if (isset($attributes['user_id']) && (int) $attributes['user_id'] !== (int) $recipient->id) {
            throw ValidationException::withMessages(['recipient' => ['Notification recipient identity is server controlled.']]);
        }
        if (isset($attributes['organization_id']) && (int) $attributes['organization_id'] !== (int) $recipient->organization_id) {
            throw ValidationException::withMessages(['recipient' => ['Notification Organization must match the recipient Organization.']]);
        }
        $dedupeKey = $this->dedupeKey($ruleKey, $factIdentity, $recipient);

        return Notification::query()->firstOrCreate(['deduplication_key' => $dedupeKey], [
            ...$attributes,
            'organization_id' => $attributes['organization_id'] ?? $recipient->organization_id,
            'user_id' => $recipient->id,
            'type' => $ruleKey,
            'schema_version' => 1,
            'category' => $this->rules->definition($ruleKey)['category'],
        ]);
    }

    public function dedupeKey(string $ruleKey, string $factIdentity, User $recipient): string
    {
        $factIdentity = trim($factIdentity);
        if ($factIdentity === '' || strlen($factIdentity) > 160) {
            throw ValidationException::withMessages(['fact' => ['A bounded stable Notification fact identity is required.']]);
        }

        return 'notification:v1:'.hash('sha256', implode('|', [$ruleKey, $factIdentity, (string) $recipient->id, 'in_app']));
    }
}
