<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CorrectiveActionStatus;
use App\Models\CorrectiveAction;
use App\Models\DeliveryProblem;
use App\Models\User;

/**
 * A corrective action belongs to its problem, so the right to record one is the
 * right to work the problem (`problem.update`), not a permission of its own.
 */
class CorrectiveActionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('problem.view');
    }

    public function create(User $user, DeliveryProblem $problem): bool
    {
        return $user->can('problem.update') && $problem->status->isOpen();
    }

    public function update(User $user, CorrectiveAction $action): bool
    {
        return $user->can('problem.update');
    }

    /**
     * Completing an action is what makes closing the problem possible, so it
     * carries the same weight as working it - but never the closing right
     * itself, which stays a separate decision.
     */
    public function complete(User $user, CorrectiveAction $action): bool
    {
        return $user->can('problem.update') && $action->status !== CorrectiveActionStatus::DONE;
    }

    /**
     * A completed action is evidence a closure may rest on; it stays.
     */
    public function delete(User $user, CorrectiveAction $action): bool
    {
        return $user->can('problem.update') && $action->status !== CorrectiveActionStatus::DONE;
    }
}
