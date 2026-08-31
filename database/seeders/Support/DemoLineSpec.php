<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * One purchase order line in the demo plan. Receipts against it are described
 * separately by DemoReceiptSpec, because a line may be filled by several.
 */
final readonly class DemoLineSpec
{
    public function __construct(
        public int $lineNo,
        public string $materialCode,
        public float $qtyOrdered,
        public float $unitPrice,
        public bool $late,
        public bool $short,
        public bool $over = false,
    ) {}

    public function amount(): float
    {
        return round($this->qtyOrdered * $this->unitPrice, 4);
    }
}
