<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Authorization for master-data records.
 *
 * Every master entity is gated by the same five permissions, differing only in
 * their prefix, so the rules live here once and each subclass names its module.
 * Restoring is deliberately tied to the delete permission: whoever may remove a
 * record is the one who may bring it back.
 */
abstract class MasterDataPolicy
{
    /**
     * Permission prefix, e.g. "supplier" for supplier.view / supplier.create.
     */
    abstract protected function permission(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->permission().'.view');
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can($this->permission().'.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->permission().'.create');
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can($this->permission().'.update');
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can($this->permission().'.delete');
    }

    public function restore(User $user, Model $record): bool
    {
        return $user->can($this->permission().'.delete');
    }
}
