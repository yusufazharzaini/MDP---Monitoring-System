<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\MaterialCategory;
use Illuminate\Database\Eloquent\Model;

class MaterialCategoryService extends MasterDataService
{
    protected function modelClass(): string
    {
        return MaterialCategory::class;
    }

    /**
     * Every material must keep a category, so a category in use stays.
     */
    protected function guardDeletion(Model $record): void
    {
        $this->refuseIfUsed($record->materials(), "Kategori {$record->code}", 'material');
    }
}
