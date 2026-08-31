<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Enums\RecordStatus;
use App\Http\Requests\MasterData\MaterialCategoryRequest;
use App\Models\MaterialCategory;
use App\Services\MasterData\MasterDataService;
use App\Services\MasterData\MaterialCategoryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class MaterialCategoryController extends MasterDataController
{
    public function __construct(private readonly MaterialCategoryService $service) {}

    protected function service(): MasterDataService
    {
        return $this->service;
    }

    protected function modelClass(): string
    {
        return MaterialCategory::class;
    }

    protected function pageDirectory(): string
    {
        return 'MaterialCategories';
    }

    protected function routeName(): string
    {
        return 'material-categories';
    }

    protected function label(): string
    {
        return 'Kategori material';
    }

    public function store(MaterialCategoryRequest $request): RedirectResponse
    {
        return $this->storeRecord($request->validated());
    }

    public function update(MaterialCategoryRequest $request, MaterialCategory $record): RedirectResponse
    {
        return $this->updateRecord($record, $request->validated());
    }

    public function edit(MaterialCategory $record): Response
    {
        return $this->editView($record);
    }

    public function destroy(MaterialCategory $record): RedirectResponse
    {
        return $this->destroyRecord($record);
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
        /** @var MaterialCategory $record */
        return [
            'id' => $record->id,
            'code' => $record->code,
            'name' => $record->name,
            'description' => $record->description,
            'materials_count' => $record->materials_count ?? 0,
            'status' => $record->status->value,
            'status_label' => $record->status->label(),
            'status_variant' => $record->status->badgeVariant(),
        ];
    }
}
