<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

class WarehouseService extends MasterDataService
{
    protected function modelClass(): string
    {
        return Warehouse::class;
    }

    /**
     * A warehouse is a delivery destination; it cannot be retired while goods
     * are still scheduled to arrive there.
     */
    protected function guardDeletion(Model $record): void
    {
        $this->refuseIfUsed(
            $record->purchaseOrderItems()->whereHas(
                'purchaseOrder',
                fn ($order) => $order->outstanding(),
            ),
            "Warehouse {$record->code}",
            'baris purchase order yang masih berjalan',
        );
    }
}
