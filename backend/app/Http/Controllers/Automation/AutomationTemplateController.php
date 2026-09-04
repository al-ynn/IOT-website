<?php

namespace App\Http\Controllers\Automation;

use App\Http\Controllers\Controller;
use App\Models\AutomationTemplate;
use App\Services\Automation\AutomationDefinitionService;
use App\Services\Automation\AutomationDefinitionValidator;
use Illuminate\Http\Request;

class AutomationTemplateController extends Controller
{
    public function index(Request $request)
    {
        $organization = $this->organization($request, 'automation.view');
        return AutomationTemplate::where('active', true)->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organization->id))->get()->map(fn ($template) => $this->resource($template));
    }

    public function show(Request $request, string $template)
    {
        $organization = $this->organization($request, 'automation.view');
        return $this->resource($this->find($organization, $template));
    }

    public function store(Request $request, AutomationDefinitionValidator $validator)
    {
        $organization = $this->organization($request, 'automation.manage');
        $data = $request->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string|max:2000', 'category' => 'required|string|max:100', 'definition' => 'required|array']);
        $validator->validate($data['definition'], $organization, false, $request->user(), true);
        $template = AutomationTemplate::create(['organization_id' => $organization->id, 'name' => $data['name'], 'description' => $data['description'] ?? null, 'category' => $data['category'], 'definition' => $data['definition'], 'created_by' => $request->user()->id]);
        return response()->json($this->resource($template), 201);
    }

    public function update(Request $request, string $template, AutomationDefinitionValidator $validator)
    {
        $organization = $this->organization($request, 'automation.manage');
        $owned = $this->findOwned($organization, $template);
        $data = $request->validate(['name' => 'sometimes|required|string|max:255', 'description' => 'nullable|string|max:2000', 'category' => 'sometimes|required|string|max:100', 'definition' => 'sometimes|required|array']);
        if (isset($data['definition'])) $validator->validate($data['definition'], $organization, false, $request->user(), true);
        $owned->update($data);
        return $this->resource($owned);
    }

    public function destroy(Request $request, string $template)
    {
        $organization = $this->organization($request, 'automation.manage');
        $this->findOwned($organization, $template)->delete();
        return response()->noContent();
    }

    public function instantiate(Request $request, string $template, AutomationDefinitionService $definitions, AutomationDefinitionValidator $validator)
    {
        $organization = $this->organization($request, 'automation.create');
        $source = $this->find($organization, $template);
        $data = $validator->validate($source->definition, $organization, false, $request->user(), true);
        $automation = $definitions->create($organization, $request->user(), $data);
        $definition = $definitions->definition($automation);
        return response()->json(['id' => (string) $automation->id, 'name' => $automation->name, 'description' => $automation->description, 'status' => $automation->status, 'enabled' => $automation->enabled, 'version' => $automation->version, 'trigger' => $definition['trigger'], 'conditions' => $definition['conditions'], 'actions' => $definition['actions'], 'schedule' => $definition['schedule'], 'createdAt' => $automation->created_at->toISOString(), 'updatedAt' => $automation->updated_at->toISOString()], 201);
    }

    private function organization(Request $request, string $permission)
    {
        abort_unless($request->user()->organization && $request->user()->hasOrganizationPermission($permission), 403);
        return $request->user()->organization;
    }

    private function find($organization, $id) { return AutomationTemplate::where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organization->id))->findOrFail($id); }
    private function findOwned($organization, $id) { return AutomationTemplate::where('organization_id', $organization->id)->findOrFail($id); }
    private function resource($template): array { $definition = $template->definition; return ['id' => (string) $template->id, 'name' => $template->name, 'description' => $template->description ?? '', 'category' => $template->category, 'trigger' => $definition['trigger'] ?? null, 'conditions' => $definition['conditions'] ?? null, 'actions' => $definition['actions'] ?? [], 'definition' => $definition, 'createdAt' => $template->created_at->toISOString()]; }
}
