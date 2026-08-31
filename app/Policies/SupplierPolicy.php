<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * Suppliers are governed by the supplier.* permissions.
 */
class SupplierPolicy extends MasterDataPolicy
{
    protected function permission(): string
    {
        return 'supplier';
    }
}
