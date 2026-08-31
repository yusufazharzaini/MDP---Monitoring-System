<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use Illuminate\Validation\Rule;

class SupplierRequest extends MasterDataRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', $this->uniqueIgnoringSelf('suppliers', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'pic_name' => ['nullable', 'string', 'max:100'],
            'pic_email' => ['nullable', 'email', 'max:100'],
            'pic_phone' => ['nullable', 'string', 'max:30'],
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
            'payment_term' => ['nullable', 'string', 'max:50'],
            'supplier_type' => ['required', Rule::enum(SupplierType::class)],
            'status' => ['required', Rule::enum(SupplierStatus::class)],
        ];
    }
}
