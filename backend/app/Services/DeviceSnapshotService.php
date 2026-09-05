<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceSnapshot;
use App\Models\User;

class DeviceSnapshotService
{
    public function capture(Device $device, User $actor, array $data): DeviceSnapshot
    {
        $device->loadMissing(['template:id,name,device_type,protocol', 'parameters:id,device_id,name,key,data_type,unit,description', 'canonicalLocation:id,name']);
        $payload = [
            'device' => [
                'name' => $device->name,
                'identifier' => $device->external_id,
                'status' => $device->status,
                'type' => $device->type,
                'protocol' => $device->protocol,
                'location' => $device->canonicalLocation ? ['id'=>(string)$device->canonicalLocation->id,'name'=>$device->canonicalLocation->name] : null,
                'reported_firmware_version' => $device->firmware_version,
            ],
            'template' => $device->template ? [
                'id' => (string) $device->template->id,
                'name' => $device->template->name,
                'device_type' => $device->template->device_type,
                'protocol' => $device->template->protocol,
            ] : null,
            'parameters' => $device->parameters->sortBy('key')->values()->map(fn ($parameter) => [
                'name' => $parameter->name,
                'key' => $parameter->key,
                'data_type' => $parameter->data_type,
                'unit' => $parameter->unit,
                'description' => $parameter->description,
            ])->all(),
        ];

        return DeviceSnapshot::create([
            'organization_id' => $device->organization_id,
            'device_id' => $device->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'payload' => $payload,
            'captured_at' => now('UTC'),
            'created_by' => $actor->id,
        ])->load('creator:id,name');
    }

    public function compare(DeviceSnapshot $from, DeviceSnapshot $to): array
    {
        $left = $this->flatten($from->payload);
        $right = $this->flatten($to->payload);
        $keys = collect(array_keys($left))->merge(array_keys($right))->unique()->sort()->values();

        return $keys->filter(fn ($key) => ($left[$key] ?? null) !== ($right[$key] ?? null))->map(fn ($key) => ['path' => $key, 'before' => $left[$key] ?? null, 'after' => $right[$key] ?? null])->values()->all();
    }

    private function flatten(array $value, string $prefix = ''): array
    {
        $result = [];
        foreach ($value as $key => $item) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($item)) {
                $result += $this->flatten($item, $path);
            } else {
                $result[$path] = $item;
            }
        }

        return $result;
    }
}
