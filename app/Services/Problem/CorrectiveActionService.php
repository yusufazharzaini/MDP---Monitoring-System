<?php

declare(strict_types=1);

namespace App\Services\Problem;

use App\Enums\CorrectiveActionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CorrectiveAction;
use App\Models\DeliveryProblem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Corrective actions - what was actually done about a problem.
 *
 * `status` and `completed_at` are not fillable: they move together, and an
 * action marked DONE is the evidence ProblemService requires before a problem
 * may be closed, so nothing may set it by mass assignment.
 */
class CorrectiveActionService
{
    public function __construct(
        private readonly ProblemService $problems,
    ) {}

    /**
     * Record an action against an open problem.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function add(DeliveryProblem $problem, array $attributes, ?User $actor = null): CorrectiveAction
    {
        $this->guardProblemOpen($problem);

        $actionDate = $this->resolveActionDate($problem, $attributes['action_date'] ?? null);
        $dueDate = $this->resolveDueDate($attributes['due_date'] ?? null, $actionDate);

        return DB::transaction(function () use ($problem, $attributes, $actionDate, $dueDate, $actor): CorrectiveAction {
            $action = new CorrectiveAction;

            $action->fill([
                ...$attributes,
                'delivery_problem_id' => $problem->getKey(),
                'action_date' => $actionDate->toDateString(),
                'due_date' => $dueDate,
                // Whoever is not named on the form is whoever recorded it.
                'action_by' => $attributes['action_by'] ?? $actor?->getKey(),
            ]);

            $action->forceFill(['status' => CorrectiveActionStatus::OPEN])->save();

            // Recording the first action is the moment work starts, so the
            // problem stops being merely OPEN.
            $this->problems->markInProgress($problem, $actor);

            return $action;
        });
    }

    /**
     * Revise an action's plan. Its status is not part of this - use
     * markInProgress() or complete().
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(CorrectiveAction $action, array $attributes, ?User $actor = null): CorrectiveAction
    {
        $problem = $action->problem()->firstOrFail();
        $this->guardProblemOpen($problem);

        $actionDate = $this->resolveActionDate($problem, $attributes['action_date'] ?? $action->action_date);

        $action->fill([
            ...$attributes,
            'action_date' => $actionDate->toDateString(),
            'due_date' => $this->resolveDueDate($attributes['due_date'] ?? null, $actionDate),
        ])->save();

        return $action;
    }

    /**
     * Mark an action as under way.
     */
    public function markInProgress(CorrectiveAction $action, ?User $actor = null): CorrectiveAction
    {
        $problem = $action->problem()->firstOrFail();
        $this->guardProblemOpen($problem);

        if ($action->status === CorrectiveActionStatus::DONE) {
            throw new BusinessRuleException('Corrective action yang sudah selesai tidak dapat dibuka kembali.');
        }

        $action->forceFill(['status' => CorrectiveActionStatus::IN_PROGRESS])->save();

        $this->problems->markInProgress($problem, $actor);

        return $action;
    }

    /**
     * Mark an action as done.
     *
     * This does not close the parent problem: closing is a separate decision
     * with its own permission (`problem.close`). What it does is make closing
     * possible, because it is the completed action the closing rule looks for.
     */
    public function complete(CorrectiveAction $action, ?User $actor = null): CorrectiveAction
    {
        $problem = $action->problem()->firstOrFail();
        $this->guardProblemOpen($problem);

        if ($action->status === CorrectiveActionStatus::DONE) {
            throw new BusinessRuleException('Corrective action ini sudah berstatus Done.');
        }

        return DB::transaction(function () use ($action, $problem, $actor): CorrectiveAction {
            $action->forceFill([
                'status' => CorrectiveActionStatus::DONE,
                'completed_at' => Carbon::now(),
            ])->save();

            $this->problems->markInProgress($problem, $actor);

            return $action;
        });
    }

    /**
     * Remove an action recorded in error.
     *
     * A completed action is never removed: a closed problem may be resting on
     * it, and deleting the evidence would leave that closure unexplained.
     */
    public function remove(CorrectiveAction $action): void
    {
        $problem = $action->problem()->firstOrFail();
        $this->guardProblemOpen($problem);

        if ($action->status === CorrectiveActionStatus::DONE) {
            throw new BusinessRuleException(
                'Corrective action yang sudah selesai tidak dapat dihapus.'
            );
        }

        $action->delete();
    }

    private function guardProblemOpen(DeliveryProblem $problem): void
    {
        if (! $problem->status->isOpen()) {
            throw new BusinessRuleException(
                "Problem {$problem->problem_number} berstatus {$problem->status->label()}; "
                .'corrective action tidak dapat diubah.'
            );
        }
    }

    /**
     * An action is taken after the problem was reported, and never in the
     * future - a plan that has not happened yet is a due date, not an action.
     */
    private function resolveActionDate(DeliveryProblem $problem, mixed $value): Carbon
    {
        $date = $value === null
            ? Carbon::now()->startOfDay()
            : Carbon::parse((string) $value)->startOfDay();

        if ($date->isAfter(Carbon::now()->startOfDay())) {
            throw new BusinessRuleException('Tanggal corrective action tidak boleh berada di masa depan.');
        }

        $reported = $problem->problem_date?->copy()->startOfDay();

        if ($reported !== null && $date->isBefore($reported)) {
            throw new BusinessRuleException(
                'Tanggal corrective action tidak boleh mendahului tanggal problem '
                .$reported->toDateString().'.'
            );
        }

        return $date;
    }

    private function resolveDueDate(mixed $given, Carbon $actionDate): ?string
    {
        if ($given === null) {
            return null;
        }

        $due = Carbon::parse((string) $given)->startOfDay();

        if ($due->isBefore($actionDate)) {
            throw new BusinessRuleException(
                'Target penyelesaian tidak boleh mendahului tanggal corrective action.'
            );
        }

        return $due->toDateString();
    }
}
