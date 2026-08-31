<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Enums\RecordStatus;
use Illuminate\Validation\Rule;

class SupplierContactRequest extends MasterDataRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'name' => ['required', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'is_primary' => ['boolean'],
            'status' => ['required', Rule::enum(RecordStatus::class)],
        ];
    }
}
