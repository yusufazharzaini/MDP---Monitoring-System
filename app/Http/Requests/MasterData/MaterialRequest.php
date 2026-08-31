<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Enums\RecordStatus;
use Illuminate\Validation\Rule;

class MaterialRequest extends MasterDataRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', $this->uniqueIgnoringSelf('materials', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:material_categories,id'],
            'uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'specification' => ['nullable', 'string', 'max:2000'],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'max:99999999999999'],
            // Critical stock is the reorder alarm, so it must sit at or below
            // the minimum it is meant to warn about.
            'critical_stock' => ['required', 'numeric', 'min:0', 'lte:minimum_stock'],
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_critical' => ['boolean'],
            'status' => ['required', Rule::enum(RecordStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'critical_stock.lte' => 'Critical stock harus lebih kecil atau sama dengan minimum stock.',
        ];
    }
}
