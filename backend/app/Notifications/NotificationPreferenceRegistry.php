<?php
namespace App\Notifications;
use Illuminate\Validation\ValidationException;
final class NotificationPreferenceRegistry
{
    public const CATEGORIES = ['updates', 'requests', 'approvals', 'mentions', 'system'];
    private const MANDATORY = ['access.accepted','access.declined','access.cancelled','access.granted','access.rejected','device.access_granted','device.access_upgraded','device.access_downgraded','device.access_revoked','template.publication.changes_requested','template.publication.rejected','report.publication.changes_requested','report.publication.rejected','automation.publication.changes_requested','automation.publication.rejected'];
    public function __construct(private NotificationRuleRegistry $rules) {}
    public function rules(): array { return array_map(fn (string $key) => $this->rule($key), $this->rules->keys()); }
    public function rule(string $key): array
    {
        $rule = $this->rules->definition($key);
        return $rule + ['label' => str_replace(['.', '_'], ' ', ucfirst($key)), 'description' => 'In-app delivery for this Notification event.', 'mandatory' => in_array($key, self::MANDATORY, true), 'defaultEnabled' => true, 'channels' => ['in_app']];
    }
    public function assertCategory(string $key): void { if (!in_array($key, self::CATEGORIES, true)) throw ValidationException::withMessages(['categories' => ["Unsupported Notification category: {$key}."]]); }
}
