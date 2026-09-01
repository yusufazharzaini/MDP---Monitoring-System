<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Services\Admin\RoleService;
use Spatie\Permission\Models\Role;

/**
 * Roles are the organisation's job titles: seeded with the system, never
 * created or deleted from a screen. What an administrator may change is the
 * permissions each one carries.
 */
class RolePolicy
{
    public function __construct(
        private readonly RoleService $roles,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('user.view');
    }

    /**
     * SUPER_ADMIN is not editable - see RoleService::isProtected().
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can('user.update') && ! $this->roles->isProtected($role);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Role $role): bool
    {
        return false;
    }
}
