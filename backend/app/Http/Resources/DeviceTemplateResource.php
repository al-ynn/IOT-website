<?php

namespace App\Http\Resources;

use App\Models\ResourceCollaborator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $permission = $request->user()
            ? ResourceCollaborator::where(['resource_type' => 'device_template', 'resource_id' => $this->id, 'user_id' => $request->user()->id])->value('permission')
            : null;
        $admin = (bool) $request->user()?->isPlatformAdmin();
        $lifecycle = app(\App\Services\ResourceLifecycleService::class)->state('device_template', $this->id);
        $operational = $lifecycle === 'active';

        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'deviceType' => $this->device_type,
            'protocol' => $this->protocol,
            'organization' => ['id' => (string) $this->organization_id, 'name' => $this->organization?->name ?? ''],
            'creator' => $this->creator ? ['id' => (string) $this->creator->id, 'name' => $this->creator->name] : null,
            'permission' => $admin ? 'admin' : $permission,
            'lifecycleState' => $lifecycle,
            'capabilities' => ['canView' => $admin || in_array($permission, ['view', 'edit'], true), 'canEdit' => $operational && ($admin || $permission === 'edit'), 'canShare' => $operational && ($admin || $permission === 'edit'), 'canComment' => $lifecycle !== 'archived' && ($admin || in_array($permission, ['view', 'edit'], true)), 'canViewRevisions' => $admin || in_array($permission, ['view', 'edit'], true)],
            'parameterCount' => (int) ($this->parameters_count ?? $this->parameters->count()),
            'deviceCount' => (int) ($this->devices_count ?? 0),
            'dashboardConfigured' => count($this->dashboard_configuration['widgets'] ?? []) > 0,
            'parameters' => $this->when($this->relationLoaded('parameters'), fn () => $this->parameters->map(fn ($parameter) => ['id' => (string) $parameter->id, 'name' => $parameter->name, 'key' => $parameter->key, 'dataType' => $parameter->data_type, 'unit' => $parameter->unit, 'description' => $parameter->description, 'semantic' => $parameter->semantic, 'configuration' => $parameter->configuration ?? []])),
            'metadataDefinitions' => $this->when($this->relationLoaded('metadataDefinitions'), fn () => $this->metadataDefinitions->map(fn ($d) => ['id'=>(string)$d->id,'name'=>$d->name,'key'=>$d->key,'dataType'=>$d->data_type,'description'=>$d->description,'required'=>(bool)$d->required,'configuration'=>$d->configuration??[]])),
            'eventDefinitions' => $this->when($this->relationLoaded('eventDefinitions'), fn () => $this->eventDefinitions->map(fn ($e) => ['id'=>(string)$e->id,'name'=>$e->name,'code'=>$e->code,'severity'=>$e->severity,'description'=>$e->description,'enabled'=>(bool)$e->enabled])),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
