<?php

namespace App\Collaboration;

use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Device;
use App\Models\DeviceParameter;
use App\Models\Location;
use App\Services\ResourceSectionRegistry;
use Illuminate\Validation\ValidationException;

final class CommentAnchorRegistry
{
    public const DEVICE = ['resource', 'section', 'dashboard_widget', 'parameter'];

    public const LOCATION = ['resource', 'metadata', 'name', 'description'];

    private const SECTION_RESOURCES = ['device_template', 'automation', 'report', 'firmware', 'webhook'];

    public function allowedTypes(string $type): array
    {
        return match ($type) {
            'device' => self::DEVICE,
            'dashboard' => ['resource', 'section', 'dashboard_widget'],
            'location' => self::LOCATION,
            'device_template', 'automation', 'report', 'firmware', 'webhook' => ['resource', 'section'],
            default => [],
        };
    }

    public function validate(string $type, object $resource, string $anchorType, ?string $key): void
    {
        if (! in_array($anchorType, $this->allowedTypes($type), true)) {
            throw ValidationException::withMessages(['anchor_type' => ['Unsupported anchor type for this resource.']]);
        }

        if ($type === 'location' && $resource instanceof Location) {
            if ($key !== null) {
                throw ValidationException::withMessages(['anchor_key' => ['Location anchors do not accept an arbitrary key.']]);
            }

            return;
        }
        if ($anchorType === 'section') {
            if ($key === null) {
                throw ValidationException::withMessages(['anchor_key' => ['Section anchors require a semantic section key.']]);
            }
            app(ResourceSectionRegistry::class)->validate($type, $key);

            return;
        }

        if ($anchorType === 'resource') {
            if ($key !== null) {
                throw ValidationException::withMessages(['anchor_key' => ['Whole-resource threads do not accept an anchor key.']]);
            }

            return;
        }

        if (in_array($type, self::SECTION_RESOURCES, true)) {
            throw ValidationException::withMessages(['anchor_type' => ['This resource supports only whole-resource and semantic-section anchors.']]);
        }

        if ($type === 'dashboard' && $resource instanceof Dashboard) {
            if (! $key || ! DashboardWidget::whereKey($key)->where('dashboard_id', $resource->id)->exists()) {
                throw ValidationException::withMessages(['anchor_key' => ['Dashboard Widget does not belong to this Dashboard.']]);
            }

            return;
        }
        if ($type !== 'device' || ! $resource instanceof Device) {
            throw ValidationException::withMessages(['resource_type' => ['Comments are not active for this resource type.']]);
        }
        if (in_array($anchorType, ['parameter', 'dashboard_widget'], true) && ! $key) {
            throw ValidationException::withMessages(['anchor_key' => ['This anchor requires an identifier.']]);
        }
        if ($anchorType === 'parameter' && ! DeviceParameter::whereKey($key)->where('device_id', $resource->id)->exists()) {
            throw ValidationException::withMessages(['anchor_key' => ['Parameter does not belong to this Device.']]);
        }
        if ($anchorType === 'dashboard_widget' && ! DashboardWidget::whereKey($key)->whereHas('dashboard', fn ($q) => $q->where('device_id', $resource->id))->exists()) {
            throw ValidationException::withMessages(['anchor_key' => ['Dashboard Widget does not belong to this Device.']]);
        }
    }
}
