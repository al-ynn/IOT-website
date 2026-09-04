<?php

namespace App\Http\Controllers;

use App\Services\ResourceLifecycleService;
use Illuminate\Http\Request;

final class ResourceLifecycleController extends Controller
{
    public function __construct(private ResourceLifecycleService $lifecycle) {}

    public function show(Request $request, string $type, int $id)
    {
        return ['data' => $this->lifecycle->show($request->user(), $type, $id)];
    }

    public function disable(Request $request, string $type, int $id)
    {
        $v = $this->transitionInput($request);

        return ['data' => $this->lifecycle->disable($request->user(), $type, $id, $v['expectedLifecycle'] ?? null, $v['lifecycleGeneration'] ?? null)];
    }

    public function restore(Request $request, string $type, int $id)
    {
        $v = $this->transitionInput($request);

        return ['data' => $this->lifecycle->restore($request->user(), $type, $id, $v['expectedLifecycle'] ?? null, $v['lifecycleGeneration'] ?? null)];
    }

    public function archive(Request $request, string $type, int $id)
    {
        $v = $this->transitionInput($request);

        return ['data' => $this->lifecycle->archive($request->user(), $type, $id, $v['expectedLifecycle'] ?? null, $v['lifecycleGeneration'] ?? null)];
    }

    private function transitionInput(Request $request): array
    {
        return $request->validate(['expectedLifecycle' => ['nullable', 'string', 'in:active,disabled,archived'], 'lifecycleGeneration' => ['nullable', 'integer', 'min:1'], 'target' => ['prohibited'], 'actorId' => ['prohibited'], 'role' => ['prohibited'], 'reactivate' => ['prohibited'], 'regrant' => ['prohibited'], 'resumeOldJobs' => ['prohibited'], 'resetGeneration' => ['prohibited'], 'disabled_by' => ['prohibited'], 'restored_by' => ['prohibited'], 'disabled_at' => ['prohibited'], 'restored_at' => ['prohibited']]);
    }
}
