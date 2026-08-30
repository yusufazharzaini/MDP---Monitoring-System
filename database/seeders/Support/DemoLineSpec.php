<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * One purchase order line in the demo plan, together with the receipt that
 * fulfils it (if any).
 */
final readonly class DemoLineSpec
{
    public function __construct(
        public int $lineNo,
        public string $materialCode,
        public float $qtyOrdered,
        public float $qtyReceived,
        public float $unitPrice,
        public bool $late,
        public bool $short,
        public bool $delivered,
    ) {}

    public function amount(): float
    {
        return round($this->qtyOrdered * $this->unitPrice, 4);
    }
}
