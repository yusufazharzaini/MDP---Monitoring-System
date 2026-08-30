<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Uom;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        $qty = $this->faker->numberBetween(100, 2000);
        $price = $this->faker->numberBetween(1000, 50000);

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'material_id' => Material::factory(),
            'warehouse_id' => Warehouse::factory(),
            'uom_id' => Uom::factory(),
            'line_no' => 1,
            'schedule_delivery_date' => now()->addDays($this->faker->numberBetween(3, 30))->toDateString(),
            'qty_ordered' => $qty,
            'unit_price' => $price,
            'amount' => $qty * $price,
        ];
    }

    public function scheduledOn(string $date): static
    {
        return $this->state(fn (): array => ['schedule_delivery_date' => $date]);
    }

    public function ordering(float $qty): static
    {
        return $this->state(fn (array $attributes): array => [
            'qty_ordered' => $qty,
            'amount' => $qty * (float) ($attributes['unit_price'] ?? 0),
        ]);
    }
}
