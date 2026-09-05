<?php

namespace App\Http\Resources;

use App\Services\Admin\DeviceAccessService;
use App\Services\ResourceLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DeviceResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'creator:id,name',
            'template:id,name',
            'canonicalLocation:id,name',
            'latestRevision',
            'lifecycleState:id,resource_type,resource_id,state',
            'metadataValues.definition',
        ]);

        $access = app(DeviceAccessService::class)->accessPayload($request->user(), $this->resource);
        $locationState = $this->canonicalLocation
            ? app(ResourceLifecycleService::class)->state('location', $this->canonicalLocation->id)
            : null;

        return [
            'id' => (string) $this->id,
            'canonicalId' => (string) $this->id,
            'baseRevisionId' => $this->latestRevision ? (string) $this->latestRevision->id : null,
            'saveOutcome' => $this->getAttribute('save_idempotency_outcome'),
            'name' => $this->name,
            'type' => $this->type,
            'serialNumber' => $this->external_id,
            'status' => $this->status,
            'location' => $this->canonicalLocation ? [
                'id' => (string) $this->canonicalLocation->id,
                'name' => $this->canonicalLocation->name,
                'lifecycle' => $locationState,
                'isAssignable' => $locationState === 'active',
            ] : null,
            'lastSeen' => $this->last_seen?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'battery' => $this->battery,
            'firmwareVersion' => $this->firmware_version,
            'protocol' => $this->protocol,
            'macAddress' => $this->mac_address,
            'creator' => $this->creator ? ['id' => (string) $this->creator->id, 'name' => $this->creator->name] : null,
            'template' => $this->template ? ['id' => (string) $this->template->id, 'name' => $this->template->name] : null,
            'metadata' => $this->metadataValues->map(fn ($v) => [
                'definitionId' => (string) $v->metadata_definition_id,
                'key' => $v->definition?->key,
                'name' => $v->definition?->name,
                'type' => $v->definition?->data_type,
                'value' => $v->value,
            ])->values(),
            'access' => $access,
            'capabilities' => [
                'canView' => true,
                'canEdit' => $access['canManage'],
                'canShare' => $access['canManage'],
                'canEditDashboard' => $access['canManage'],
                'canEditParameters' => $access['canManage'],
                'canManageCredentials' => $access['canManage'],
                'canManageAccess' => false,
                'canChangeLocation' => $access['canManage'],
                'canViewAdminMetadata' => false,
            ],
        ];
    }
}
