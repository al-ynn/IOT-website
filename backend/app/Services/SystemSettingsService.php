<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class SystemSettingsService
{
    public function __construct(private SystemSettingDefinitionRegistry $registry) {}

    public function integer(string $key): int
    {
        return (int) $this->value($key);
    }

    public function value(string $key): mixed
    {
        $definition = $this->definition($key);

        return Cache::rememberForever($this->cacheKey($key), fn () => SystemSetting::query()->where('key', $key)->value('value') ?? $definition['default']);
    }

    public function categories(): array
    {
        $overrides = SystemSetting::query()->get()->keyBy('key');
        $categories = [];
        foreach ($this->registry->all() as $key => $definition) {
            $override = $overrides->get($key);
            $categories[$definition['category']][] = ['key' => $key, ...$definition, 'value' => $override?->value ?? $definition['default'], 'is_overridden' => (bool) $override, 'updated_at' => $override?->updated_at?->toISOString()];
        }

        return collect($categories)->map(fn ($settings, $name) => compact('name', 'settings'))->values()->all();
    }

    public function update(string $key, mixed $value, User $actor): array
    {
        $definition = $this->definition($key);
        if (! is_int($value)) {
            throw ValidationException::withMessages(['value' => ['The value must be an integer.']]);
        }
        if ($value < $definition['min'] || $value > $definition['max']) {
            throw ValidationException::withMessages(['value' => ["The value must be between {$definition['min']} and {$definition['max']}."]]);
        }
        $this->assertRelatedBounds($key, $value);
        SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $actor->id]);
        Cache::forget($this->cacheKey($key));

        return ['key' => $key, ...$definition, 'value' => $value, 'is_overridden' => true];
    }

    public function reset(string $key): array
    {
        $definition = $this->definition($key);
        SystemSetting::query()->where('key', $key)->delete();
        Cache::forget($this->cacheKey($key));

        return ['key' => $key, ...$definition, 'value' => $definition['default'], 'is_overridden' => false];
    }

    private function definition(string $key): array
    {
        return $this->registry->get($key) ?? abort(404, 'System setting is not registered.');
    }

    private function assertRelatedBounds(string $key, int $value): void
    {
        if ($key === 'webhook_request_timeout_seconds' && $value < $this->integer('webhook_connect_timeout_seconds')) {
            throw ValidationException::withMessages(['value' => ['Request timeout cannot be shorter than the connection timeout.']]);
        }
        if ($key === 'webhook_connect_timeout_seconds' && $value > $this->integer('webhook_request_timeout_seconds')) {
            throw ValidationException::withMessages(['value' => ['Connection timeout cannot exceed the request timeout.']]);
        }
    }

    private function cacheKey(string $key): string
    {
        return 'system-setting:'.$key;
    }
}
