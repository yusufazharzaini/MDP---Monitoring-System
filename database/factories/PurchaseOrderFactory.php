<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Database\Factories\Concerns\GeneratesDocumentNumbers;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    use GeneratesDocumentNumbers;

    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        $date = Carbon::instance($this->faker->dateTimeBetween('-6 months', 'now'));

        return [
            'po_number' => $this->documentNumber('PO', $date),
            'po_date' => $date->toDateString(),
            'supplier_id' => Supplier::factory(),
            'plant_id' => Plant::factory(),
            'currency' => 'IDR',
            'payment_term' => 'NET 30',
            'status' => PurchaseOrderStatus::APPROVED,
            'total_amount' => 0,
            'remarks' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseOrderStatus::DRAFT,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseOrderStatus::APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => PurchaseOrderStatus::CANCELLED]);
    }

    public function on(string $date): static
    {
        return $this->state(fn (): array => ['po_date' => $date]);
    }
}
