<?php

namespace App\Revisions;

use App\Models\Location;

final class LocationRevisionSnapshotBuilder
{
    public const SCHEMA_VERSION = 1;

    public function build(Location $location): array
    {
        return ['metadata' => ['name' => $location->name, 'description' => $location->description]];
    }
}