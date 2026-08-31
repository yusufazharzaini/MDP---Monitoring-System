<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\Plant;
use Illuminate\Database\Eloquent\Model;

class PlantService extends MasterDataService
{
    protected function modelClass(): string
    {
        return Plant::class;
    }

    /**
     * Retiring a plant would strand its warehouses and any order still due into
     * it, so both are checked before the plant may go.
     */
    protected function guardDeletion(Model $record): void
    {
        $this->refuseIfUsed($record->warehouses(), "Plant {$record->code}", 'warehouse');
        $this->refuseIfUsed(
            $record->purchaseOrders()->outstanding(),
            "Plant {$record->code}",
            'purchase order yang masih berjalan',
        );
    }
}
