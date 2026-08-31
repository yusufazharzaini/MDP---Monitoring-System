<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * A category is part of the material catalogue, so it shares its permissions.
 */
class MaterialCategoryPolicy extends MasterDataPolicy
{
    protected function permission(): string
    {
        return 'material';
    }
}
