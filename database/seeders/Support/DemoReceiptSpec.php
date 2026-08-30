<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * One goods receipt against a purchase order: a delivery header plus the
 * quantity booked for each order line it touches.
 *
 * An order with two receipts is a split shipment - the case that makes partial
 * and multiple delivery visible in the demo data.
 */
final readonly class DemoReceiptSpec
{
    /**
     * @param  array<int, float>  $quantities  line_no => quantity received
     */
    public function __construct(
        public string $deliveryNumber,
        public string $deliveryDate,
        public int $daysLate,
        public array $quantities,
    ) {}
}
