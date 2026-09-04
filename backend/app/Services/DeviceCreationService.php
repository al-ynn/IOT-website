<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Organization;
use App\Models\User;
use App\Services\Admin\DeviceAccessService;
use Illuminate\Support\Facades\DB;
use App\Jobs\NotifyAdminsOfDeviceCreated;
use Illuminate\Validation\ValidationException;

class DeviceCreationService
{
    public function __construct(
        private DeviceAccessService $access,
        private DeviceTemplateService $templates,
        private DeviceParameterService $parameters,
        private NotificationOutboxService $notificationOutbox,
        private ResourceRevisionService $revisions,
        private LocationService $locations,
    ) {}

    public function create(User $actor, Organization $organization, array $data): Device
    {
        if (! $actor->isActive() || ! $actor->organization_id || (int) $actor->organization_id !== (int) $organization->id) {
            throw ValidationException::withMessages(['organization_id' => ['The selected organization is not available.']]);
        }

        $location = $this->locations->assertAssignable($organization, isset($data['location_id']) ? (int)$data['location_id'] : null);
        $device = DB::transaction(function () use ($actor, $organization, $data, $location) {
            $device = $organization->devices()->create([
                'created_by' => $actor->id,
                'name' => $data['name'],
                'type' => $data['type'],
                'external_id' => $data['serialNumber'],
                'protocol' => $data['protocol'],
                'location_id' => $location?->id,
                'location' => null,
                'mac_address' => $data['macAddress'] ?? null,
            ]);
            if (!$actor->isPlatformAdmin()) $this->access->createForCreator($actor, $device);
            if (!empty($data['template_id'])) {
                $template = $this->templates->findForOrganization($organization, $data['template_id']);
                app(ResourceLifecycleService::class)->assertActive('device_template', $template->id, 'Disabled or Archived Templates cannot be used for Device creation.');
                $device = $this->templates->apply($template, $device, $this->parameters);
            }
            $this->revisions->recordDevice($device, $actor, 'Initial Device configuration');
            if (!$actor->isPlatformAdmin()) {
                $this->notificationOutbox->recordDeviceCreated($device);
                DB::afterCommit(fn () => NotifyAdminsOfDeviceCreated::dispatch($device->id));
            }
            return $device;
        });
        return $device->refresh()->load(['creator:id,name', 'template:id,name', 'canonicalLocation:id,name']);
    }
}
