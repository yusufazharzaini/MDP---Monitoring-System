<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Database\Factories\Concerns\GeneratesDocumentNumbers;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    use GeneratesDocumentNumbers;

    protected $model = Delivery::class;

    public function definition(): array
    {
        return [
            'delivery_number' => $this->documentNumber('DN'),
            'purchase_order_id' => PurchaseOrder::factory(),
            'supplier_id' => Supplier::factory(),
            'plant_id' => Plant::factory(),
            'delivery_date' => now()->toDateString(),
            'do_number' => strtoupper($this->faker->bothify('DO-####')),
            'vehicle_number' => strtoupper($this->faker->bothify('B #### ??')),
            'driver_name' => $this->faker->name(),
            'status' => DeliveryStatus::RECEIVED,
            'remarks' => null,
        ];
    }

    /**
     * Attach the delivery to an existing purchase order, inheriting its
     * supplier and plant so the header stays internally consistent.
     */
    public function forPurchaseOrder(PurchaseOrder $order): static
    {
        return $this->state(fn (): array => [
            'purchase_order_id' => $order->getKey(),
            'supplier_id' => $order->supplier_id,
            'plant_id' => $order->plant_id,
        ]);
    }

    public function on(string $date): static
    {
        return $this->state(fn (): array => ['delivery_date' => $date]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => DeliveryStatus::CANCELLED]);
    }
}
