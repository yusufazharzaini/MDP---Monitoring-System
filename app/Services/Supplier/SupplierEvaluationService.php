<?php

declare(strict_types=1);

namespace App\Services\Supplier;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\EvaluationStatus;
use App\Enums\SupplierGrade;
use App\Events\Supplier\SupplierEvaluationApproved;
use App\Events\Supplier\SupplierEvaluationReopened;
use App\Exceptions\BusinessRuleException;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use App\Models\User;
use App\Repositories\DashboardRepository;
use App\Services\Performance\DeliveryPerformanceService;
use App\Services\Setting\KpiSettingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Turns a month of transactions into a supplier scorecard.
 *
 * An evaluation is a signed-off snapshot, not a cache: it records the scores a
 * manager approved for a closed month. The live dashboard never reads it, so a
 * later data correction changes today's dashboard without silently rewriting
 * last quarter's approved scorecard.
 */
class SupplierEvaluationService
{
    /**
     * Scoring weights, summing to 100.
     */
    private const CRITERIA = [
        'Delivery' => 40.0,
        'Quality' => 25.0,
        'Quantity' => 25.0,
        'Responsiveness' => 10.0,
    ];

    public function __construct(
        private readonly DeliveryPerformanceService $performance,
        private readonly DashboardRepository $repository,
        private readonly KpiSettingService $kpi,
    ) {}

    /**
     * Compute a supplier's four component scores for one month, from
     * transactions alone.
     *
     * @return array<string, float>
     */
    public function calculateScores(Supplier $supplier, int $year, int $month): array
    {
        $filter = $this->periodFilter($supplier, $year, $month);
        $metrics = $this->performance->metrics($filter);

        return [
            'delivery_score' => $metrics->onTimeRate(),
            'quantity_score' => $metrics->quantityFulfillment(),
            'quality_score' => $this->qualityScore($filter, $metrics->totalDelivery),
            'responsiveness_score' => $this->responsivenessScore($filter),
        ];
    }

    /**
     * The weighted total of the four component scores.
     *
     * @param  array<string, float>  $scores
     */
    public function calculateTotalScore(array $scores): float
    {
        $weighted =
            ($scores['delivery_score'] ?? 0.0) * self::CRITERIA['Delivery']
            + ($scores['quality_score'] ?? 0.0) * self::CRITERIA['Quality']
            + ($scores['quantity_score'] ?? 0.0) * self::CRITERIA['Quantity']
            + ($scores['responsiveness_score'] ?? 0.0) * self::CRITERIA['Responsiveness'];

        return round($weighted / array_sum(self::CRITERIA), 4);
    }

    public function gradeFor(float $totalScore): SupplierGrade
    {
        return $this->kpi->gradeFor($totalScore);
    }

    /**
     * Generate - or regenerate - one supplier's evaluation for a month.
     *
     * Idempotent by the (supplier, year, month) unique key, and transactional
     * so a scorecard never exists without its criteria breakdown.
     */
    public function generateForPeriod(Supplier $supplier, int $year, int $month, ?string $remarks = null): SupplierEvaluation
    {
        $this->guardPeriod($year, $month);
        $this->guardSupplierWasActive($supplier, $year, $month);

        $scores = $this->calculateScores($supplier, $year, $month);
        $total = $this->calculateTotalScore($scores);

        return DB::transaction(function () use ($supplier, $year, $month, $scores, $total, $remarks): SupplierEvaluation {
            $evaluation = SupplierEvaluation::query()->firstOrNew([
                'supplier_id' => $supplier->getKey(),
                'period_year' => $year,
                'period_month' => $month,
            ]);

            /*
             * A signed-off scorecard is a record of what a manager approved,
             * not a cache of the current numbers. Regenerating one would let a
             * data correction months later silently restate a figure somebody
             * put their name to, so it is refused - reopening is an explicit,
             * audited act with its own permission.
             */
            if ($evaluation->exists && $evaluation->isApproved()) {
                throw new BusinessRuleException(
                    "Evaluasi {$supplier->code} periode "
                    .sprintf('%04d-%02d', $year, $month)
                    .' sudah disetujui dan tidak dapat dihitung ulang. Buka kembali evaluasi terlebih dahulu.'
                );
            }

            $evaluation->forceFill([
                ...$scores,
                'total_score' => $total,
                'grade' => $this->gradeFor($total),
                'remarks' => $remarks ?? $evaluation->remarks,
                'created_by' => $evaluation->created_by ?? Auth::id(),
            ])->save();

            $evaluation->items()->delete();
            $evaluation->items()->createMany($this->criteriaRows($scores));

            return $evaluation->refresh();
        });
    }

    /**
     * Generate evaluations for every supplier that was active in the month.
     *
     * @return array<int, SupplierEvaluation>
     */
    public function generateForAllSuppliers(int $year, int $month): array
    {
        $this->guardPeriod($year, $month);

        $from = Carbon::create($year, $month, 1)->startOfMonth();

        $approved = SupplierEvaluation::query()
            ->forPeriod($year, $month)
            ->approved()
            ->pluck('supplier_id')
            ->all();

        return Supplier::query()
            ->activeInPeriod($from->toDateString(), $from->copy()->endOfMonth()->toDateString())
            // A month-end batch skips the scorecards already signed off rather
            // than failing on the first one; the rest of the run still lands.
            ->whereNotIn('id', $approved === [] ? [0] : $approved)
            ->get()
            ->map(fn (Supplier $supplier): SupplierEvaluation => $this->generateForPeriod($supplier, $year, $month))
            ->all();
    }

