<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Enums\RecordStatus;
use App\Http\Requests\MasterData\DepartmentRequest;
use App\Models\Department;
use App\Services\MasterData\DepartmentService;
use App\Services\MasterData\MasterDataService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class DepartmentController extends MasterDataController
{
    public function __construct(private readonly DepartmentService $service) {}

    protected function service(): MasterDataService
    {
        return $this->service;
    }

    protected function modelClass(): string
    {
        return Department::class;
    }

    protected function pageDirectory(): string
    {
        return 'Departments';
    }

    protected function routeName(): string
    {
        return 'departments';
    }

    protected function label(): string
    {
        return 'Department';
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        return $this->storeRecord($request->validated());
    }

    public function update(DepartmentRequest $request, Department $record): RedirectResponse
    {
        return $this->updateRecord($record, $request->validated());
    }

    public function edit(Department $record): Response
    {
        return $this->editView($record);
    }

    public function destroy(Department $record): RedirectResponse
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
        /** @var Department $record */
        return [
            'id' => $record->id,
            'code' => $record->code,
            'name' => $record->name,
            'description' => $record->description,
            'users_count' => $record->users_count ?? 0,
            'status' => $record->status->value,
            'status_label' => $record->status->label(),
            'status_variant' => $record->status->badgeVariant(),
        ];
    }
}
