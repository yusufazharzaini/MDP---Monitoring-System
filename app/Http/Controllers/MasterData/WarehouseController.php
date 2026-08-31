<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Enums\RecordStatus;
use App\Http\Requests\MasterData\WarehouseRequest;
use App\Models\Plant;
use App\Models\Warehouse;
use App\Services\MasterData\MasterDataService;
use App\Services\MasterData\WarehouseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class WarehouseController extends MasterDataController
{
    public function __construct(private readonly WarehouseService $warehouses) {}

    protected function service(): MasterDataService
    {
        return $this->warehouses;
    }

    protected function modelClass(): string
    {
        return Warehouse::class;
    }

    protected function pageDirectory(): string
    {
        return 'Warehouses';
    }

    protected function routeName(): string
    {
        return 'warehouses';
    }

    protected function label(): string
    {
        return 'Warehouse';
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        return $this->storeRecord($request->validated());
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        return $this->updateRecord($warehouse, $request->validated());
    }

    /**
     * Warehouses are usually browsed one plant at a time.
     *
     * @return Builder<Warehouse>
     */
    protected function indexQuery(Request $request): Builder
    {
        return parent::indexQuery($request)->when(
            $request->filled('plant_id'),
            fn (Builder $q) => $q->forPlant($request->integer('plant_id')),
        );
    }

    public function edit(Warehouse $warehouse): Response
    {
        return $this->editView($warehouse);
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        return $this->destroyRecord($warehouse);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'statuses' => RecordStatus::options(),
            'plants' => Plant::query()->active()->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (Plant $p): array => ['value' => $p->id, 'label' => $p->code.' - '.$p->name])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function transform(Model $record): array
    {
        /** @var Warehouse $record */
        return [
            'id' => $record->id,
            'ulid' => $record->ulid,
            'plant_id' => $record->plant_id,
            'plant_name' => $record->plant?->name,
            'plant_code' => $record->plant?->code,
            'code' => $record->code,
            'name' => $record->name,
            'address' => $record->address,
            'status' => $record->status->value,
            'status_label' => $record->status->label(),
            'status_variant' => $record->status->badgeVariant(),
        ];
    }
}
