<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * A contact belongs to its supplier, so it inherits the supplier.* permissions.
 */
class SupplierContactPolicy extends MasterDataPolicy
{
    protected function permission(): string
    {
        return 'supplier';
    }
}
