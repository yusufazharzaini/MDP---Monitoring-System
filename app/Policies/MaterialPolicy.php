<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * Materials are governed by the material.* permissions.
 */
class MaterialPolicy extends MasterDataPolicy
{
    protected function permission(): string
    {
        return 'material';
    }
}
