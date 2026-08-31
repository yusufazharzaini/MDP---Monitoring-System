<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Enums\RecordStatus;
use Illuminate\Validation\Rule;

class WarehouseRequest extends MasterDataRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plant_id' => ['required', 'integer', 'exists:plants,id'],
            // A warehouse code only has to be unique inside its own plant,
            // which is the same rule the composite index enforces.
            'code' => [
                'required', 'string', 'max:20',
                $this->uniqueIgnoringSelf('warehouses', 'code')
                    ->where('plant_id', $this->integer('plant_id')),
            ],
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::enum(RecordStatus::class)],
        ];
    }
}
