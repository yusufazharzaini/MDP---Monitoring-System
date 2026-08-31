<?php

declare(strict_types=1);

namespace App\Services\Problem;

use App\Enums\CorrectiveActionStatus;
use App\Enums\ProblemSeverity;
use App\Enums\ProblemStatus;
use App\Events\Problem\ProblemCancelled;
use App\Events\Problem\ProblemClosed;
use App\Events\Problem\ProblemReported;
use App\Events\Problem\ProblemUpdated;
use App\Exceptions\BusinessRuleException;
use App\Models\Delivery;
use App\Models\DeliveryProblem;
use App\Models\User;
use App\Services\Support\NumberGeneratorService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The delivery problem lifecycle: OPEN -> IN_PROGRESS -> CLOSED, or CANCELLED.
 *
 * The service owns `problem_number`, `status` and `closed_at` - none of them
 * are fillable on the model - because each one carries a rule the form cannot
 * enforce on its own. The rule that gives this module its point is the closing
 * one: a problem may only be closed once at least one corrective action is
 * DONE, so "resolved" always has evidence behind it.
 */
class ProblemService
{
    public function __construct(
        private readonly NumberGeneratorService $numbers,
    ) {}

    /**
     * Report a problem against a goods receipt.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function report(Delivery $delivery, array $attributes, ?User $actor = null): DeliveryProblem
    {
        $this->guardDeliveryAcceptsProblems($delivery);

        $problemDate = $this->resolveProblemDate($delivery, $attributes['problem_date'] ?? null);
        $severity = $this->resolveSeverity($attributes['severity'] ?? null);

        $this->guardMaterialBelongsToDelivery($delivery, $attributes['material_id'] ?? null);

        return DB::transaction(function () use ($delivery, $attributes, $problemDate, $severity, $actor): DeliveryProblem {
            $problem = new DeliveryProblem;

            $problem->fill([
                ...$attributes,
                'delivery_id' => $delivery->getKey(),
                // The supplier is the receipt's, never the form's: a problem
                // cannot quietly re-attribute itself to another supplier and
                // distort that supplier's performance score.
                'supplier_id' => $delivery->supplier_id,
                'problem_date' => $problemDate->toDateString(),
                'severity' => $severity,
                'due_date' => $this->resolveDueDate($attributes['due_date'] ?? null, $problemDate, $severity),
            ]);

            $problem->forceFill([
                'problem_number' => $this->numbers->problemNumber($problemDate),
                'status' => ProblemStatus::OPEN,
                'created_by' => $actor?->getKey(),
            ])->save();

            ProblemReported::dispatch($problem, $actor);

            return $problem;
        });
    }

    /**
     * Revise an open problem - its description, root cause, owner or severity.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(DeliveryProblem $problem, array $attributes, ?User $actor = null): DeliveryProblem
    {
        $this->guardEditable($problem);

        $delivery = $problem->delivery()->firstOrFail();
        $problemDate = $this->resolveProblemDate($delivery, $attributes['problem_date'] ?? $problem->problem_date);
        $severity = $this->resolveSeverity($attributes['severity'] ?? $problem->severity);

        $this->guardMaterialBelongsToDelivery(
            $delivery,
            array_key_exists('material_id', $attributes) ? $attributes['material_id'] : $problem->material_id,
        );

        return DB::transaction(function () use ($problem, $attributes, $problemDate, $severity, $actor): DeliveryProblem {
            $problem->fill([
                ...$attributes,
                'problem_date' => $problemDate->toDateString(),
                'severity' => $severity,
                'due_date' => $this->resolveDueDate($attributes['due_date'] ?? null, $problemDate, $severity),
            ])->save();

            ProblemUpdated::dispatch($problem, $actor);

            return $problem;
        });
    }

    /**
     * Close a problem.
     *
     * Requirement 15: closing requires at least one corrective action marked
     * DONE. The check is an exists() against the database rather than a scan of
     * a loaded relation, so it stays one indexed row read however many actions
     * the problem has accumulated.
     */
    public function close(DeliveryProblem $problem, ?User $actor = null, ?string $note = null): DeliveryProblem
    {
        if ($problem->status === ProblemStatus::CLOSED) {
            throw new BusinessRuleException("Problem {$problem->problem_number} sudah ditutup.");
        }

        if ($problem->status === ProblemStatus::CANCELLED) {
            throw new BusinessRuleException(
                "Problem {$problem->problem_number} sudah dibatalkan dan tidak dapat ditutup."
            );
        }

        if (! $this->hasCompletedAction($problem)) {
            throw new BusinessRuleException(
                "Problem {$problem->problem_number} tidak dapat ditutup: "
                .'minimal satu corrective action harus berstatus Done.'
            );
        }

        return DB::transaction(function () use ($problem, $actor, $note): DeliveryProblem {
            $problem->forceFill([
                'status' => ProblemStatus::CLOSED,
                'closed_at' => Carbon::now(),
                'root_cause' => $note ?? $problem->root_cause,
            ])->save();

            ProblemClosed::dispatch($problem, $actor, $note);

            return $problem;
        });
    }

