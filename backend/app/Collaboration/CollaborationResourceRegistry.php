<?php

namespace App\Collaboration;

use App\Models\Automation;
use App\Models\Dashboard;
use App\Models\Device;
use App\Models\DeviceTemplate;
use App\Models\FirmwareArtifact;
use App\Models\Report;
use App\Models\User;
use App\Models\ResourceLifecycleState;
use App\Models\Webhook;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class CollaborationResourceRegistry
{
    /** @var array<string, CollaborationResourceDefinition> */
    private array $definitions;

    public function __construct(DeviceCollaborationAuthorizer $devices, DeviceTemplateCollaborationAuthorizer $templates, DashboardCollaborationAuthorizer $dashboards, FirmwareCollaborationAuthorizer $firmware, AutomationCollaborationAuthorizer $automations, ReportCollaborationAuthorizer $reports, WebhookCollaborationAuthorizer $webhooks, LocationResourceAuthorizer $locations)
    {
        $this->definitions = [
            'device' => new CollaborationResourceDefinition('device', Device::class, $devices, array_merge($this->capabilities(true, true, true, false, true), ['adminRecentlyCreated' => true, 'adminRecentlyUpdated' => true, 'needsAttention' => true]), ['viewer', 'full_access']),
            'device_template' => new CollaborationResourceDefinition('device_template', DeviceTemplate::class, $templates, array_merge($this->capabilities(true, true, true, true, true), ['publication' => true, 'versionedPublication' => true, 'publishedCatalog' => true, 'reviewCenter' => true, 'adminRecentlyCreated' => true, 'adminRecentlyUpdated' => true, 'needsAttention' => true]), ['view', 'edit', 'review'], true),
            'dashboard' => new CollaborationResourceDefinition('dashboard', Dashboard::class, $dashboards, array_merge($this->capabilities(true, true, true, true, true), ['privateCollaboration' => true, 'immediateCanonicalEdits' => true, 'pullable' => false, 'publication' => true, 'versionedPublication' => true, 'publishedCatalog' => true, 'reviewCenter' => true]), ['view', 'edit'], true),
            'automation' => new CollaborationResourceDefinition('automation', Automation::class, $automations, array_merge($this->capabilities(true, true, true, true, true), ['privateCollaboration' => true, 'publication' => true, 'versionedPublication' => true, 'publishedCatalog' => true, 'reviewCenter' => true]), ['view', 'edit', 'review'], true),
            'report' => new CollaborationResourceDefinition('report', Report::class, $reports, array_merge($this->capabilities(true,true,true,false,true),['privateRuns'=>true,'publication'=>true,'reviewCenter'=>true]), ['view','edit'], true),
            'firmware' => new CollaborationResourceDefinition('firmware', FirmwareArtifact::class, $firmware, array_merge($this->capabilities(true, true, true, true, true), ['releaseApproval'=>true,'reviewCenter'=>true]), ['view', 'edit', 'review'], true),
            'location' => new CollaborationResourceDefinition('location', Location::class, $locations, array_merge($this->capabilities(true, true, true, false, true), ['organizationalMetadata'=>true, 'privateCollaboration'=>true, 'needsAttention'=>true, 'pullable'=>false]), ['view', 'edit'], true),
            'webhook' => new CollaborationResourceDefinition('webhook', Webhook::class, $webhooks, array_merge($this->capabilities(true, true, true, true, true), ['privateCollaboration'=>true,'activationApproval'=>true,'reviewCenter'=>true]), ['view', 'edit'], true),
        ];
    }

    public function definition(string $type): CollaborationResourceDefinition
    {
        if (! isset($this->definitions[$type])) {
            throw ValidationException::withMessages(['resource_type' => ['Unsupported collaboration resource type.']]);
        }

        return $this->definitions[$type];
    }

    /** @return array<string, array> */
    public function metadata(): array
    {
        return array_map(fn (CollaborationResourceDefinition $definition) => $definition->metadata(), $this->definitions);
    }

    public function resolve(User $user, CollaborationResourceReference $reference, string $ability = 'view'): ResolvedCollaborationResource
    {
        $definition = $this->definition($reference->type);

        if (! $user->isActive() || ! $user->organization_id) {
            abort(404);
        }

        /** @var Model|null $resource */
        $resource = $definition->modelClass::query()
            ->where('organization_id', $user->organization_id)
            ->find($reference->id);

        if (! $resource || ! $this->allowed($definition->authorizer, $ability, $user, $resource)) {
            abort(404);
        }

        return new ResolvedCollaborationResource($definition, $resource);
    }

    private function allowed(CollaborationResourceAuthorizer $authorizer, string $ability, User $user, Model $resource): bool
    {
        $state=ResourceLifecycleState::where(['resource_type'=>$this->typeFor($resource),'resource_id'=>$resource->getKey()])->value('state')??'active';
        if(in_array($ability,['edit','share','review'],true)&&$state!=='active')return false;
        if($ability==='comment'&&$state!=='active')return false;
        return match ($ability) {
            'view' => $authorizer->canView($user, $resource),
            'edit' => $authorizer->canEdit($user, $resource),
            'share' => $authorizer->canShare($user, $resource),
            'comment' => $authorizer->canComment($user, $resource),
            'review' => $authorizer->canReview($user, $resource),
            'administer' => $authorizer->canAdminister($user, $resource),
            default => throw ValidationException::withMessages(['ability' => ['Unsupported collaboration ability.']]),
        };
    }

    private function typeFor(Model $resource): string { foreach($this->definitions as $key=>$definition)if($resource instanceof $definition->modelClass)return $key;return ''; }

    private function capabilities(bool $sharing, bool $comments, bool $revisions, bool $review, bool $lifecycle): array
    {
        return ['sharing' => $sharing, 'comments' => $comments, 'revisions' => $revisions, 'review' => $review, 'lifecycle' => $lifecycle, 'notifications' => true];
    }
}
