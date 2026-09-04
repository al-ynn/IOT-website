<?php

namespace App\Services;

use App\Models\DeviceTemplate;
use App\Models\ResourcePublicationState;
use App\Models\ResourcePublicationVersion;
use App\Models\User;

final class ResourcePublicationResolver
{
    public function current(string $type, int|string $resourceId): ?ResourcePublicationVersion
    {
        $state = ResourcePublicationState::where(['resource_type' => $type, 'resource_id' => $resourceId])->first();
        if (! $state?->current_publication_version_id) return null;
        return ResourcePublicationVersion::with(['revision', 'publisher:id,name'])->whereKey($state->current_publication_version_id)->where(['resource_type' => $type, 'resource_id' => $resourceId])->first();
    }

    public function publishedTemplate(User $user, int|string $templateId): array
    {
        abort_unless($user->organization_id || $user->isPlatformAdmin(), 403);
        $query = DeviceTemplate::whereKey($templateId);
        if (! $user->isPlatformAdmin()) $query->where('organization_id', $user->organization_id);
        $template = $query->firstOrFail();
        app(ResourceLifecycleService::class)->assertActive('device_template', $template->id, 'This published Template is not currently available for new use.');
        $version = $this->current('device_template', $template->id);
        abort_unless($version, 404);
        return [$template, $version];
    }

    public function version(User $user, int|string $templateId, int|string $versionId): array
    {
        [$template] = $this->publishedTemplate($user, $templateId);
        $version = ResourcePublicationVersion::with(['revision', 'publisher:id,name', 'previousVersion:id,publication_number'])->whereKey($versionId)->where(['resource_type' => 'device_template', 'resource_id' => $template->id])->firstOrFail();
        return [$template, $version];
    }
}
