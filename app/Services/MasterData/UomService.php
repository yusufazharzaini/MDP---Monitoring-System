<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\PurchaseOrderItem;
use App\Models\Uom;
use Illuminate\Database\Eloquent\Model;

class UomService extends MasterDataService
{
    protected function modelClass(): string
    {
        return Uom::class;
    }

    /**
     * A unit of measure is what makes a quantity mean something; removing one
     * still attached to a material or an order line would make those numbers
     * unreadable.
     */
    protected function guardDeletion(Model $record): void
    {
        $this->refuseIfUsed($record->materials(), "UOM {$record->code}", 'material');
        $this->refuseIfUsed(
            PurchaseOrderItem::query()->where('uom_id', $record->getKey()),
            "UOM {$record->code}",
            'baris purchase order',
        );
    }
}
