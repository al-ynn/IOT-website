<?php

namespace App\Http\Controllers;

use App\Collaboration\CollaborationResourceReference;
use App\Collaboration\CollaborationResourceRegistry;
use App\Models\Dashboard;
use App\Services\Admin\DeviceAccessService;
use App\Services\LocationAccessService;
use Illuminate\Http\Request;

final class DashboardMapProjectionController extends Controller
{
    public function __construct(private CollaborationResourceRegistry $collaboration, private DeviceAccessService $devices, private LocationAccessService $locations) {}

    public function show(Request $request, string $dashboard)
    {
        $resolved = $this->collaboration->resolve($request->user(), new CollaborationResourceReference('dashboard', $dashboard), 'view');
        abort_unless($resolved->resource instanceof Dashboard, 404);
        $configured = collect($resolved->resource->widgets)->flatMap(fn ($widget) => collect($widget->configuration['markers'] ?? [])->pluck('deviceId'))->filter()->map(fn ($id) => (int) $id)->unique();
        $query = $this->devices->accessibleDevices($request->user())->with(['canonicalLocation:id,name,latitude,longitude']);
        if ($configured->isNotEmpty()) $query->whereIn('id', $configured);
        $markers = $query->get()->filter(function ($device) use ($request) {
            if (!$device->canonicalLocation) return false;
            try { $this->locations->findViewable($request->user(), $device->canonicalLocation->id); return true; } catch (\Throwable) { return false; }
        })->map(function ($device) {
            $location = $device->canonicalLocation;
            $lat = is_numeric($location->latitude) ? (float) $location->latitude : null;
            $lng = is_numeric($location->longitude) ? (float) $location->longitude : null;
            if ($lat === null || $lng === null || !is_finite($lat) || !is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) return null;
            return ['sourceType' => 'device', 'sourceId' => (string) $device->id, 'label' => $device->name, 'status' => $device->status, 'latitude' => $lat, 'longitude' => $lng, 'locationName' => $location->name];
        })->filter()->values();
        return response()->json(['data' => $markers]);
    }
}
