<?php

namespace App\Http\Controllers;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Models\Dashboard;
use App\Models\DashboardMapAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class DashboardMapAssetController extends Controller
{
    public function __construct(private CollaborationResourceRegistry $collaboration) {}

    public function store(Request $request, string $dashboard)
    {
        $resolved = $this->collaboration->resolve($request->user(), new CollaborationResourceReference('dashboard', $dashboard), 'edit');
        $dashboardModel = $resolved->resource;
        abort_unless($dashboardModel instanceof Dashboard && $dashboardModel->scope_type === 'personal', 404);
        $request->validate(['asset' => ['required', 'file', 'max:10240', 'mimetypes:image/png,image/jpeg,image/webp']]);
        $file = $request->file('asset');
        $dimensions = @getimagesize($file->getRealPath());
        abort_unless(is_array($dimensions) && $dimensions[0] > 0 && $dimensions[1] > 0 && $dimensions[0] <= 10000 && $dimensions[1] <= 10000, 422, 'The image dimensions are invalid or exceed the supported limit.');
        $path = 'dashboard-map-assets/'.$dashboardModel->id.'/'.Str::uuid().'.'.strtolower($file->extension());
        Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));
        $asset = DashboardMapAsset::create(['dashboard_id' => $dashboardModel->id, 'uploaded_by' => $request->user()->id, 'disk' => 'local', 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'width' => $dimensions[0], 'height' => $dimensions[1]]);
        return response()->json(['data' => $this->data($asset)], 201);
    }

    public function show(Request $request, string $asset)
    {
        $item = DashboardMapAsset::with('dashboard')->findOrFail($asset);
        $resolved = $this->collaboration->resolve($request->user(), new CollaborationResourceReference('dashboard', (string) $item->dashboard_id), 'view');
        abort_unless($resolved->resource instanceof Dashboard, 404);
        abort_unless(Storage::disk($item->disk)->exists($item->path), 404);
        return Storage::disk($item->disk)->response($item->path, basename($item->path), ['Content-Type' => $item->mime_type, 'Content-Disposition' => 'inline; filename="'.basename($item->path).'"', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=60']);
    }

    private function data(DashboardMapAsset $asset): array
    {
        return ['id' => (string) $asset->id, 'mimeType' => $asset->mime_type, 'size' => $asset->size, 'width' => $asset->width, 'height' => $asset->height, 'url' => '/api/dashboard-map-assets/'.$asset->id];
    }
}
