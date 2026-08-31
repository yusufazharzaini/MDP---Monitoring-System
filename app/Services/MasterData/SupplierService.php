<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;

class SupplierService extends MasterDataService
{
    protected function modelClass(): string
    {
        return Supplier::class;
    }

    /**
     * A supplier with orders still in flight cannot be retired - somebody is
     * waiting on those deliveries. Completed and cancelled orders are history
     * and do not block it.
     */
    protected function guardDeletion(Model $record): void
    {
        $this->refuseIfUsed(
            $record->purchaseOrders()->outstanding(),
            "Supplier {$record->code}",
            'purchase order yang masih berjalan',
        );
    }
}
