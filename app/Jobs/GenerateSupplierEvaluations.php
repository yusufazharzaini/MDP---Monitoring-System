<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Supplier\SupplierEvaluationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The month-end evaluation run.
 *
 * One supplier is a request; every supplier active in a month is a batch that
 * computes four component scores each from its own aggregates, and a manager
 * should not watch a spinner for it. Queued, so the screen returns at once and
 * the work lands behind it.
 */
class GenerateSupplierEvaluations implements ShouldQueue
{
    use Queueable;

    /**
     * Recomputing is idempotent by the (supplier, period) unique key and
     * approved scorecards are skipped, so a retry cannot double anything.
     */
    public int $tries = 3;

    public function __construct(
        private readonly int $year,
        private readonly int $month,
    ) {}

    public function handle(SupplierEvaluationService $evaluations): void
    {
        $evaluations->generateForAllSuppliers($this->year, $this->month);
    }

    /**
     * One run per period at a time: two overlapping batches would each try to
     * write the same rows.
     */
    public function uniqueId(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }
}
