<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * Plants are governed by the plant.* permissions.
 */
class PlantPolicy extends MasterDataPolicy
{
    protected function permission(): string
    {
        return 'plant';
    }
}
