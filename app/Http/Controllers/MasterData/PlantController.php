<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Enums\RecordStatus;
use App\Http\Requests\MasterData\PlantRequest;
use App\Models\Plant;
use App\Services\MasterData\MasterDataService;
use App\Services\MasterData\PlantService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class PlantController extends MasterDataController
{
    public function __construct(private readonly PlantService $plants) {}

    protected function service(): MasterDataService
    {
        return $this->plants;
    }

    protected function modelClass(): string
    {
        return Plant::class;
    }

    protected function pageDirectory(): string
    {
        return 'Plants';
    }

    protected function routeName(): string
    {
        return 'plants';
    }

    protected function label(): string
    {
        return 'Plant';
    }

    public function store(PlantRequest $request): RedirectResponse
    {
        return $this->storeRecord($request->validated());
    }

    public function update(PlantRequest $request, Plant $plant): RedirectResponse
    {
        return $this->updateRecord($plant, $request->validated());
    }

    public function edit(Plant $plant): Response
    {
        return $this->editView($plant);
    }

    public function destroy(Plant $plant): RedirectResponse
    {
        return $this->destroyRecord($plant);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return ['statuses' => RecordStatus::options()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function transform(Model $record): array
    {
        /** @var Plant $record */
        return [
            'id' => $record->id,
            'ulid' => $record->ulid,
            'code' => $record->code,
            'name' => $record->name,
            'address' => $record->address,
            'city' => $record->city,
            'pic_name' => $record->pic_name,
            'pic_phone' => $record->pic_phone,
            'status' => $record->status->value,
            'status_label' => $record->status->label(),
            'status_variant' => $record->status->badgeVariant(),
            'warehouses_count' => $record->warehouses_count ?? 0,
            'users_count' => $record->users_count ?? 0,
        ];
    }
}
