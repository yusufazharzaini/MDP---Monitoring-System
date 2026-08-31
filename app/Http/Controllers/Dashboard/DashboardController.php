<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardFilterRequest;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Plant;
use App\Models\Supplier;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dashboard screen.
 *
 * The controller stays thin: it validates the filter, asks DashboardService for
 * the payload, and hands it over. Not one figure on this screen is computed in
 * the controller or in Vue.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    /**
     * First load: the page plus its data, server-rendered into the Inertia props.
     */
    public function index(DashboardFilterRequest $request): Response
    {
        $filter = $request->toFilter();

        return Inertia::render('Dashboard/Index', [
            'dashboard' => $this->dashboard->payload($filter),
            'options' => $this->filterOptions(),
            'generatedAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * Filter changes and the refresh button come back here for JSON only, so a
     * refresh re-renders the panels without a full page visit.
     */
    public function data(DashboardFilterRequest $request): JsonResponse
    {
        return response()->json([
            'dashboard' => $this->dashboard->payload($request->toFilter()),
            'generatedAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * The filter bar's choices.
     *
     * Cached for a minute: master data barely moves, and this would otherwise
     * be four queries on every filter change.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function filterOptions(): array
    {
        return Cache::remember('dashboard.filter_options', 60, static fn (): array => [
            'plants' => Plant::query()->active()->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(static fn (Plant $p): array => [
                    'value' => $p->id,
                    'label' => $p->code.' - '.$p->name,
                ])->all(),

            'suppliers' => Supplier::query()->active()->orderBy('name')
                ->get(['id', 'code', 'name', 'short_name'])
                ->map(static fn (Supplier $s): array => [
                    'value' => $s->id,
                    'label' => $s->displayName().' ('.$s->code.')',
                ])->all(),

            'materials' => Material::query()->active()->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(static fn (Material $m): array => [
                    'value' => $m->id,
                    'label' => $m->code.' - '.$m->name,
                ])->all(),

            'materialCategories' => MaterialCategory::query()->active()->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(static fn (MaterialCategory $c): array => [
                    'value' => $c->id,
                    'label' => $c->name,
                ])->all(),
        ]);
    }
}
