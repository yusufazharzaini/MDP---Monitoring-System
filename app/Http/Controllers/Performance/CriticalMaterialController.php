<?php

declare(strict_types=1);

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\PerformanceFilterRequest;
use App\Models\MaterialCategory;
use App\Models\Plant;
use App\Services\Dashboard\CriticalMaterialService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The critical material watchlist.
 *
 * The dashboard shows the count; this screen is the list behind it, with the
 * reasons each material qualified. Every rule is evaluated in
 * CriticalMaterialService against the four configurable flags, so the page
 * never decides for itself what "critical" means.
 */
class CriticalMaterialController extends Controller
{
    public function __construct(
        private readonly CriticalMaterialService $criticalMaterials,
    ) {}

    public function index(PerformanceFilterRequest $request): Response
    {
        $filter = $request->toFilter();
        $materials = $this->criticalMaterials->getCriticalMaterials($filter);

        return Inertia::render('CriticalMaterials/Index', [
            'filters' => $filter->toArray(),
            'materials' => $materials->all(),
            // Counted from the same gathered set rather than re-queried, so the
            // headline and the table can never disagree.
            'summary' => [
                'total' => $materials->count(),
                'high_risk' => $materials->where('risk_level', 'HIGH')->count(),
                'flagged' => $materials->where('is_flagged_critical', true)->count(),
            ],
            'options' => [
                'plants' => Plant::query()->orderBy('code')->get(['id', 'code', 'name'])
                    ->map(fn (Plant $p): array => ['value' => $p->id, 'label' => $p->code.' - '.$p->name])->all(),
                'materialCategories' => MaterialCategory::query()->orderBy('name')->get(['id', 'name'])
                    ->map(fn (MaterialCategory $c): array => ['value' => $c->id, 'label' => $c->name])->all(),
            ],
        ]);
    }
}
