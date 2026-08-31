<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * Departments are organisational data, administered under the user.* permissions.
 */
class DepartmentPolicy extends MasterDataPolicy
{
    protected function permission(): string
    {
        return 'user';
    }
}
