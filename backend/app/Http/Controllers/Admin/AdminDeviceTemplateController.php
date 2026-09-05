<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceTemplateResource;
use App\Models\Device;
use App\Models\DeviceTemplate;
use App\Models\Organization;
use App\Services\DeviceParameterService;
use App\Services\DeviceTemplateService;
use Illuminate\Http\Request;

class AdminDeviceTemplateController extends Controller
{
    public function index(Request $r)
    {
        $f = $r->validate(['organization_id' => ['nullable', 'integer', 'exists:organizations,id'], 'search' => ['nullable', 'string', 'max:255'], 'per_page' => ['nullable', 'integer', 'in:25,50,100']]);
        $q = DeviceTemplate::query()->where('organization_id', $r->user()->organization_id)->with('organization:id,name')->withCount(['parameters', 'devices'])->when($f['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))->orderBy('name')->paginate($f['per_page'] ?? 25);

        return DeviceTemplateResource::collection($q);
    }

    public function show(Request $r, DeviceTemplate $template, DeviceTemplateService $s)
    {
        abort_unless((int) $template->organization_id === (int) $r->user()->organization_id, 404);

        return new DeviceTemplateResource($s->detail($template));
    }

    public function store(Request $r, DeviceTemplateService $s)
    {
        $d = $r->validate([...$s->templateRules(), 'organization_id' => ['required', 'integer', 'exists:organizations,id']]);
        $org = Organization::whereKey($r->user()->organization_id)->findOrFail($d['organization_id']);
        unset($d['organization_id']);

        return (new DeviceTemplateResource($s->detail($s->create($org, $r->user(), $d))))->response()->setStatusCode(201);
    }

    public function update(Request $r, DeviceTemplate $template, DeviceTemplateService $s)
    {
        abort_unless((int) $template->organization_id === (int) $r->user()->organization_id, 404);
        $data = $r->validate($s->templateRules(true));

        return new DeviceTemplateResource($s->update($template, $data, $r->user(), $data['baseRevisionId'] ?? null, $r->header('Idempotency-Key')));
    }

    public function destroy(DeviceTemplate $template, DeviceTemplateService $s)
    {
        abort(405, 'Use the lifecycle Disable or Archive action; ordinary hard delete is unavailable.');
    }

    public function addParameter(Request $r, DeviceTemplate $template, DeviceTemplateService $s)
    {
        $this->assertCurrentOrganization($r, $template);
        $data = $r->validate($s->parameterRules());
        $s->addParameter($template, $data, $r->user(), $data['baseRevisionId'] ?? null, $r->header('Idempotency-Key'));

        return new DeviceTemplateResource($s->detail($template));
    }

    public function updateParameter(Request $r, DeviceTemplate $template, string $parameter, DeviceTemplateService $s)
    {
        $this->assertCurrentOrganization($r, $template);
        $data = $r->validate($s->parameterRules(true));
        $s->updateParameter($s->findParameter($template, $parameter), $data, $r->user(), $data['baseRevisionId'] ?? null, $r->header('Idempotency-Key'));

        return new DeviceTemplateResource($s->detail($template));
    }

    public function deleteParameter(Request $r, DeviceTemplate $template, string $parameter, DeviceTemplateService $s)
    {
        $this->assertCurrentOrganization($r, $template);
        $data = $r->validate(['baseRevisionId' => ['nullable', 'integer']]);
        $s->deleteParameter($s->findParameter($template, $parameter), $r->user(), $data['baseRevisionId'] ?? null, $r->header('Idempotency-Key'));

        return response()->noContent();
    }

    public function duplicate(Request $r, DeviceTemplate $template, DeviceTemplateService $s)
    {
        $this->assertCurrentOrganization($r, $template);

        return (new DeviceTemplateResource($s->duplicate($template, $r->user())))->response()->setStatusCode(201);
    }

    public function apply(Request $r, Device $device, DeviceTemplateService $s, DeviceParameterService $p)
    {
        abort_unless((int) $device->organization_id === (int) $r->user()->organization_id, 404);
        $t = DeviceTemplate::where('organization_id', $r->user()->organization_id)->findOrFail($r->validate(['template_id' => ['required', 'integer', 'exists:device_templates,id']])['template_id']);
        $data = $r->validate(['template_id' => ['required', 'integer'], 'baseRevisionId' => ['nullable', 'integer']]);
        $s->apply($t, $device, $p, $r->user(), null, null, $data['baseRevisionId'] ?? null, $r->header('Idempotency-Key'));

        return response()->json(['applied' => true]);
    }

    private function assertCurrentOrganization(Request $request, DeviceTemplate $template): void
    {
        abort_unless((int) $template->organization_id === (int) $request->user()->organization_id, 404);
    }
}
