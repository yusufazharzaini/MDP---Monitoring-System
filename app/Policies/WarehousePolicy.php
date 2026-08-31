<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * Warehouses are governed by the warehouse.* permissions.
 */
class WarehousePolicy extends MasterDataPolicy
{
    protected function permission(): string
    {
        return 'warehouse';
    }
}
