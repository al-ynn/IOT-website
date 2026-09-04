<?php

namespace App\Http\Controllers;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Http\Resources\WebhookResource;
use App\Models\ResourceCollaborator;
use App\Models\Webhook;
use App\Services\WebhookEventRouter;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class WebhookController extends Controller
{
    public function __construct(private WebhookService $service, private CollaborationResourceRegistry $registry) {}

    public function index(Request $r)
    {
        $org = $r->user()->organization;
        abort_unless($org, 403);
        $f = $r->validate(['search' => 'nullable|string|max:100', 'enabled' => 'nullable|boolean', 'per_page' => 'nullable|integer|in:25,50,100']);
        $ids = $r->user()->isPlatformAdmin() ? $org->webhooks()->select('id') : ResourceCollaborator::where(['resource_type' => 'webhook', 'user_id' => $r->user()->id])->select('resource_id');

        return WebhookResource::collection($org->webhooks()->whereIn('id', $ids)->with('creator:id,name')->when($f['search'] ?? null, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))->when(array_key_exists('enabled', $f), fn ($q) => $q->where('enabled', $f['enabled']))->latest()->paginate($f['per_page'] ?? 25)->withQueryString());
    }

    public function store(Request $r)
    {
        $org = $r->user()->organization;
        abort_unless($org && $r->user()->hasOrganizationPermission('device.manage'), 403);
        [$w,$secret] = $this->service->create($org, $r->user(), $this->validated($r, true));

        return response()->json(['data' => array_merge((new WebhookResource($w->load('creator:id,name')))->resolve(), ['secret' => $secret])], 201);
    }

    public function show(Request $r, string $id)
    {
        return new WebhookResource($this->resolve($r, $id, 'view')->load('creator:id,name'));
    }

    public function update(Request $r, string $id)
    {
        $webhook = $this->resolve($r, $id, 'edit');
        $data = $this->validated($r, false);
        $base = $data['baseRevisionId'] ?? null;
        unset($data['baseRevisionId']);

        return new WebhookResource($this->service->update($r->user(), $webhook, $data, $base, $r->header('Idempotency-Key'))->load('creator:id,name'));
    }

    public function destroy(Request $r, string $id)
    {
        $this->service->delete($this->resolve($r, $id, 'edit'));

        return response()->noContent();
    }

    public function rotate(Request $r, string $id)
    {
        [$w,$secret] = $this->service->rotate($this->resolve($r, $id, 'edit'));

        return response()->json(['data' => array_merge((new WebhookResource($w->load('creator:id,name')))->resolve(), ['secret' => $secret])]);
    }

    public function test(Request $r, string $id, WebhookEventRouter $router)
    {
        $w = $this->resolve($r, $id, 'edit');
        abort_unless($w->enabled && $w->approved_revision_id, 422, 'Webhook is not active with an approved revision.');
        $d = $router->dispatchToWebhook($w, 'webhook.test', ['message' => 'Webhook test delivery.']);

        return response()->json(['data' => ['deliveryId' => (string) $d->id, 'status' => 'queued']], 202);
    }

    private function resolve(Request $r, string $id, string $ability): Webhook
    {
        return $this->registry->resolve($r->user(), new CollaborationResourceReference('webhook', $id), $ability)->resource;
    }

    private function validated(Request $r, bool $create): array
    {
        return $r->validate(['name' => [Rule::requiredIf($create), 'string', 'max:120'], 'url' => [Rule::requiredIf($create), 'url', 'max:2048'], 'enabled' => ['prohibited'], 'event_types' => [Rule::requiredIf($create), 'array', 'min:1'], 'event_types.*' => [Rule::in(Webhook::EVENT_TYPES)], 'baseRevisionId' => ['sometimes', 'integer']]);
    }
}
