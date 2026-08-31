<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\Department;
use Illuminate\Database\Eloquent\Model;

class DepartmentService extends MasterDataService
{
    protected function modelClass(): string
    {
        return Department::class;
    }

    protected function guardDeletion(Model $record): void
    {
        $this->refuseIfUsed($record->users(), "Department {$record->code}", 'user');
    }
}
