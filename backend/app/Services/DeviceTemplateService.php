<?php

namespace App\Services;

use App\Collaboration\DeviceTemplateCollaborationAuthorizer;
use App\Models\Device;
use App\Models\DeviceTemplate;
use App\Models\DeviceTemplateParameter;
use App\Models\Organization;
use App\Models\ResourcePublicationVersion;
use App\Models\ResourceRevision;
use App\Models\User;
use App\Services\Admin\DeviceAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class DeviceTemplateService
{
    public const HARDWARE = ['sensor', 'gateway', 'esp32', 'esp8266', 'raspberry_pi', 'arduino', 'industrial_controller'];
    public const CONNECTIONS = ['mqtt', 'https', 'wifi', 'ethernet', 'cellular', 'lorawan', 'bluetooth'];
    public function __construct(private ResourceRevisionService $revisions, private SafeResourceSaveService $saves, private DeviceTemplateCollaborationAuthorizer $authorizer, private ResourceLifecycleService $lifecycle, private DeviceAccessService $deviceAccess) {}

    public function templateRules(bool $u = false): array
    {
        return ['name' => [$u ? 'sometimes' : 'required', 'required', 'string', 'max:50'], 'description' => ['nullable', 'string', 'max:128'], 'device_type' => ['sometimes', 'required', Rule::in(self::HARDWARE)], 'protocol' => ['sometimes', 'required', Rule::in(self::CONNECTIONS)], 'baseRevisionId' => [$u ? 'sometimes' : 'prohibited', 'integer']];
    }

    public function parameterRules(bool $u = false): array
    {
        $rules = app(DeviceParameterService::class)->createRules();
        if ($u) {
            foreach ($rules as &$r) {
                array_unshift($r, 'sometimes');
            }
        }

        return $rules;
    }

    public function list(Organization $o, array $f = [])
    {
        return $o->deviceTemplates()->withCount(['parameters', 'devices'])->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($n) => $n->where('name', 'like', "%$s%")->orWhere('description', 'like', "%$s%")))->orderBy('name')->paginate($f['per_page'] ?? 25)->withQueryString();
    }

    public function findForOrganization(Organization $o, string|int $id): DeviceTemplate
    {
        return $o->deviceTemplates()->findOrFail($id);
    }

    public function detail(DeviceTemplate $t): DeviceTemplate
    {
        return $t->load(['organization:id,name', 'parameters', 'metadataDefinitions', 'eventDefinitions', 'creator:id,name'])->loadCount('devices');
    }

    public function metadataDefinitionRules(bool $update = false): array
    {
        return ['name' => [$update ? 'sometimes' : 'required', 'string', 'max:120'], 'key' => [$update ? 'sometimes' : 'required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'], 'data_type' => [$update ? 'sometimes' : 'required', Rule::in(['string', 'integer', 'number', 'boolean', 'date', 'datetime', 'enum'])], 'description' => ['nullable', 'string', 'max:1000'], 'required' => ['sometimes', 'boolean'], 'configuration' => ['nullable', 'array'], 'baseRevisionId' => ['nullable', 'integer']];
    }

    public function eventDefinitionRules(bool $update = false): array
    {
        return ['name' => [$update ? 'sometimes' : 'required', 'string', 'max:120'], 'code' => [$update ? 'sometimes' : 'required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'], 'severity' => [$update ? 'sometimes' : 'required', Rule::in(['info', 'warning', 'critical'])], 'description' => ['nullable', 'string', 'max:1000'], 'enabled' => ['sometimes', 'boolean'], 'baseRevisionId' => ['nullable', 'integer']];
    }

    public function addMetadataDefinition(DeviceTemplate $t, array $data, User $u, ?int $base = null, ?string $key = null)
    {
        $data['key'] = strtolower(trim($data['key']));
        $data['name'] = trim($data['name']); unset($data['baseRevisionId']);
        $this->save($u, $t, $base, $key, ['metadata_definition' => $data], function (DeviceTemplate $locked) use ($data) {
            if ($locked->metadataDefinitions()->where('key', $data['key'])->exists()) throw ValidationException::withMessages(['key' => ['This metadata key already exists in the Template.']]);
            $locked->metadataDefinitions()->create($data);
        }, 'Template metadata definition updated');
        return $t->metadataDefinitions()->where('key', $data['key'])->firstOrFail();
    }

    public function addEventDefinition(DeviceTemplate $t, array $data, User $u, ?int $base = null, ?string $key = null)
    {
        $data['code'] = strtolower(trim($data['code'])); $data['name'] = trim($data['name']); unset($data['baseRevisionId']);
        $this->save($u, $t, $base, $key, ['event_definition' => $data], function (DeviceTemplate $locked) use ($data) {
            if ($locked->eventDefinitions()->where('code', $data['code'])->exists()) throw ValidationException::withMessages(['code' => ['This event code already exists in the Template.']]);
            $locked->eventDefinitions()->create($data);
        }, 'Template event definition updated');
        return $t->eventDefinitions()->where('code', $data['code'])->firstOrFail();
    }
    public function updateMetadataDefinition($definition,array $data,User $u,?int $base=null,?string $key=null){$data['name']=trim($data['name']??$definition->name);unset($data['baseRevisionId']);$this->save($u,$definition->template,$base,$key,['metadata_definition_id'=>$definition->id,...$data],fn($locked)=>$locked->metadataDefinitions()->findOrFail($definition->id)->update($data),'Template metadata definition updated');return $definition->fresh();}
    public function updateEventDefinition($definition,array $data,User $u,?int $base=null,?string $key=null){$data['name']=trim($data['name']??$definition->name);unset($data['baseRevisionId']);$this->save($u,$definition->template,$base,$key,['event_definition_id'=>$definition->id,...$data],fn($locked)=>$locked->eventDefinitions()->findOrFail($definition->id)->update($data),'Template event definition updated');return $definition->fresh();}

    public function create(Organization $o, User $u, array $d): DeviceTemplate
    {
        return DB::transaction(function () use ($o, $u, $d) {
            $t = $o->deviceTemplates()->create([...$d, 'device_type' => $d['device_type'] ?? 'sensor', 'protocol' => $d['protocol'] ?? 'mqtt', 'created_by' => $u->id]);
            $this->revisions->recordTemplate($t, $u, 'Template created');

            return $t;
        });
    }

    public function update(DeviceTemplate $t, array $d, User $u, int|string|null $baseRevisionId = null, ?string $idempotencyKey = null): DeviceTemplate
    {
        unset($d['baseRevisionId']);

        return $this->detail($this->save($u, $t, $baseRevisionId, $idempotencyKey, $d, fn (DeviceTemplate $locked) => $locked->update($d)));
    }

    public function delete(DeviceTemplate $t): void
    {
        $t->delete();
    }

    public function dashboard(DeviceTemplate $template): array
    {
        $saved = $template->dashboard_configuration ?? [];
        return [
            'id' => 'template-'.$template->id,
            'name' => $saved['name'] ?? $template->name.' Web Dashboard',
            'description' => null,
            'scope' => 'template',
            'canEdit' => true,
            'canShare' => false,
            'isDefault' => true,
            'layoutVersion' => (int) ($saved['layoutVersion'] ?? 1),
            'baseRevisionId' => ResourceRevision::where(['resource_type' => 'device_template', 'resource_id' => $template->id])->latest('revision_number')->value('id'),
            'widgets' => $saved['widgets'] ?? [],
            'widgetDefinitions' => app(\App\Services\Dashboard\DashboardWidgetRegistry::class)->definitions('personal'),
        ];
    }

    public function saveDashboard(DeviceTemplate $template, array $data, User $actor, int|string|null $baseRevisionId, ?string $idempotencyKey): DeviceTemplate
    {
        $registry = app(\App\Services\Dashboard\DashboardWidgetRegistry::class);
        $widgets = collect($data['widgets'])->map(function (array $widget) use ($actor, $registry) {
            $settings = $widget['settings'] ?? [];
            $source = $settings['datasource'] ?? [];
            $configuration = array_filter(['deviceId' => $source['deviceId'] ?? null, 'telemetryKey' => $source['telemetryKey'] ?? null, 'unit' => $source['unit'] ?? null, 'timeRange' => $settings['timeRange'] ?? null, 'chartType' => $settings['chartType'] ?? null, 'minimum' => $settings['minimum'] ?? null, 'maximum' => $settings['maximum'] ?? null, 'staticValue' => $settings['staticValue'] ?? null, 'fontSize' => $settings['fontSize'] ?? null, 'fontStyle' => $settings['fontStyle'] ?? null, 'aggregation' => $settings['aggregation'] ?? null, 'rowLimit' => $settings['rowLimit'] ?? null, 'step' => $settings['step'] ?? null], fn ($value) => $value !== null && $value !== '');
            $validated = $registry->validate($actor, 'personal', ['id' => $widget['id'] ?? null, 'type' => $widget['type'] ?? null, 'title' => $settings['title'] ?? null, 'layout' => $widget['layout'] ?? null, 'configuration' => $configuration]);
            return ['id' => $validated['id'], 'type' => $validated['type'], 'settings' => ['title' => $validated['title'], 'datasource' => isset($configuration['deviceId']) ? ['deviceId' => (string) $configuration['deviceId'], 'telemetryKey' => $configuration['telemetryKey'] ?? '', 'unit' => $configuration['unit'] ?? null] : null, 'timeRange' => $configuration['timeRange'] ?? null, 'chartType' => $configuration['chartType'] ?? null, 'minimum' => $configuration['minimum'] ?? null, 'maximum' => $configuration['maximum'] ?? null, ...array_intersect_key($configuration, array_flip(['staticValue', 'fontSize', 'fontStyle', 'aggregation', 'rowLimit', 'step']))], 'layout' => $validated['layout'], 'available' => true];
        })->values()->all();
        $command = ['name' => $data['name'], 'widgets' => $widgets, 'layoutVersion' => $data['layoutVersion']];
        return $this->save($actor, $template, $baseRevisionId, $idempotencyKey, $command, fn (DeviceTemplate $locked) => $locked->update(['dashboard_configuration' => ['name' => $data['name'], 'layoutVersion' => $data['layoutVersion'] + 1, 'widgets' => $widgets]]), 'Template Web Dashboard updated');
    }

    public function addParameter(DeviceTemplate $t, array $d, User $u, int|string|null $baseRevisionId = null, ?string $idempotencyKey = null): DeviceTemplateParameter
    {
        unset($d['baseRevisionId']);$d=app(DeviceParameterService::class)->allowedCreate($d);
        $this->save($u, $t, $baseRevisionId, $idempotencyKey, $d, function (DeviceTemplate $locked) use ($d) {
            if ($locked->parameters()->where('key', $d['key'])->exists()) {
                throw ValidationException::withMessages(['key' => ['This key already exists in the template.']]);
            }
            $locked->parameters()->create($d);
        }, 'Template parameters updated');

        return $t->parameters()->where('key', $d['key'])->firstOrFail();
    }

    public function findParameter(DeviceTemplate $t, string|int $id): DeviceTemplateParameter
    {
        return $t->parameters()->findOrFail($id);
    }

    public function updateParameter(DeviceTemplateParameter $p, array $d, User $u, int|string|null $baseRevisionId = null, ?string $idempotencyKey = null): DeviceTemplateParameter
    {
        unset($d['baseRevisionId']);
        unset($d['key']);
        if (array_key_exists('configuration', $d)) {
            $normalized = app(DeviceParameterService::class)->allowedCreate([
                'name' => $d['name'] ?? $p->name,
                'key' => $p->key,
                'data_type' => $d['data_type'] ?? $p->data_type,
                'unit' => $d['unit'] ?? $p->unit,
                'description' => $d['description'] ?? $p->description,
                'semantic' => $d['semantic'] ?? $p->semantic,
                'configuration' => $d['configuration'],
            ]);
            $d['configuration'] = $normalized['configuration'];
        }
        $id = $p->id;
        $this->save($u, $p->template, $baseRevisionId, $idempotencyKey, ['parameter_id' => $id, ...$d], fn (DeviceTemplate $locked) => $locked->parameters()->findOrFail($id)->update($d), 'Template parameters updated');

        return $p->newQuery()->findOrFail($id);
    }

    public function deleteParameter(DeviceTemplateParameter $p, User $u, int|string|null $baseRevisionId = null, ?string $idempotencyKey = null): void
    {
        $id = $p->id;
        $this->save($u, $p->template, $baseRevisionId, $idempotencyKey, ['parameter_id' => $id, 'delete' => true], fn (DeviceTemplate $locked) => $locked->parameters()->findOrFail($id)->delete(), 'Template parameters updated');
    }

    public function duplicate(DeviceTemplate $t, User $u): DeviceTemplate
    {
        $this->assertActive($t);

        return DB::transaction(function () use ($t, $u) {
            $copy = $t->replicate();
            $copy->name = 'Copy of '.$t->name;
            $copy->created_by = $u->id;
            $copy->save();
            foreach ($t->parameters as $p) {
                $n = $p->replicate();
                $n->device_template_id = $copy->id;
                $n->save();
            }$this->revisions->recordTemplate($copy, $u, 'Template created from duplicate');

            return $this->detail($copy);
        });
    }

    public function apply(DeviceTemplate $t, Device $d, DeviceParameterService $p, ?User $actor = null, ?ResourceRevision $revision = null, ?ResourcePublicationVersion $version = null, int|string|null $baseRevisionId = null, ?string $idempotencyKey = null): Device
    {
        $this->assertActive($t);
        app(ResourceLifecycleService::class)->assertActive('device', $d->id, 'Disabled or Archived Devices cannot receive a Template.');
        if ($t->organization_id !== $d->organization_id) {
            throw ValidationException::withMessages(['template_id' => ['Template and Device must belong to the same organization.']]);
        }if ($revision && ($revision->resource_type !== 'device_template' || (int) $revision->resource_id !== (int) $t->id)) {
            throw ValidationException::withMessages(['revision_id' => ['Revision does not belong to this Template.']]);
        }if ($version && ((int) $version->resource_id !== (int) $t->id || (int) $version->resource_revision_id !== (int) $revision?->id)) {
            throw ValidationException::withMessages(['publication_version' => ['Publication Version does not match this Template revision.']]);
        }$parameters = $revision ? collect($revision->snapshot['parameters'] ?? []) : $t->parameters->map(fn ($x) => ['name' => $x->name, 'key' => $x->key, 'dataType' => $x->data_type, 'unit' => $x->unit, 'description' => $x->description,'semantic'=>$x->semantic,'configuration'=>$x->configuration]);
        $keys = $parameters->pluck('key');
        $conflicts = $d->parameters()->whereIn('key', $keys)->pluck('key')->values()->all();
        if ($conflicts) {
            throw ValidationException::withMessages(['template_id' => ['Existing parameter keys conflict: '.implode(', ', $conflicts).'.']]);
        }

        if (! $actor) {
            return DB::transaction(function () use ($t, $d, $p, $revision, $version, $parameters) {
                foreach ($parameters as $x) {
                    $p->create($d, ['name' => $x['name'], 'key' => $x['key'], 'data_type' => $x['dataType'], 'unit' => $x['unit'] ?? null, 'description' => $x['description'] ?? null,'semantic'=>$x['semantic']??null,'configuration'=>$x['configuration']??null], null, false);
                }
                $d->update(['device_template_id' => $t->id, 'device_template_revision_id' => $revision?->id, 'device_template_publication_version_id' => $version?->id]);
                $this->revisions->recordDevice($d, null, 'Template applied');

                return $d->refresh();
            });
        }

        return $this->saves->execute($actor, 'device', Device::class, $d->id, $baseRevisionId,
            fn (User $user, Device $locked) => abort_unless($this->deviceAccess->hasFullDeviceAccess($user, $locked), 404),
            fn (Device $locked) => $this->lifecycle->assertActive('device', $locked->id, 'Disabled or Archived Devices cannot receive a Template.'),
            function (Device $d) use ($t, $p, $revision, $version, $parameters) {
                foreach ($parameters as $x) {
                    $p->create($d, ['name' => $x['name'], 'key' => $x['key'], 'data_type' => $x['dataType'], 'unit' => $x['unit'] ?? null, 'description' => $x['description'] ?? null,'semantic'=>$x['semantic']??null,'configuration'=>$x['configuration']??null], null, false);
                }$d->update(['device_template_id' => $t->id, 'device_template_revision_id' => $revision?->id, 'device_template_publication_version_id' => $version?->id]);

                return $d;
            }, fn (Device $locked, User $user) => $this->revisions->recordDevice($locked, $user, 'Template applied'), $baseRevisionId !== null, $idempotencyKey, ['template_id' => $t->id, 'revision_id' => $revision?->id, 'publication_version_id' => $version?->id]);
    }

    private function assertActive(DeviceTemplate $template): void
    {
        app(ResourceLifecycleService::class)->assertActive('device_template', $template->id, 'Disabled or Archived Templates cannot be changed or used.');
    }

    private function save(User $actor, DeviceTemplate $template, int|string|null $baseRevisionId, ?string $idempotencyKey, array $command, callable $mutate, string $summary = 'Template metadata updated'): DeviceTemplate
    {
        return $this->saves->execute($actor, 'device_template', DeviceTemplate::class, $template->id, $baseRevisionId, fn (User $user, DeviceTemplate $locked) => abort_unless($this->authorizer->canEdit($user, $locked), 404), fn (DeviceTemplate $locked) => $this->lifecycle->assertActive('device_template', $locked->id, 'Disabled or Archived Templates cannot be changed or used.'), function (DeviceTemplate $locked) use ($mutate) {
            $mutate($locked);

            return $locked;
        }, fn (DeviceTemplate $locked, User $user) => $this->revisions->recordTemplate($locked, $user, $summary), $baseRevisionId !== null, $idempotencyKey, $command);
    }
}
