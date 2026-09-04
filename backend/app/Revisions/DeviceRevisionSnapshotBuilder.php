<?php

namespace App\Revisions;

use App\Models\Device;

final class DeviceRevisionSnapshotBuilder
{
    public const SCHEMA_VERSION = 1;

    public function build(Device $device): array
    {
        $device->loadMissing(['dashboard.widgets', 'parameters', 'canonicalLocation:id,name']);
        $dashboard = $device->dashboard;
        return $this->normalize([
            'metadata' => [
                'name' => $device->name,
                'type' => $device->type,
                'protocol' => $device->protocol,
                'location' => $device->canonicalLocation ? ['id'=>(string)$device->canonicalLocation->id,'name'=>$device->canonicalLocation->name] : null,
                'macAddress' => $device->mac_address,
                'templateId' => $device->device_template_id,
            ],
            'dashboard' => $dashboard ? [
                'id' => $dashboard->id,
                'name' => $dashboard->name,
                'description' => $dashboard->description,
                'widgets' => $dashboard->widgets->map(fn ($widget) => [
                    'id' => $widget->id,
                    'type' => $widget->widget_type,
                    'title' => $widget->title,
                    'layout' => $widget->layout,
                    'configuration' => $widget->configuration,
                ])->sortBy('id')->values()->all(),
            ] : null,
            'parameters' => $device->parameters->map(fn ($parameter) => [
                'id' => $parameter->id,
                'name' => $parameter->name,
                'key' => $parameter->key,
                'dataType' => $parameter->data_type,
                'unit' => $parameter->unit,
                'description' => $parameter->description,
            ])->sortBy(fn ($parameter) => [$parameter['key'], $parameter['id']])->values()->all(),
        ]);
    }

    public function normalize(array $value): array
    {
        $walk = function ($item) use (&$walk) {
            if (!is_array($item)) return $item;
            if (array_is_list($item)) return array_map($walk, $item);
            ksort($item);
            return array_map($walk, $item);
        };
        return $walk($value);
    }
}
