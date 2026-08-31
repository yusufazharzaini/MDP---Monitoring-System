<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Enums\RecordStatus;
use App\Enums\UomType;
use App\Http\Requests\MasterData\UomRequest;
use App\Models\Uom;
use App\Services\MasterData\MasterDataService;
use App\Services\MasterData\UomService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class UomController extends MasterDataController
{
    public function __construct(private readonly UomService $service) {}

    protected function service(): MasterDataService
    {
        return $this->service;
    }

    protected function modelClass(): string
    {
        return Uom::class;
    }

    protected function pageDirectory(): string
    {
        return 'Uoms';
    }

    protected function routeName(): string
    {
        return 'uoms';
    }

    protected function label(): string
    {
        return 'UOM';
    }

    public function store(UomRequest $request): RedirectResponse
    {
        return $this->storeRecord($request->validated());
    }

    public function update(UomRequest $request, Uom $record): RedirectResponse
    {
        return $this->updateRecord($record, $request->validated());
    }

    public function edit(Uom $record): Response
    {
        return $this->editView($record);
    }

    public function destroy(Uom $record): RedirectResponse
    {
        return $this->destroyRecord($record);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return ['statuses' => RecordStatus::options(), 'types' => UomType::options()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function transform(Model $record): array
    {
        /** @var Uom $record */
        return [
            'id' => $record->id,
            'code' => $record->code,
            'name' => $record->name,
            'type' => $record->type->value,
            'type_label' => $record->type->label(),
            'status' => $record->status->value,
            'status_label' => $record->status->label(),
            'status_variant' => $record->status->badgeVariant(),
        ];
    }
}
