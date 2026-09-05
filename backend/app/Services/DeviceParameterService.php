<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceParameter;
use App\Models\User;
use App\Services\Admin\DeviceAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeviceParameterService
{
    public function __construct(private ResourceRevisionService $revisions, private SafeResourceSaveService $saves, private ResourceLifecycleService $lifecycle, private DeviceAccessService $access) {}

    public const TYPES = ['string','integer','number','boolean','enum'];

    public function list(Device $device)
    {
        $parameters = $device->parameters()->orderBy('name')->orderBy('id')->get();
        $latest = $device->telemetryRecords()->latest('recorded_at')->get()->unique('key')->keyBy('key');
        foreach ($parameters as $parameter) {
            $record = $latest->get($parameter->key);
            $parameter->setAttribute('latest_value', $record?->typed_value ?? $record?->value);
            $parameter->setAttribute('latest_recorded_at', $record?->recorded_at);
        }
        return $parameters;
    }

    public function create(Device $device, array $data, ?User $actor = null, bool $record = true, int|string|null $baseRevisionId = null, ?string $idempotencyKey = null): DeviceParameter
    {
        if (! $record || ! $actor) {
            return DB::transaction(function () use ($device, $data) {
                if ($device->parameters()->where('key', $data['key'])->exists()) {
                    throw ValidationException::withMessages(['key' => ['This parameter key is already defined for the device.']]);
                }

return $device->parameters()->create($data);
            });
        }$this->save($actor, $device, $baseRevisionId, $idempotencyKey, $data, function (Device $locked) use ($data) {
            if ($locked->parameters()->where('key', $data['key'])->exists()) {
                throw ValidationException::withMessages(['key' => ['This parameter key is already defined for the device.']]);
            }$locked->parameters()->create($data);
        });

        return $device->parameters()->where('key', $data['key'])->firstOrFail();
    }

    public function find(Device $device, string|int $id): DeviceParameter
    {
        return $device->parameters()->findOrFail($id);
    }

    public function update(DeviceParameter $parameter, array $data, User $actor, int|string|null $baseRevisionId = null, ?string $idempotencyKey = null): DeviceParameter
    {
        $id = $parameter->id;
        $this->save($actor, $parameter->device, $baseRevisionId, $idempotencyKey, ['parameter_id' => $id, ...$data], fn (Device $locked) => $locked->parameters()->findOrFail($id)->update($data));

        return $parameter->newQuery()->findOrFail($id);
    }

    public function delete(DeviceParameter $parameter, User $actor, int|string|null $baseRevisionId = null, ?string $idempotencyKey = null): void
    {
        $id = $parameter->id;
        $this->save($actor, $parameter->device, $baseRevisionId, $idempotencyKey, ['parameter_id' => $id, 'delete' => true], fn (Device $locked) => $locked->parameters()->findOrFail($id)->delete());
    }

    private function save(User $actor, Device $device, int|string|null $baseRevisionId, ?string $idempotencyKey, array $command, callable $mutate): Device
    {
        return $this->saves->execute($actor, 'device', Device::class, $device->id, $baseRevisionId, fn (User $user, Device $locked) => abort_unless($this->access->hasFullDeviceAccess($user, $locked), 404), fn (Device $locked) => $this->lifecycle->assertActive('device', $locked->id, 'Disabled or Archived Devices are read-only.'), function (Device $locked) use ($mutate) {
            $mutate($locked);

            return $locked;
        }, fn (Device $locked, User $user) => $this->revisions->recordDevice($locked, $user, 'Parameters updated'), $baseRevisionId !== null, $idempotencyKey, $command);
    }

    public function createRules(): array
    {
        return ['name'=>['required','string','max:100'],'key'=>['required','string','max:100','regex:/^[a-z][a-z0-9_]*$/'],'data_type'=>['required','in:'.implode(',',self::TYPES)],'unit'=>['nullable','string','max:30'],'description'=>['nullable','string','max:500'],'semantic'=>['nullable','in:temperature,humidity,pressure,voltage,current,power,battery,motion,state,signal,level'],'configuration'=>['nullable','array'],'configuration.min'=>['nullable','numeric'],'configuration.max'=>['nullable','numeric'],'configuration.precision'=>['nullable','integer','between:0,6'],'configuration.step'=>['nullable','numeric','gt:0'],'configuration.maxLength'=>['nullable','integer','between:1,10000'],'configuration.default'=>['nullable'],'configuration.trueLabel'=>['nullable','string','max:50'],'configuration.falseLabel'=>['nullable','string','max:50'],'configuration.options'=>['nullable','array','max:50'],'configuration.options.*'=>['string','max:100','distinct'],'baseRevisionId'=>['nullable','integer']];
    }

    public function updateRules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'max:100'], 'unit' => ['sometimes', 'nullable', 'string', 'max:30'], 'description' => ['sometimes', 'nullable', 'string', 'max:500'], 'baseRevisionId' => ['nullable', 'integer']];
    }

    public function allowedCreate(array $data): array
    {
        $allowed = array_intersect_key($data, array_flip(['name','key','data_type','unit','description','semantic','configuration']));
        $c = $allowed['configuration'] ?? [];
        $type = $allowed['data_type'] ?? null;
        if (in_array($type, ['integer','number'], true)) {
            $c = array_intersect_key($c, ['min'=>true,'max'=>true,'precision'=>true,'step'=>true,'default'=>true]);
            if (isset($c['min'], $c['max']) && $c['min'] > $c['max']) throw ValidationException::withMessages(['configuration.min'=>['Minimum must not exceed maximum.']]);
            if (isset($c['default']) && (! is_numeric($c['default']) || (isset($c['min']) && $c['default'] < $c['min']) || (isset($c['max']) && $c['default'] > $c['max']))) throw ValidationException::withMessages(['configuration.default'=>['Default must be numeric and within the configured range.']]);
        } elseif ($type === 'string') {
            $c = array_intersect_key($c, ['maxLength'=>true,'default'=>true]);
            if (isset($c['default'], $c['maxLength']) && mb_strlen((string) $c['default']) > $c['maxLength']) throw ValidationException::withMessages(['configuration.default'=>['Default exceeds maximum length.']]);
        } elseif ($type === 'boolean') {
            $c = array_intersect_key($c, ['default'=>true,'trueLabel'=>true,'falseLabel'=>true]);
            if (isset($c['default']) && ! is_bool($c['default'])) throw ValidationException::withMessages(['configuration.default'=>['Boolean default must be true or false.']]);
        } elseif ($type === 'enum') {
            $c = array_intersect_key($c, ['options'=>true,'default'=>true]);
            if (empty($c['options'])) throw ValidationException::withMessages(['configuration.options'=>['At least one Enum value is required.']]);
            if (isset($c['default']) && ! in_array($c['default'], $c['options'], true)) throw ValidationException::withMessages(['configuration.default'=>['Default must be an allowed Enum value.']]);
        } else {
            $c = [];
        }
        $allowed['configuration'] = $c;
        return $allowed;
    }

    public function allowedUpdate(array $data): array
    {
        return array_intersect_key($data,array_flip(['name', 'unit', 'description']));
    }
}