    /**
     * Sign off a scorecard, freezing its figures.
     *
     * From here the evaluation stops tracking the transactions underneath it,
     * which is the whole point: last quarter's approved grade must not move
     * because a receipt was corrected this morning.
     */
    public function approve(SupplierEvaluation $evaluation, ?User $actor = null): SupplierEvaluation
    {
        if ($evaluation->isApproved()) {
            throw new BusinessRuleException(
                'Evaluasi periode '.$evaluation->periodLabel().' sudah disetujui.'
            );
        }

        return DB::transaction(function () use ($evaluation, $actor): SupplierEvaluation {
            $evaluation->forceFill([
                'status' => EvaluationStatus::APPROVED,
                'approved_by' => $actor?->getKey() ?? Auth::id(),
                'approved_at' => Carbon::now(),
            ])->save();

            SupplierEvaluationApproved::dispatch($evaluation, $actor);

            return $evaluation;
        });
    }

    /**
     * Return an approved scorecard to DRAFT so it can be recomputed.
     *
     * The reason is required and audited: reopening a signed-off month is a
     * management decision, and the trail has to say why the figures moved.
     */
    public function reopen(SupplierEvaluation $evaluation, ?User $actor = null, ?string $reason = null): SupplierEvaluation
    {
        if (! $evaluation->isApproved()) {
            throw new BusinessRuleException(
                'Evaluasi periode '.$evaluation->periodLabel().' masih berstatus draft.'
            );
        }

        return DB::transaction(function () use ($evaluation, $actor, $reason): SupplierEvaluation {
            $evaluation->forceFill([
                'status' => EvaluationStatus::DRAFT,
                'approved_by' => null,
                'approved_at' => null,
                'remarks' => $reason ?? $evaluation->remarks,
            ])->save();

            SupplierEvaluationReopened::dispatch($evaluation, $actor, $reason);

            return $evaluation;
        });
    }

    /**
     * Quality: the share of receipts that arrived without a problem being
     * raised against their delivery, penalised by severity.
     */
    private function qualityScore(DashboardFilter $filter, int $totalDelivery): float
    {
        if ($totalDelivery <= 0) {
            return 0.0;
        }

        $penalty = $this->repository->problemFrequency($filter)->sum(
            static fn (object $row): int => (int) $row->problem_count,
        );

        return round(max(0.0, 100.0 - ($penalty / $totalDelivery * 100)), 2);
    }

    /**
     * Responsiveness: the share of problems closed by their due date.
     *
     * A supplier with no problems in the month scores 100 - there was nothing
     * to respond to, which is the best possible outcome.
     */
    private function responsivenessScore(DashboardFilter $filter): float
    {
        $row = $this->repository->problemResolution($filter);

        $total = (int) ($row->total_problems ?? 0);

        if ($total <= 0) {
            return 100.0;
        }

        return round((int) ($row->resolved_on_time ?? 0) / $total * 100, 2);
    }

    /**
     * @param  array<string, float>  $scores
     * @return array<int, array<string, mixed>>
     */
    private function criteriaRows(array $scores): array
    {
        $map = [
            'Delivery' => $scores['delivery_score'] ?? 0.0,
            'Quality' => $scores['quality_score'] ?? 0.0,
            'Quantity' => $scores['quantity_score'] ?? 0.0,
            'Responsiveness' => $scores['responsiveness_score'] ?? 0.0,
        ];

        return array_map(
            static fn (string $criteria): array => [
                'criteria_name' => $criteria,
                'weight' => self::CRITERIA[$criteria],
                'score' => $map[$criteria],
            ],
            array_keys(self::CRITERIA),
        );
    }

    private function periodFilter(Supplier $supplier, int $year, int $month): DashboardFilter
    {
        $anchor = Carbon::create($year, $month, 1)->startOfMonth();

        return new DashboardFilter(
            dateFrom: $anchor->toDateString(),
            dateTo: $anchor->copy()->endOfMonth()->toDateString(),
            supplierId: $supplier->getKey(),
        );
    }

    /**
     * A supplier that delivered nothing in the month is not evaluated.
     *
     * Scoring one anyway produces a scorecard that reads as terrible
     * performance when it means no activity: delivery, quantity and quality
     * are all zero for want of data, while responsiveness scores full marks
     * because no problem could be raised against a delivery that never
     * happened. The ranking already omits these suppliers; a signed-off
     * scorecard must not contradict it.
     */
    private function guardSupplierWasActive(Supplier $supplier, int $year, int $month): void
    {
        $metrics = $this->performance->metrics($this->periodFilter($supplier, $year, $month));

        if ($metrics->totalDelivery <= 0) {
            throw new BusinessRuleException(
                "Supplier {$supplier->code} tidak memiliki penerimaan pada periode "
                .sprintf('%04d-%02d', $year, $month)
                .', sehingga tidak dapat dievaluasi.'
            );
        }
    }

    private function guardPeriod(int $year, int $month): void
    {
        if ($month < 1 || $month > 12) {
            throw new BusinessRuleException("Bulan evaluasi tidak valid: {$month}.");
        }

        if ($year < 2000 || $year > 2100) {
            throw new BusinessRuleException("Tahun evaluasi tidak valid: {$year}.");
        }
    }
}
