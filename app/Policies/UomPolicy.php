<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * Units of measure are part of the material catalogue, so they share its permissions.
 */
class UomPolicy extends MasterDataPolicy
{
    protected function permission(): string
    {
        return 'material';
    }
}
