<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DeliveryProblem;
use App\Models\User;

/**
 * Reporting a problem and closing one are deliberately different jobs.
 *
 * Anyone who handles goods may raise a problem (`problem.create`), but signing
 * it off as resolved is a supervisory act (`problem.close`) - which is why
 * WAREHOUSE and PURCHASING can report and revise while LOGISTIC and MANAGEMENT
 * can close.
 *
 * As elsewhere, the record's own state is part of the answer: a closed or
 * cancelled problem is no longer open to editing by anybody.
 */
class DeliveryProblemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('problem.view');
    }

    public function view(User $user, DeliveryProblem $problem): bool
    {
        return $user->can('problem.view');
    }

    public function create(User $user): bool
    {
        return $user->can('problem.create');
    }

    public function update(User $user, DeliveryProblem $problem): bool
    {
        return $user->can('problem.update') && $problem->status->isOpen();
    }

    public function close(User $user, DeliveryProblem $problem): bool
    {
        return $user->can('problem.close') && $problem->status->isOpen();
    }

    public function cancel(User $user, DeliveryProblem $problem): bool
    {
        return $user->can('problem.close') && $problem->status->isOpen();
    }

    /**
     * Problems are never deleted - a report withdrawn in error is cancelled,
     * which keeps the fact that it was raised.
     */
    public function delete(User $user, DeliveryProblem $problem): bool
    {
        return false;
    }
}
