<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * One purchase order in the demo plan. Every order is fulfilled by at most one
 * delivery, which is what keeps the seeded rollup identical to what
 * DeliveryStatusService would compute at runtime.
 */
final readonly class DemoOrderSpec
{
    /**
     * @param  array<int, DemoLineSpec>  $lines
     */
    public function __construct(
        public string $poNumber,
        public string $deliveryNumber,
        public string $supplierCode,
        public string $plantCode,
        public string $poDate,
        public string $scheduleDate,
        public ?string $deliveryDate,
        public int $daysLate,
        public array $lines,
    ) {}

    public function isDelivered(): bool
    {
        return $this->deliveryDate !== null;
    }

    public function totalAmount(): float
    {
        return round(array_sum(array_map(
            static fn (DemoLineSpec $line): float => $line->amount(),
            $this->lines,
        )), 4);
    }
}
