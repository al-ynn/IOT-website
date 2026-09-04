<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReportRunResource;
use App\Models\Report;
use App\Models\ReportRun;
use App\Services\ReportConfigurationService;
use App\Services\ReportGenerationService;
use App\Services\ReportAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportRunController extends Controller
{
    public function __construct(private ReportGenerationService $generation, private ReportConfigurationService $configuration, private ReportAccessService $access) {}

    public function store(Request $request, string $report)
    {
        $model = $this->report($request, $report);

        return (new ReportRunResource($this->generation->request($model, $request->user())->load('requester:id,name')))->response()->setStatusCode(202);
    }

    public function index(Request $request, string $report)
    {
        $model = $this->report($request, $report);
        $f = $request->validate(['per_page' => 'nullable|integer|in:25,50,100']);

        $runs = $model->runs()->with('requester:id,name');
        if (! $request->user()->isPlatformAdmin()) {
            $runs->where('requested_by', $request->user()->id);
        }

        return ReportRunResource::collection($runs->latest()->paginate($f['per_page'] ?? 25)->withQueryString());
    }

    public function show(Request $request, string $report, string $run)
    {
        return new ReportRunResource($this->run($request, $report, $run)->load('requester:id,name'));
    }

    public function download(Request $request, string $report, string $run)
    {
        $item = $this->run($request, $report, $run);
        abort_unless($item->status === 'completed' && $item->artifact_path, 404);
        $this->configuration->assertCurrentAccess($request->user(), $item->resolved_configuration);
        abort_unless(Storage::disk($item->artifact_disk)->exists($item->artifact_path), 404, 'Report artifact is unavailable.');
        $name = str($item->report->name)->slug().'-'.$item->id.'.csv';

        return Storage::disk($item->artifact_disk)->download($item->artifact_path, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function report(Request $request, string $id): Report
    {
        abort_unless($request->user()->organization && $request->user()->hasOrganizationPermission('analytics.view'), 403);

        $report = $request->user()->organization->reports()->findOrFail($id);
        abort_unless($this->access->canView($request->user(), $report), 404);

        return $report;
    }

    private function run(Request $request, string $report, string $run): ReportRun
    {
        $item = $this->report($request, $report)->runs()->with(['report', 'revision'])->findOrFail($run);
        $this->access->assertViewRun($request->user(), $item);

        return $item;
    }
}
