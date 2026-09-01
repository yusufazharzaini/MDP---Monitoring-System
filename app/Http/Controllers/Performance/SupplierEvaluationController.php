<?php

declare(strict_types=1);

namespace App\Http\Controllers\Performance;

use App\Enums\EvaluationStatus;
use App\Enums\SupplierGrade;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\GenerateEvaluationRequest;
use App\Http\Requests\Performance\ReopenEvaluationRequest;
use App\Jobs\GenerateSupplierEvaluations;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use App\Models\SupplierEvaluationItem;
use App\Services\Supplier\SupplierEvaluationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The monthly scorecard register.
 *
 * A scorecard is generated from transactions while it is a DRAFT and frozen
 * once approved, so the figures a manager signed off cannot be restated by a
 * data correction months later.
 */
class SupplierEvaluationController extends Controller
{
    public function __construct(
        private readonly SupplierEvaluationService $evaluations,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SupplierEvaluation::class);

        $records = SupplierEvaluation::query()
            ->withListRelations()
            ->when(
                $request->filled('period'),
                fn (Builder $q) => $q
                    ->where('period_year', (int) substr($request->string('period')->toString(), 0, 4))
                    ->where('period_month', (int) substr($request->string('period')->toString(), 5, 2)),
            )
            ->when(
                $request->filled('status'),
                fn (Builder $q) => $q->where('status', $request->string('status')->toString()),
            )
            ->when(
                $request->filled('grade'),
                fn (Builder $q) => $q->where('grade', $request->string('grade')->toString()),
            )
            ->when(
                $request->filled('supplier_id'),
                fn (Builder $q) => $q->where('supplier_id', $request->integer('supplier_id')),
            )
            ->latestPeriodFirst()
            ->orderByDesc('total_score')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SupplierEvaluation $e): array => $this->summarise($e));

        return Inertia::render('SupplierEvaluations/Index', [
            'records' => $records,
            'filters' => $request->only(['period', 'status', 'grade', 'supplier_id']),
            'options' => [
                'statuses' => EvaluationStatus::options(),
                'grades' => SupplierGrade::options(),
                'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'code', 'name'])
                    ->map(fn (Supplier $s): array => ['value' => $s->id, 'label' => $s->code.' - '.$s->name])->all(),
                // The month picker never offers a month that has not happened.
                'latestPeriod' => Carbon::now()->format('Y-m'),
            ],
            'can' => ['create' => $request->user()?->can('create', SupplierEvaluation::class) ?? false],
        ]);
    }

    /**
     * Generate or regenerate scorecards for a month.
     *
     * With a supplier, just that one; without, every supplier active in the
     * month, skipping the ones already signed off.
     */
    public function store(GenerateEvaluationRequest $request): RedirectResponse
    {
        $this->authorize('create', SupplierEvaluation::class);

        $year = $request->year();
        $month = $request->month();

        if ($request->filled('supplier_id')) {
            $supplier = Supplier::query()->findOrFail($request->integer('supplier_id'));

            // The policy answers whether this particular scorecard may be
            // recomputed; the service repeats the check for callers with no form.
            $existing = SupplierEvaluation::query()
                ->where('supplier_id', $supplier->getKey())
                ->forPeriod($year, $month)
                ->first();

            if ($existing !== null) {
                $this->authorize('regenerate', $existing);
            }

            $evaluation = $this->evaluations->generateForPeriod(
                $supplier,
                $year,
                $month,
                $request->string('remarks')->toString() ?: null,
            );

            return redirect()
                ->route('supplier-evaluations.show', $evaluation->getKey())
                ->with('success', "Evaluasi {$supplier->code} periode {$evaluation->periodLabel()} dihitung.");
        }

        /*
         * Queued: a month across every active supplier is four aggregate scores
         * each, and a manager should not hold a request open for it. On the
         * sync driver this still runs inline, so a small deployment needs no
         * worker to function.
         */
        GenerateSupplierEvaluations::dispatch($year, $month);

        return redirect()
            ->route('supplier-evaluations.index', ['period' => $request->string('period')->toString()])
            ->with('success', 'Perhitungan evaluasi periode '
                .$request->string('period')->toString().' sedang diproses.');
    }

    public function show(Request $request, SupplierEvaluation $evaluation): Response
    {
        $this->authorize('view', $evaluation);

        $evaluation->load([
            'supplier:id,ulid,code,name,short_name,supplier_type',
            'items',
            'creator:id,name',
            'approver:id,name',
        ]);

        return Inertia::render('SupplierEvaluations/Show', [
            'record' => [
                ...$this->summarise($evaluation),
                // The regenerate button needs it: without a supplier the store
                // action takes its batch branch and recomputes the whole month.
                'supplier_id' => $evaluation->supplier_id,
                'supplier_ulid' => $evaluation->supplier?->ulid,
                'remarks' => $evaluation->remarks,
                'created_by' => $evaluation->creator?->name,
                'approved_at' => $evaluation->approved_at?->toDateTimeString(),
                'criteria' => $evaluation->items
                    ->map(fn (SupplierEvaluationItem $item): array => [
                        'criteria_name' => $item->criteria_name,
                        'weight' => (float) $item->weight,
                        'score' => (float) $item->score,
                        // The contribution, computed here so the page adds
                        // nothing up itself.
                        'weighted' => round((float) $item->score * (float) $item->weight / 100, 2),
                    ])->all(),
            ],
            'can' => [
                'regenerate' => $request->user()?->can('regenerate', $evaluation) ?? false,
                'approve' => $request->user()?->can('approve', $evaluation) ?? false,
                'reopen' => $request->user()?->can('reopen', $evaluation) ?? false,
            ],
        ]);
    }

    public function approve(Request $request, SupplierEvaluation $evaluation): RedirectResponse
    {
        $this->authorize('approve', $evaluation);

        $this->evaluations->approve($evaluation, $request->user());

        return back()->with('success', "Evaluasi periode {$evaluation->periodLabel()} disetujui.");
    }

    public function reopen(ReopenEvaluationRequest $request, SupplierEvaluation $evaluation): RedirectResponse
    {
        $this->authorize('reopen', $evaluation);

        $this->evaluations->reopen($evaluation, $request->user(), $request->string('reason')->toString());

        return back()->with('success', "Evaluasi periode {$evaluation->periodLabel()} dibuka kembali.");
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(SupplierEvaluation $evaluation): array
    {
        return [
            'id' => $evaluation->getKey(),
            'period' => $evaluation->periodLabel(),
            'supplier_code' => $evaluation->supplier?->code,
            'supplier_name' => $evaluation->supplier?->displayName(),
            'delivery_score' => $evaluation->delivery_score,
            'quality_score' => $evaluation->quality_score,
            'quantity_score' => $evaluation->quantity_score,
            'responsiveness_score' => $evaluation->responsiveness_score,
            'total_score' => $evaluation->total_score,
            'grade' => $evaluation->grade->value,
            'grade_label' => $evaluation->grade->label(),
            'grade_variant' => $evaluation->grade->badgeVariant(),
            'status' => $evaluation->status->value,
            'status_label' => $evaluation->status->label(),
            'status_variant' => $evaluation->status->badgeVariant(),
            'approved_by' => $evaluation->approver?->name,
            'criteria_count' => $evaluation->items_count ?? 0,
        ];
    }
}
