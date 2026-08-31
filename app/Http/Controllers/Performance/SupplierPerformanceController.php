<?php

declare(strict_types=1);

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\PerformanceFilterRequest;
use App\Models\MaterialCategory;
use App\Models\Plant;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use App\Services\Performance\SupplierPerformanceService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The supplier ranking and the per-supplier scorecard.
 *
 * Both read entirely from SupplierPerformanceService, which computes in the
 * database - the pages format numbers and never derive one.
 */
class SupplierPerformanceController extends Controller
{
    public function __construct(
        private readonly SupplierPerformanceService $performance,
    ) {}

    /**
     * Every supplier active in the period, ranked.
     *
     * Unlike the dashboard's top-five panel this is the whole list, because
     * the supplier nobody wants to see is at the bottom of it.
     */
    public function index(PerformanceFilterRequest $request): Response
    {
        $filter = $request->toFilter();
        $ranking = $this->performance->getSupplierRanking($filter);

        return Inertia::render('SupplierPerformance/Index', [
            'filters' => $filter->toArray(),
            'ranking' => $ranking->all(),
            'thresholds' => $this->performance->gradeBands(),
            'options' => $this->filterOptions(),
        ]);
    }

    /**
     * One supplier's scorecard: the period's counts, its grade, six months of
     * trend and where its problems concentrate.
     */
    public function show(PerformanceFilterRequest $request, Supplier $supplier): Response
    {
        $filter = $request->toFilter();

        return Inertia::render('SupplierPerformance/Show', [
            'scorecard' => $this->performance->getSupplierScorecard($supplier, $filter),
            'filters' => $filter->toArray(),
            // The signed-off history beside the live figures, so a reader can
            // see where today's numbers differ from what was approved.
            'evaluations' => SupplierEvaluation::query()
                ->where('supplier_id', $supplier->getKey())
                ->with('approver:id,name')
                ->latestPeriodFirst()
                ->limit(12)
                ->get()
                ->map(fn (SupplierEvaluation $evaluation): array => [
                    'id' => $evaluation->getKey(),
                    'period' => $evaluation->periodLabel(),
                    'total_score' => $evaluation->total_score,
                    'grade_label' => $evaluation->grade->label(),
                    'grade_variant' => $evaluation->grade->badgeVariant(),
                    'status_label' => $evaluation->status->label(),
                    'status_variant' => $evaluation->status->badgeVariant(),
                    'approved_by' => $evaluation->approver?->name,
                ])->all(),
            'can' => [
                'viewEvaluations' => $request->user()?->can('evaluation.view') ?? false,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'plants' => Plant::query()->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn (Plant $p): array => ['value' => $p->id, 'label' => $p->code.' - '.$p->name])->all(),
            'materialCategories' => MaterialCategory::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (MaterialCategory $c): array => ['value' => $c->id, 'label' => $c->name])->all(),
        ];
    }
}
