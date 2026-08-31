<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Enums\RecordStatus;
use App\Http\Requests\MasterData\MaterialRequest;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Uom;
use App\Services\MasterData\MasterDataService;
use App\Services\MasterData\MaterialService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaterialController extends MasterDataController
{
    public function __construct(private readonly MaterialService $materials) {}

    protected function service(): MasterDataService
    {
        return $this->materials;
    }

    protected function modelClass(): string
    {
        return Material::class;
    }

    protected function pageDirectory(): string
    {
        return 'Materials';
    }

    protected function routeName(): string
    {
        return 'materials';
    }

    protected function label(): string
    {
        return 'Material';
    }

    public function store(MaterialRequest $request): RedirectResponse
    {
        return $this->storeRecord($request->validated());
    }

    public function update(MaterialRequest $request, Material $material): RedirectResponse
    {
        return $this->updateRecord($material, $request->validated());
    }

    public function show(Material $material): Response
    {
        $this->authorize('view', $material);

        $material->loadMissing(['category', 'uom']);

        return Inertia::render('Materials/Show', [
            'record' => $this->transform($material),
            'can' => $this->abilities(),
        ]);
    }

    /**
     * @return Builder<Material>
     */
    protected function indexQuery(Request $request): Builder
    {
        return parent::indexQuery($request)
            ->when(
                $request->filled('category_id'),
                fn (Builder $q) => $q->inCategory($request->integer('category_id')),
            )
            ->when($request->boolean('critical_only'), fn (Builder $q) => $q->critical());
    }

    public function edit(Material $material): Response
    {
        return $this->editView($material);
    }

    public function destroy(Material $material): RedirectResponse
    {
        return $this->destroyRecord($material);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'statuses' => RecordStatus::options(),
            'categories' => MaterialCategory::query()->active()->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn (MaterialCategory $c): array => ['value' => $c->id, 'label' => $c->name])
                ->all(),
            'uoms' => Uom::query()->active()->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (Uom $u): array => ['value' => $u->id, 'label' => $u->code.' - '.$u->name])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function transform(Model $record): array
    {
        /** @var Material $record */
        return [
            'id' => $record->id,
            'ulid' => $record->ulid,
            'code' => $record->code,
            'name' => $record->name,
            'category_id' => $record->category_id,
            'category_name' => $record->category?->name,
            'uom_id' => $record->uom_id,
            'uom_code' => $record->uom?->code,
            'specification' => $record->specification,
            'minimum_stock' => (float) $record->minimum_stock,
            'critical_stock' => (float) $record->critical_stock,
            'lead_time_days' => $record->lead_time_days,
            'is_critical' => $record->is_critical,
            'status' => $record->status->value,
            'status_label' => $record->status->label(),
            'status_variant' => $record->status->badgeVariant(),
        ];
    }
}
