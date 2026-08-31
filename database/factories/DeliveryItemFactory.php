<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeliveryItemCondition;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Material;
use App\Models\PurchaseOrderItem;
use App\Models\Uom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryItem>
 */
class DeliveryItemFactory extends Factory
{
    protected $model = DeliveryItem::class;

    public function definition(): array
    {
        return [
            'delivery_id' => Delivery::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'material_id' => Material::factory(),
            'uom_id' => Uom::factory(),
            'qty_received' => $this->faker->numberBetween(100, 2000),
            'condition' => DeliveryItemCondition::GOOD,
            'remarks' => null,
        ];
    }

    /**
     * Receive against a specific purchase order line, inheriting its material
     * and unit of measure.
     */
    public function fulfilling(PurchaseOrderItem $item, ?float $qty = null): static
    {
        return $this->state(fn (): array => [
            'purchase_order_item_id' => $item->getKey(),
            'material_id' => $item->material_id,
            'uom_id' => $item->uom_id,
            'qty_received' => $qty ?? (float) $item->qty_ordered,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => ['condition' => DeliveryItemCondition::REJECTED]);
    }
}
