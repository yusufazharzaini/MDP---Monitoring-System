<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\Material;
use Illuminate\Database\Eloquent\Model;

class MaterialService extends MasterDataService
{
    protected function modelClass(): string
    {
        return Material::class;
    }

    protected function guardDeletion(Model $record): void
    {
        $this->refuseIfUsed(
            $record->purchaseOrderItems()->whereHas(
                'purchaseOrder',
                fn ($order) => $order->outstanding(),
            ),
            "Material {$record->code}",
            'baris purchase order yang masih berjalan',
        );
    }
}
