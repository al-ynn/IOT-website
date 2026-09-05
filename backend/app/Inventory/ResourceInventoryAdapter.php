<?php

namespace App\Inventory;

final readonly class ResourceInventoryAdapter
{
    public function __construct(
        public string $type,
        public string $label,
        public string $table,
        public string $creatorColumn,
        public string $adminDestination,
        public bool $softDeletes = false,
    ) {}

    public function destination(string $id): string
    {
        return str_replace('{id}', rawurlencode($id), $this->adminDestination);
    }
}
