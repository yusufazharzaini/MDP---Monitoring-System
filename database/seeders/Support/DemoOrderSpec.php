<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * One purchase order in the demo plan, with its lines and the receipts that
 * fulfil them. An order with no receipts is a genuinely pending order.
 */
final readonly class DemoOrderSpec
{
    /**
     * @param  array<int, DemoLineSpec>  $lines
     * @param  array<int, DemoReceiptSpec>  $receipts
     */
    public function __construct(
        public string $poNumber,
        public string $supplierCode,
        public string $plantCode,
        public string $poDate,
        public string $scheduleDate,
        public array $lines,
        public array $receipts,
    ) {}

    public function isDelivered(): bool
    {
        return $this->receipts !== [];
    }

    public function totalAmount(): float
    {
        return round(array_sum(array_map(
            static fn (DemoLineSpec $line): float => $line->amount(),
            $this->lines,
        )), 4);
    }

    /**
     * Total quantity booked against one line across every receipt.
     */
    public function receivedFor(int $lineNo): float
    {
        return array_sum(array_map(
            static fn (DemoReceiptSpec $receipt): float => $receipt->quantities[$lineNo] ?? 0.0,
            $this->receipts,
        ));
    }
}