    /**
     * Withdraw a problem that should never have been raised.
     *
     * The row survives - a report and its retraction are both history - but it
     * leaves the open population and stops counting towards the supplier.
     */
    public function cancel(DeliveryProblem $problem, ?User $actor = null, ?string $reason = null): DeliveryProblem
    {
        if ($problem->status === ProblemStatus::CANCELLED) {
            throw new BusinessRuleException("Problem {$problem->problem_number} sudah dibatalkan.");
        }

        if ($problem->status === ProblemStatus::CLOSED) {
            throw new BusinessRuleException(
                "Problem {$problem->problem_number} sudah ditutup dan tidak dapat dibatalkan."
            );
        }

        return DB::transaction(function () use ($problem, $actor, $reason): DeliveryProblem {
            $problem->forceFill([
                'status' => ProblemStatus::CANCELLED,
                'closed_at' => Carbon::now(),
            ])->save();

            ProblemCancelled::dispatch($problem, $actor, $reason);

            return $problem;
        });
    }

    /**
     * Move a problem to IN_PROGRESS because work has started on it.
     *
     * CorrectiveActionService calls this when the first action is recorded, so
     * the problem's status reflects what is actually happening rather than
     * waiting for somebody to remember to change it. A no-op unless the problem
     * is still OPEN.
     */
    public function markInProgress(DeliveryProblem $problem, ?User $actor = null): DeliveryProblem
    {
        if ($problem->status !== ProblemStatus::OPEN) {
            return $problem;
        }

        $problem->forceFill(['status' => ProblemStatus::IN_PROGRESS])->save();

        ProblemUpdated::dispatch($problem, $actor);

        return $problem;
    }

    public function hasCompletedAction(DeliveryProblem $problem): bool
    {
        return $problem->correctiveActions()
            ->where('status', CorrectiveActionStatus::DONE)
            ->exists();
    }

    /**
     * A cancelled receipt has been reversed; there is nothing left to report a
     * problem against.
     */
    private function guardDeliveryAcceptsProblems(Delivery $delivery): void
    {
        if ($delivery->isCancelled()) {
            throw new BusinessRuleException(
                "Delivery {$delivery->delivery_number} sudah dibatalkan, "
                .'problem tidak dapat dilaporkan terhadap delivery ini.'
            );
        }
    }

    private function guardEditable(DeliveryProblem $problem): void
    {
        if (! $problem->status->isOpen()) {
            throw new BusinessRuleException(
                "Problem {$problem->problem_number} berstatus {$problem->status->label()} "
                .'dan tidak dapat diubah.'
            );
        }
    }

    /**
     * A problem names a material only if that material actually arrived on the
     * receipt. Otherwise the Pareto chart and the critical-material rule would
     * both count a material against a delivery that never carried it.
     */
    private function guardMaterialBelongsToDelivery(Delivery $delivery, int|string|null $materialId): void
    {
        if ($materialId === null) {
            return;
        }

        $belongs = $delivery->items()->where('material_id', $materialId)->exists();

        if (! $belongs) {
            throw new BusinessRuleException(
                "Material yang dipilih tidak terdapat pada delivery {$delivery->delivery_number}."
            );
        }
    }

    /**
     * A problem is observed when the goods are handled: never before they
     * arrived, never in the future.
     */
    private function resolveProblemDate(Delivery $delivery, mixed $value): Carbon
    {
        $date = $value === null
            ? Carbon::now()->startOfDay()
            : Carbon::parse((string) $value)->startOfDay();

        if ($date->isAfter(Carbon::now()->startOfDay())) {
            throw new BusinessRuleException('Tanggal problem tidak boleh berada di masa depan.');
        }

        $arrival = $delivery->delivery_date?->copy()->startOfDay();

        if ($arrival !== null && $date->isBefore($arrival)) {
            throw new BusinessRuleException(
                'Tanggal problem tidak boleh mendahului tanggal delivery '
                .$arrival->toDateString().'.'
            );
        }

        return $date;
    }

    private function resolveSeverity(mixed $value): ProblemSeverity
    {
        return match (true) {
            $value instanceof ProblemSeverity => $value,
            $value === null => ProblemSeverity::MEDIUM,
            default => ProblemSeverity::from((string) $value),
        };
    }

    /**
     * The due date defaults to the severity's resolution window, which is what
     * makes "overdue" mean something for a problem nobody set a date on.
     */
    private function resolveDueDate(mixed $given, Carbon $problemDate, ProblemSeverity $severity): string
    {
        if ($given === null) {
            return $problemDate->copy()->addDays($severity->resolutionDays())->toDateString();
        }

        $due = Carbon::parse((string) $given)->startOfDay();

        if ($due->isBefore($problemDate)) {
            throw new BusinessRuleException('Target penyelesaian tidak boleh mendahului tanggal problem.');
        }

        return $due->toDateString();
    }
}
