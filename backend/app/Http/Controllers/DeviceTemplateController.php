<?php

namespace App\Http\Controllers;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Http\Resources\DeviceTemplateResource;
use App\Models\DeviceTemplate;
use App\Models\ResourceCollaborator;
use App\Services\Admin\DeviceAccessService;
use App\Services\DeviceParameterService;
use App\Services\DeviceTemplateService;
use App\Services\ResourceLifecycleService;
use App\Services\ResourcePublicationResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\DeviceCreationService;

class DeviceTemplateController extends Controller
{
    public function __construct(private CollaborationResourceRegistry $registry) {}

    private function organization(Request $request)
    {
        abort_unless($request->user()->organization, 403);

        return $request->user()->organization;
    }

    private function resolve(Request $request, string $id, string $ability = 'view'): DeviceTemplate
    {
        return $this->registry->resolve($request->user(), new CollaborationResourceReference('device_template', $id), $ability)->resource;
    }

    public function index(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'per_page' => ['nullable', 'integer', 'in:25,50,100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $ids = ResourceCollaborator::where(['resource_type' => 'device_template', 'user_id' => $request->user()->id])->pluck('resource_id');
        $query = DeviceTemplate::whereIn('id', $ids)->where('organization_id', $this->organization($request)->id)->withCount(['parameters', 'devices'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))->orderBy('name')->paginate($filters['per_page'] ?? 25);

        return DeviceTemplateResource::collection($query);
    }

    public function store(Request $request, DeviceTemplateService $service)
    {
        abort_unless($request->user()->hasOrganizationPermission('device.manage'), 403);
        $template = $service->create($this->organization($request), $request->user(), $request->validate($service->templateRules()));

        return (new DeviceTemplateResource($service->detail($template)))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $template, DeviceTemplateService $service)
    {
        return new DeviceTemplateResource($service->detail($this->resolve($request, $template)));
    }

    public function update(Request $request, string $template, DeviceTemplateService $service)
    {
        $data = $request->validate($service->templateRules(true));

        return new DeviceTemplateResource($service->update($this->resolve($request, $template, 'edit'), $data, $request->user(), $data['baseRevisionId'] ?? null, $request->header('Idempotency-Key')));
    }

    public function destroy(Request $request, string $template, DeviceTemplateService $service)
    {
        abort(405, 'Use the Admin lifecycle Disable or Archive action; ordinary hard delete is unavailable.');
    }

    public function addParameter(Request $request, string $template, DeviceTemplateService $service)
    {
        $resource = $this->resolve($request, $template, 'edit');
        $data = $request->validate($service->parameterRules());
        $service->addParameter($resource, $data, $request->user(), $data['baseRevisionId'] ?? null, $request->header('Idempotency-Key'));

        return new DeviceTemplateResource($service->detail($resource));
    }

    public function updateParameter(Request $request, string $template, string $parameter, DeviceTemplateService $service)
    {
        $resource = $this->resolve($request, $template, 'edit');
        $data = $request->validate($service->parameterRules(true));
        $service->updateParameter($service->findParameter($resource, $parameter), $data, $request->user(), $data['baseRevisionId'] ?? null, $request->header('Idempotency-Key'));

        return new DeviceTemplateResource($service->detail($resource));
    }

    public function deleteParameter(Request $request, string $template, string $parameter, DeviceTemplateService $service)
    {
        $resource = $this->resolve($request, $template, 'edit');
        $data = $request->validate(['baseRevisionId' => ['nullable', 'integer']]);
        $service->deleteParameter($service->findParameter($resource, $parameter), $request->user(), $data['baseRevisionId'] ?? null, $request->header('Idempotency-Key'));

        return response()->noContent();
    }

    public function addMetadata(Request $request, string $template, DeviceTemplateService $service)
    {
        $resource = $this->resolve($request, $template, 'edit'); $data = $request->validate($service->metadataDefinitionRules());
        $service->addMetadataDefinition($resource, $data, $request->user(), $data['baseRevisionId'] ?? null, $request->header('Idempotency-Key'));
        return new DeviceTemplateResource($service->detail($resource));
    }

    public function addEvent(Request $request, string $template, DeviceTemplateService $service)
    {
        $resource = $this->resolve($request, $template, 'edit'); $data = $request->validate($service->eventDefinitionRules());
        $service->addEventDefinition($resource, $data, $request->user(), $data['baseRevisionId'] ?? null, $request->header('Idempotency-Key'));
        return new DeviceTemplateResource($service->detail($resource));
    }
    public function updateMetadata(Request $request,string $template,string $definition,DeviceTemplateService $service){$resource=$this->resolve($request,$template,'edit');$item=$resource->metadataDefinitions()->findOrFail($definition);$data=$request->validate($service->metadataDefinitionRules(true));$service->updateMetadataDefinition($item,$data,$request->user(),$data['baseRevisionId']??null,$request->header('Idempotency-Key'));return new DeviceTemplateResource($service->detail($resource));}
    public function updateEvent(Request $request,string $template,string $definition,DeviceTemplateService $service){$resource=$this->resolve($request,$template,'edit');$item=$resource->eventDefinitions()->findOrFail($definition);$data=$request->validate($service->eventDefinitionRules(true));$service->updateEventDefinition($item,$data,$request->user(),$data['baseRevisionId']??null,$request->header('Idempotency-Key'));return new DeviceTemplateResource($service->detail($resource));}

    public function duplicate(Request $request, string $template, DeviceTemplateService $service)
    {
        return (new DeviceTemplateResource($service->duplicate($this->resolve($request, $template), $request->user())))->response()->setStatusCode(201);
    }

    public function dashboard(Request $request, string $template, DeviceTemplateService $service)
    {
        $resource = $this->resolve($request, $template);
        $dashboard = $service->dashboard($resource);
        $dashboard['canEdit'] = app(\App\Collaboration\DeviceTemplateCollaborationAuthorizer::class)->canEdit($request->user(), $resource);
        return response()->json($dashboard);
    }

    public function updateDashboard(Request $request, string $template, DeviceTemplateService $service)
    {
        $resource = $this->resolve($request, $template, 'edit');
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'layoutVersion' => ['required', 'integer', 'min:1'], 'baseRevisionId' => ['nullable', 'integer'], 'widgets' => ['present', 'array', 'max:30'], 'widgets.*' => ['array']]);
        $saved = $service->saveDashboard($resource, $data, $request->user(), $data['baseRevisionId'] ?? null, $request->header('Idempotency-Key'));
        return response()->json($service->dashboard($saved->refresh()));
    }

    public function createDevice(Request $request, string $template, DeviceCreationService $creation)
    {
        $resource = $this->resolve($request, $template, 'edit');
        $data = $request->validate(['name' => ['required', 'string', 'max:50'], 'organization_id' => ['prohibited'], 'template_id' => ['prohibited'], 'access_level' => ['prohibited']]);
        $device = $creation->create($request->user(), $this->organization($request), ['name' => trim($data['name']), 'type' => $resource->device_type, 'protocol' => $resource->protocol, 'serialNumber' => 'TPL-'.$resource->id.'-'.Str::upper(Str::random(12)), 'template_id' => $resource->id]);
        return response()->json((new \App\Http\Resources\DeviceResource($device))->resolve($request), 201);
    }

    public function apply(Request $request, string $device, DeviceTemplateService $service, DeviceAccessService $access, DeviceParameterService $parameters, ResourcePublicationResolver $publications)
    {
        $target = $access->findManageableDeviceOrFail($request->user(), $device);
        app(ResourceLifecycleService::class)->assertActive('device', $target->id, 'Disabled or Archived Devices cannot receive a Template.');
        $input = $request->validate(['template_id' => ['required', 'integer'], 'baseRevisionId' => ['nullable', 'integer']]);
        $templateId = (string) $input['template_id'];
        $version = $publications->current('device_template', $templateId);
        if ($version) {
            $template = DeviceTemplate::whereKey($templateId)->where('organization_id', $request->user()->organization_id)->firstOrFail();
            app(ResourceLifecycleService::class)->assertActive('device_template', $template->id, 'Disabled or Archived Templates cannot be applied.');
            $service->apply($template, $target, $parameters, $request->user(), $version->revision, $version, $input['baseRevisionId'] ?? null, $request->header('Idempotency-Key'));
        } else {
            $template = $this->resolve($request, $templateId);
            app(ResourceLifecycleService::class)->assertActive('device_template', $template->id, 'Disabled or Archived Templates cannot be applied.');
            $service->apply($template, $target, $parameters, $request->user(), null, null, $input['baseRevisionId'] ?? null, $request->header('Idempotency-Key'));
        }

        return response()->json(['applied' => true]);
    }
}
