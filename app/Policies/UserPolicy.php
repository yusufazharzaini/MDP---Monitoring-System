<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * User administration.
 *
 * The record's own state is part of the answer here as everywhere else, with
 * one addition: the actor's identity is too. Nobody retires their own account,
 * because the mistake is silent until they try to log in again.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user.view');
    }

    public function view(User $user, User $subject): bool
    {
        return $user->can('user.view');
    }

    public function create(User $user): bool
    {
        return $user->can('user.create');
    }

    public function update(User $user, User $subject): bool
    {
        return $user->can('user.update');
    }

    /**
     * Retiring a user soft-deletes them, so "delete" here means "revoke access
     * and keep the history".
     */
    public function delete(User $user, User $subject): bool
    {
        return $user->can('user.delete')
            && $user->getKey() !== $subject->getKey()
            && ! $subject->trashed();
    }

    public function restore(User $user, User $subject): bool
    {
        return $user->can('user.update') && $subject->trashed();
    }

    /**
     * Role assignment is part of editing a user, not a right of its own -
     * but it is the part that can hand out privilege, so it is named
     * separately to keep that visible at the call site.
     */
    public function assignRoles(User $user, User $subject): bool
    {
        return $user->can('user.update');
    }

    /**
     * Granting the super administrator role is its own act.
     *
     * It is not a permission anybody can be given: the role passes every gate
     * unconditionally, cannot have its permissions edited, and its last holder
     * cannot be demoted. Only somebody who already holds it may hand it out,
     * and never to themselves - UserService enforces the same rule for callers
     * that never touch a screen.
     */
    public function assignSuperAdmin(User $user, User $subject): bool
    {
        return $user->hasRole(RolesAndPermissionsSeeder::SUPER_ADMIN)
            && $user->getKey() !== $subject->getKey();
    }
}
