<?php
namespace App\Services;
use App\Models\User;
use App\Notifications\NotificationPreferenceRegistry;
use Illuminate\Validation\ValidationException;
final class NotificationPreferenceService
{
    public function __construct(private NotificationPreferenceRegistry $registry) {}
    public function payload(User $user): array
    {
        $stored = $this->stored($user); $categories = $stored['categories'] ?? []; $rules = $stored['rules'] ?? [];
        return ['scope' => 'account', 'channels' => [['key' => 'in_app', 'label' => 'In-app', 'enabled' => true, 'mutable' => false]],
            'categories' => array_map(fn ($key) => ['key' => $key, 'label' => ucfirst($key), 'override' => $categories[$key] ?? null, 'hasMandatory' => collect($this->registry->rules())->contains(fn ($r) => $r['category'] === $key && $r['mandatory'])], NotificationPreferenceRegistry::CATEGORIES),
            'rules' => array_map(fn ($r) => ['key' => $r['key'], 'category' => $r['category'], 'label' => $r['label'], 'description' => $r['description'], 'mandatory' => $r['mandatory'], 'mandatoryReason' => $r['mandatory'] ? 'Required security or governance delivery.' : null, 'defaultEnabled' => true, 'override' => $rules[$r['key']] ?? null, 'effectiveEnabled' => $this->effective($r, $rules, $categories), 'channels' => ['in_app']], $this->registry->rules())];
    }
    public function replace(User $user, array $input): array
    {
        $categories = []; foreach (($input['categories'] ?? []) as $key => $value) { $this->registry->assertCategory($key); $categories[$key] = (bool) $value; }
        $rules = []; foreach (($input['rules'] ?? []) as $key => $value) { $rule = $this->registry->rule($key); if ($rule['mandatory'] && !$value) throw ValidationException::withMessages(["rules.{$key}" => ['Mandatory Notifications cannot be disabled.']]); if (!$rule['mandatory']) $rules[$key] = (bool) $value; }
        $user->update(['notification_settings' => ['schemaVersion' => 1, 'categories' => $categories, 'rules' => $rules]]); return $this->payload($user->refresh());
    }
    public function reset(User $user): array { $user->update(['notification_settings' => null]); return $this->payload($user->refresh()); }
    public function enabled(User $user, string $eventKey, bool $explicitReminder = false): bool
    {
        $rule = $this->registry->rule($eventKey); if ($rule['mandatory'] || $explicitReminder) return true; $stored = $this->stored($user);
        if (array_key_exists($eventKey, $stored['rules'] ?? [])) return (bool) $stored['rules'][$eventKey];
        if (array_key_exists($rule['category'], $stored['categories'] ?? [])) return (bool) $stored['categories'][$rule['category']]; return true;
    }
    private function stored(User $user): array { $value = $user->notification_settings; return is_array($value) && ($value['schemaVersion'] ?? null) === 1 ? $value : []; }
    private function effective(array $rule, array $rules, array $categories): bool { if ($rule['mandatory']) return true; if (array_key_exists($rule['key'], $rules)) return (bool) $rules[$rule['key']]; if (array_key_exists($rule['category'], $categories)) return (bool) $categories[$rule['category']]; return true; }
}
