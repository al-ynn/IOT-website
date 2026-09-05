<?php

namespace App\Services;

use App\Collaboration\LocationResourceAuthorizer;
use App\Models\Location;
use App\Models\Organization;
use App\Models\ResourceCollaborator;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LocationService
{
    public function __construct(private ResourceLifecycleService $lifecycle, private ResourceRevisionService $revisions, private LocationResourceAuthorizer $authorizer, private SafeResourceSaveService $saves) {}

    public function normalize(string $name): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            throw ValidationException::withMessages(['name' => ['The Location name is required.']]);
        }

        return [$name, mb_strtolower($name, 'UTF-8')];
    }

    public function create(User $actor, Organization $org, array $data): Location
    {
        abort_unless($actor->status === 'active' && (int) $actor->organization_id === (int) $org->id, 403);
        [$name,$key] = $this->normalize($data['name']);
        if (Location::where('organization_id', $org->id)->where('normalized_name', $key)->exists()) {
            throw ValidationException::withMessages(['name' => ['A Location with this name already exists in the Organization.']]);
        }

        return DB::transaction(function () use ($actor, $org, $data, $name, $key) {
            $location = Location::create(['organization_id' => $org->id, 'name' => $name, 'normalized_name' => $key, 'description' => isset($data['description']) ? trim($data['description']) : null, 'created_by' => $actor->id, 'semantic_updated_at' => null]);
            if (! $actor->isPlatformAdmin()) {
                ResourceCollaborator::create(['resource_type' => 'location', 'resource_id' => $location->id, 'user_id' => $actor->id, 'permission' => 'edit', 'granted_by' => $actor->id]);
            }
            $this->revisions->recordLocation($location, $actor, 'Location created');

            return $location->refresh();
        });
    }

    public function update(User $actor, Location $location, array $data, int|string|null $baseRevisionId = null, ?string $idempotencyKey = null): Location
    {
        return $this->saves->execute($actor, 'location', Location::class, $location->id, $baseRevisionId,
            fn (User $user, Location $locked) => abort_unless($this->authorizer->canEdit($user, $locked), 404),
            fn (Location $locked) => $this->lifecycle->assertActive('location', $locked->id, 'Restore the Location before editing it.'),
            function (Location $locked) use ($data) {
                $name = $locked->name;
                $key = $locked->normalized_name;
                if (array_key_exists('name', $data)) {
                    [$name,$key] = $this->normalize($data['name']);
                }
                if (Location::where('organization_id', $locked->organization_id)->where('normalized_name', $key)->whereKeyNot($locked->id)->exists()) {
                    throw ValidationException::withMessages(['name' => ['A Location with this name already exists in the Organization.']]);
                }
                $description = array_key_exists('description', $data) ? ($data['description'] === null ? null : trim($data['description'])) : $locked->description;
                if ($name === $locked->name && $description === $locked->description) {
                    return $locked;
                }
                $locked->update(['name' => $name, 'normalized_name' => $key, 'description' => $description, 'semantic_updated_at' => now()]);

                return $locked;
            },
            fn (Location $locked, User $user) => $this->revisions->recordLocation($locked, $user, 'Location updated'),
            $baseRevisionId !== null, $idempotencyKey, $data);
    }

    public function assertAssignable(Organization $org, ?int $id): ?Location
    {
        if ($id === null) {
            return null;
        }
        $location = Location::whereKey($id)->where('organization_id', $org->id)->firstOrFail();
        $this->lifecycle->assertActive('location', $location->id, 'Disabled or Archived Locations cannot receive Device assignments.');

        return $location;
    }
}
