<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupplierEvaluation;
use App\Models\User;

/**
 * Computing a scorecard and signing one off are different jobs.
 *
 * Anyone with `evaluation.create` may generate or regenerate a draft from the
 * transactions; only `evaluation.approve` freezes it, and only that same right
 * can reopen a frozen one. As elsewhere, the record's own state is half the
 * answer - an approved scorecard is closed to recomputation by everybody.
 */
class SupplierEvaluationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('evaluation.view');
    }

    public function view(User $user, SupplierEvaluation $evaluation): bool
    {
        return $user->can('evaluation.view');
    }

    /**
     * Generating covers regenerating: both write the same draft row.
     */
    public function create(User $user): bool
    {
        return $user->can('evaluation.create');
    }

    /**
     * An approved scorecard is a record of what was signed off, so it is not
     * recomputed - it is reopened first, deliberately.
     */
    public function regenerate(User $user, SupplierEvaluation $evaluation): bool
    {
        return $user->can('evaluation.create') && ! $evaluation->isApproved();
    }

    public function approve(User $user, SupplierEvaluation $evaluation): bool
    {
        return $user->can('evaluation.approve') && ! $evaluation->isApproved();
    }

    public function reopen(User $user, SupplierEvaluation $evaluation): bool
    {
        return $user->can('evaluation.approve') && $evaluation->isApproved();
    }

    /**
     * Monthly scorecards are management history and are never deleted.
     */
    public function delete(User $user, SupplierEvaluation $evaluation): bool
    {
        return false;
    }
}
