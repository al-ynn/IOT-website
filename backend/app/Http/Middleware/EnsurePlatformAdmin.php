<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\ResourcePublicationSubmission;
use App\Models\ResourceShareRequest;
use App\Models\Device;
use App\Services\ReviewDomainRegistry;
use App\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();
        abort_unless($admin?->isAdmin(), 403, 'Administrator access is required.');
        $organization = app(CurrentOrganization::class)->require($admin);

        // Admin is an organization-scoped role. Client-supplied scope can narrow
        // only to the current organization; it can never select another tenant.
        if ($request->has('organization_id')) {
            abort_unless((string) $request->input('organization_id') === (string) $organization->id, 404);
        }
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            $request->merge(['organization_id' => $organization->id]);
        }

        foreach (['device_id' => Device::class] as $key => $model) {
            if ($request->filled($key)) {
                abort_unless($model::query()
                    ->where('organization_id', $admin->organization_id)
                    ->whereKey($request->input($key))
                    ->exists(), 404);
            }
        }

        // Route-bound tenant models receive the same defense before a controller
        // or service can act on them. Controllers resolving scalar IDs must still
        // query through the current organization.
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model && $parameter->getAttribute('organization_id') !== null) {
                abort_unless((string) $parameter->getAttribute('organization_id') === (string) $admin->organization_id, 404);
            }
            if ($parameter instanceof ResourcePublicationSubmission) {
                $definition = app(ReviewDomainRegistry::class)->definition($parameter->resource_type);
                abort_unless($definition['model']::query()
                    ->where('organization_id', $admin->organization_id)
                    ->whereKey($parameter->resource_id)
                    ->exists(), 404);
            }
            if ($parameter instanceof ResourceShareRequest) {
                abort_unless($parameter->sender()->where('organization_id', $admin->organization_id)->exists(), 404);
            }
        }

        return $next($request);
    }
}
