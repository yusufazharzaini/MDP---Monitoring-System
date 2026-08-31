<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use App\Enums\DeliveryItemCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Goods cannot arrive tomorrow; the service re-checks this so the
            // rule holds for callers that never touch a form.
            'delivery_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'do_number' => ['nullable', 'string', 'max:50'],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            // Zero is allowed: booking a line as arrived-but-empty is how a
            // clerk records a delivery that turned up short on that material.
            'items.*.qty_received' => ['required', 'numeric', 'min:0', 'max:99999999999999'],
            'items.*.condition' => ['required', Rule::enum(DeliveryItemCondition::class)],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Delivery harus memiliki minimal satu baris penerimaan.',
            'items.min' => 'Delivery harus memiliki minimal satu baris penerimaan.',
            'delivery_date.before_or_equal' => 'Tanggal delivery tidak boleh berada di masa depan.',
        ];
    }
}
