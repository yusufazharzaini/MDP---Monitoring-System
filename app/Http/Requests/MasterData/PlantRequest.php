<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Enums\RecordStatus;
use Illuminate\Validation\Rule;

class PlantRequest extends MasterDataRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', $this->uniqueIgnoringSelf('plants', 'code')],
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'pic_name' => ['nullable', 'string', 'max:100'],
            'pic_phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::enum(RecordStatus::class)],
        ];
    }
}
