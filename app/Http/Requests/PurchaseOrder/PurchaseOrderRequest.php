<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a purchase order and its lines in one pass.
 *
 * The line rules are strict about references because a line that points at an
 * inactive material or a warehouse in a different plant is a delivery nobody
 * can receive.
 */
class PurchaseOrderRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'po_date' => ['required', 'date_format:Y-m-d'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'plant_id' => ['required', 'integer', 'exists:plants,id'],
            'currency' => ['required', 'string', 'max:10'],
            'payment_term' => ['nullable', 'string', 'max:50'],
            'remarks' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.material_id' => ['required', 'integer', 'exists:materials,id'],
            'items.*.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'items.*.uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'items.*.schedule_delivery_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:po_date'],
            'items.*.qty_ordered' => ['required', 'numeric', 'gt:0', 'max:99999999999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999999999'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => __('requests.po_needs_lines'),
            'items.min' => __('requests.po_needs_lines'),
            'items.*.qty_ordered.gt' => __('requests.po_qty_positive'),
            'items.*.schedule_delivery_date.after_or_equal' => __('requests.po_schedule_before_date'),
        ];
    }

    /**
     * Cross-field rules the field-level ones cannot express.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->assertWarehousesBelongToPlant($validator);
                $this->assertLinesBelongToThisOrder($validator);
            },
        ];
    }

    /**
     * A warehouse in another plant cannot receive this order's goods.
     */
    private function assertWarehousesBelongToPlant(Validator $validator): void
    {
        $plantId = $this->integer('plant_id');
        $warehouses = Warehouse::query()
            ->whereIn('id', collect($this->input('items', []))->pluck('warehouse_id')->filter()->all())
            ->pluck('plant_id', 'id');

        foreach ((array) $this->input('items', []) as $index => $line) {
            $warehouseId = $line['warehouse_id'] ?? null;

            if ($warehouseId !== null && ($warehouses[$warehouseId] ?? null) !== $plantId) {
                $validator->errors()->add(
                    "items.{$index}.warehouse_id",
                    'Warehouse harus berada pada plant yang sama dengan purchase order.',
                );
            }
        }
    }

    /**
     * A line id from another order must never be adopted by this one.
     */
    private function assertLinesBelongToThisOrder(Validator $validator): void
    {
        $order = $this->route('purchase_order');

        if (! $order instanceof PurchaseOrder) {
            return;
        }

        $owned = $order->items()->pluck('id')->all();

        foreach ((array) $this->input('items', []) as $index => $line) {
            $id = $line['id'] ?? null;

            if ($id !== null && ! in_array((int) $id, $owned, true)) {
                $validator->errors()->add("items.{$index}.id", 'Baris item tidak dikenali pada purchase order ini.');
            }
        }
    }
}
