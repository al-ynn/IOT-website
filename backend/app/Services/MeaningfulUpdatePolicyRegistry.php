<?php

namespace App\Services;

use App\Inventory\ResourceInventoryAdapterRegistry;

final class MeaningfulUpdatePolicyRegistry
{
    public function __construct(
        private ResourceInventoryAdapterRegistry $inventory,
        private ResourceSectionRegistry $sections,
    ) {}

    /** @return list<string> */
    public function types(): array
    {
        return $this->inventory->types();
    }

    /** @return list<string> */
    public function meaningfulSections(string $type): array
    {
        $this->inventory->get($type);

        return array_column($this->sections->definitions($type), 'key');
    }

    public function creationRevisionIsMeaningful(): bool
    {
        return false;
    }
}