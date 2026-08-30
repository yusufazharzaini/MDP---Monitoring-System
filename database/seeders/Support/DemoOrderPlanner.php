<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use Illuminate\Support\Carbon;

/**
 * Builds the demo purchase order / delivery plan.
 *
 * The plan is a pure function of the anchor month: no randomness, no database.
 * Both DemoPurchaseOrderSeeder and DemoDeliverySeeder rebuild the identical
 * plan, so they can run independently and still agree line for line.
 *
 * Every month is planned against three budgets that the dashboard asserts:
 * total delivery lines, late lines and short lines. Split shipments consume
 * two delivery lines and one short line each, and are accounted for first.
 */
final class DemoOrderPlanner
{
    /** Late lines in the current month that are also short, producing LATE_SHORT rows. */
    private const CURRENT_MONTH_LATE_AND_SHORT = 6;

    private const PENDING_ORDERS = 25;

    /** Days before the scheduled date that the first half of a split arrives. */
    private const SPLIT_FIRST_RECEIPT_LEAD_DAYS = 3;

    /** Materials safe for undelivered orders - none of them is flagged critical. */
    private const PENDING_MATERIALS = ['MAT-0008', 'MAT-0009', 'MAT-0010', 'MAT-0011', 'MAT-0012'];

    /** @var array<int, string> */
    private array $normalMaterials;

    /** @var array<int, string> */
    private array $plantCodes;

    /** @var array<string, int> */
    private array $sequence = [];

    /**
     * @param  array<int, string>  $normalMaterials  Materials used by clean, on-time lines.
     * @param  array<int, string>  $plantCodes
     */
    public function __construct(
        private readonly Carbon $anchorMonth,
        array $normalMaterials,
        array $plantCodes,
    ) {
        $this->normalMaterials = array_values($normalMaterials);
        $this->plantCodes = array_values($plantCodes);
    }

    /**
     * The whole six-month plan, oldest month first.
     *
     * @return array<int, DemoOrderSpec>
     */
    public function build(): array
    {
        $orders = [];

        foreach (DemoBlueprint::PRIOR_MONTHS as $month) {
            $orders = [
                ...$orders,
                ...$this->buildMonth(
                    $month['offset'],
                    $this->allocatePriorMonth($month['total'], $month['late']),
                    $month['short'],
                    lateAndShort: 0,
                    splitLines: $month['split'],
                    overLines: $month['over'],
                ),
            ];
        }

        return [
            ...$orders,
            ...$this->buildMonth(
                0,
                DemoBlueprint::CURRENT_MONTH_ALLOCATION,
                DemoBlueprint::CURRENT_MONTH_SHORT_LINES,
                lateAndShort: self::CURRENT_MONTH_LATE_AND_SHORT,
                splitLines: DemoBlueprint::CURRENT_MONTH_SPLIT_LINES,
                overLines: DemoBlueprint::CURRENT_MONTH_OVER_LINES,
            ),
            ...$this->buildPendingOrders(),
        ];
    }

    /**
     * Distribute a prior month's volume over the suppliers using the current
     * month's proportions, then place its late lines on the suppliers that
     * carry the poorest performance.
     *
     * @return array<string, array{int, int}>
     */
    private function allocatePriorMonth(int $total, int $late): array
    {
        $weights = [];
        foreach (DemoBlueprint::CURRENT_MONTH_ALLOCATION as $code => [$lines, $_]) {
            $weights[$code] = (float) $lines;
        }

        $lines = $this->distribute($total, $weights);

        // Poorest performers first, so the demo keeps a believable ranking spread.
        $priority = ['SUP-004', 'SUP-003', 'SUP-002', 'SUP-005', 'SUP-001', 'SUP-006', 'SUP-007', 'SUP-008'];
        $lateLines = $this->spread($late, $lines, $priority);

        $allocation = [];
        foreach ($lines as $code => $count) {
            $allocation[$code] = [$count, $lateLines[$code] ?? 0];
        }

        return $allocation;
    }

    /**
     * @param  array<string, array{int, int}>  $allocation  supplier => [delivery lines, late lines]
     * @return array<int, DemoOrderSpec>
     */
    private function buildMonth(
        int $offset,
        array $allocation,
        int $shortLines,
        int $lateAndShort,
        int $splitLines,
        int $overLines,
    ): array {
        $month = $this->anchorMonth->copy()->subMonths($offset);

        $lateCapacity = [];
        $volumeWeights = [];
        foreach ($allocation as $code => [$lines, $late]) {
            $lateCapacity[$code] = $late;
            $volumeWeights[$code] = (float) $lines;
        }

        // Splits are placed first: each consumes two delivery lines and one short line.
        $splitPerSupplier = $this->capacityAwareDistribution(
            $splitLines,
            $volumeWeights,
            array_map(static fn (array $a): int => intdiv($a[0] - $a[1], 2), $allocation),
        );

        $overlapPerSupplier = $this->spread(
            min($lateAndShort, array_sum($lateCapacity)),
            $lateCapacity,
            $this->byDescendingValue($lateCapacity),
        );

        $shortOnlyTotal = max(
            0,
            $shortLines - array_sum($overlapPerSupplier) - array_sum($splitPerSupplier),
        );
        $shortOnlyPerSupplier = $this->distribute($shortOnlyTotal, $volumeWeights);
        $overPerSupplier = $this->distribute($overLines, $volumeWeights);

        $orders = [];
        $index = 0;

        foreach ($allocation as $code => [$deliveryLines, $late]) {
            $splits = $splitPerSupplier[$code] ?? 0;
            $overlap = $overlapPerSupplier[$code] ?? 0;

            $budget = $deliveryLines - (2 * $splits) - $late;
            $shortOnly = min($shortOnlyPerSupplier[$code] ?? 0, max(0, $budget));
            $over = min($overPerSupplier[$code] ?? 0, max(0, $budget - $shortOnly));

            for ($i = 0; $i < $splits; $i++) {
                $orders[] = $this->splitOrder($month, $code, $index++);
            }

            // Late lines get their own single-line order so the late count is exact.
            for ($i = 0; $i < $late; $i++) {
                $orders[] = $this->singleLineOrder($month, $code, $index++, late: true, short: $i < $overlap);
            }

            for ($i = 0; $i < $shortOnly; $i++) {
                $orders[] = $this->singleLineOrder($month, $code, $index++, late: false, short: true);
            }

            for ($i = 0; $i < $over; $i++) {
                $orders[] = $this->overOrder($month, $code, $index++);
            }

            $remaining = $budget - $shortOnly - $over;
            $orders = [...$orders, ...$this->cleanOrders($month, $code, $remaining, $index)];
            $index += $remaining;
        }

        return $orders;
    }

    /**
     * A split shipment: one order line filled by two receipts, both on or before
     * the scheduled date. The first is cumulatively SHORT, the second settles it
     * as FULL - which is exactly what the runtime calculator would derive.
     */
    private function splitOrder(Carbon $month, string $supplierCode, int $index): DemoOrderSpec
    {
        // The first receipt arrives early, so the schedule must sit far enough
        // into the month that it cannot spill into the previous period.
        $schedule = $this->scheduleFor($month, $index, self::SPLIT_FIRST_RECEIPT_LEAD_DAYS);
        $line = $this->makeLine(1, $index, late: false, short: false);

        $firstQty = round($line->qtyOrdered * DemoBlueprint::SPLIT_FIRST_RECEIPT_RATIO, 4);
        $stamp = $month->format('Ym');

        $receipts = [
            new DemoReceiptSpec(
                deliveryNumber: $this->nextNumber('DN', $stamp),
                deliveryDate: $schedule->copy()->subDays(self::SPLIT_FIRST_RECEIPT_LEAD_DAYS)->toDateString(),
                daysLate: 0,
                quantities: [1 => $firstQty],
            ),
            new DemoReceiptSpec(
                deliveryNumber: $this->nextNumber('DN', $stamp),
                deliveryDate: $schedule->toDateString(),
                daysLate: 0,
                quantities: [1 => round($line->qtyOrdered - $firstQty, 4)],
            ),
        ];

        return $this->makeOrder($month, $supplierCode, $schedule, $index, [$line], $receipts);
    }

    /**
     * A punctual receipt above the ordered quantity: OVER_DELIVERY. Flagged for
     * visibility rather than blocked, because the business needs to see it.
     */
    private function overOrder(Carbon $month, string $supplierCode, int $index): DemoOrderSpec
    {
        $schedule = $this->scheduleFor($month, $index);
        $base = $this->makeLine(1, $index, late: false, short: false);
        $line = new DemoLineSpec(
            lineNo: $base->lineNo,
            materialCode: $base->materialCode,
            qtyOrdered: $base->qtyOrdered,
            unitPrice: $base->unitPrice,
            late: false,
            short: false,
            over: true,
        );

        return $this->makeOrder(
            $month,
            $supplierCode,
            $schedule,
            $index,
            [$line],
            [$this->receiptFor($month, $schedule, 0, [1 => $this->receivedQuantity($line)])],
        );
    }

    private function singleLineOrder(Carbon $month, string $supplierCode, int $index, bool $late, bool $short): DemoOrderSpec
    {
        $schedule = $this->scheduleFor($month, $index);
        $line = $this->makeLine(1, $index, $late, $short);
        $daysLate = $late ? 1 + ($index % 7) : 0;

        return $this->makeOrder(
            $month,
            $supplierCode,
            $schedule,
            $index,
            [$line],
            [$this->receiptFor($month, $schedule, $daysLate, [1 => $this->receivedQuantity($line)])],
        );
    }

    /**
     * Clean, on-time, fully received lines grouped into multi-line orders.
     *
     * @return array<int, DemoOrderSpec>
     */
    private function cleanOrders(Carbon $month, string $supplierCode, int $lineCount, int $indexOffset): array
    {
        $orders = [];
        $groupSizes = [3, 2, 4, 1, 2, 3];
        $placed = 0;
        $group = 0;

        while ($placed < $lineCount) {
            $size = min($groupSizes[$group % count($groupSizes)], $lineCount - $placed);
            $index = $indexOffset + $placed;
            $schedule = $this->scheduleFor($month, $index);

            $lines = [];
            $quantities = [];
            for ($line = 0; $line < $size; $line++) {
                $spec = $this->makeLine($line + 1, $index + $line, late: false, short: false);
                $lines[] = $spec;
                $quantities[$spec->lineNo] = $spec->qtyOrdered;
            }

            $orders[] = $this->makeOrder(
                $month,
                $supplierCode,
                $schedule,
                $index,
                $lines,
                [$this->receiptFor($month, $schedule, 0, $quantities)],
            );

            $placed += $size;
            $group++;
        }

        return $orders;
    }

    /**
     * Orders scheduled inside the current month that have not been received yet.
     *
     * @return array<int, DemoOrderSpec>
     */
    private function buildPendingOrders(): array
    {
        $orders = [];
        $codes = DemoBlueprint::supplierCodes();

        for ($i = 0; $i < self::PENDING_ORDERS; $i++) {
            $index = 900_000 + $i;

            $line = new DemoLineSpec(
                lineNo: 1,
                materialCode: self::PENDING_MATERIALS[$i % count(self::PENDING_MATERIALS)],
                qtyOrdered: $this->quantityFor($index),
                unitPrice: $this->priceFor($index),
                late: false,
                short: false,
            );

            $orders[] = $this->makeOrder(
                $this->anchorMonth,
                $codes[$i % count($codes)],
                $this->scheduleFor($this->anchorMonth, $index),
                $index,
                [$line],
                receipts: [],
            );
        }

        return $orders;
    }

    /**
     * @param  array<int, DemoLineSpec>  $lines
     * @param  array<int, DemoReceiptSpec>  $receipts
     */
    private function makeOrder(
        Carbon $month,
        string $supplierCode,
        Carbon $schedule,
        int $index,
        array $lines,
        array $receipts,
    ): DemoOrderSpec {
        return new DemoOrderSpec(
            poNumber: $this->nextNumber('PO', $month->format('Ym')),
            supplierCode: $supplierCode,
            plantCode: $this->plantCodes[$index % count($this->plantCodes)],
            poDate: $schedule->copy()->subDays(14)->toDateString(),
            scheduleDate: $schedule->toDateString(),
            lines: $lines,
            receipts: $receipts,
        );
    }

    /**
     * @param  array<int, float>  $quantities
     */
    private function receiptFor(Carbon $month, Carbon $schedule, int $daysLate, array $quantities): DemoReceiptSpec
    {
        return new DemoReceiptSpec(
            deliveryNumber: $this->nextNumber('DN', $month->format('Ym')),
            deliveryDate: $schedule->copy()->addDays($daysLate)->toDateString(),
            daysLate: $daysLate,
            quantities: $quantities,
        );
    }

    private function makeLine(int $lineNo, int $index, bool $late, bool $short): DemoLineSpec
    {
        return new DemoLineSpec(
            lineNo: $lineNo,
            materialCode: $this->materialFor($index, problem: $late || $short),
            qtyOrdered: $this->quantityFor($index),
            unitPrice: $this->priceFor($index),
            late: $late,
            short: $short,
        );
    }

    private function receivedQuantity(DemoLineSpec $line): float
    {
        if ($line->over) {
            return round($line->qtyOrdered * DemoBlueprint::OVER_RECEIPT_RATIO, 4);
        }

        if (! $line->short) {
            return $line->qtyOrdered;
        }

        return max(1.0, $line->qtyOrdered - max(1.0, floor($line->qtyOrdered * 0.05)));
    }

    /**
     * Schedules land within the first 20 days so a late receipt stays inside
     * the same month, keeping monthly aggregates clean.
     *
     * @param  int  $minDayOffset  Earliest day of month the schedule may take, for
     *                             orders whose first receipt arrives ahead of schedule.
     */
    private function scheduleFor(Carbon $month, int $index, int $minDayOffset = 0): Carbon
    {
        $span = 20 - $minDayOffset;

        return $month->copy()->startOfMonth()->addDays($minDayOffset + ($index % $span));
    }

    /**
     * Late and short lines always draw from the problem-material set, which is
     * what pins the critical-material count to exactly seven.
     */
    private function materialFor(int $index, bool $problem): string
    {
        if ($problem) {
            return DemoBlueprint::PROBLEM_MATERIALS[$index % count(DemoBlueprint::PROBLEM_MATERIALS)];
        }

        return $this->normalMaterials[$index % count($this->normalMaterials)];
    }

    private function quantityFor(int $index): float
    {
        return 100.0 + (($index * 7) % 20) * 50.0;
    }

    private function priceFor(int $index): float
    {
        return 5000.0 + (($index * 13) % 40) * 500.0;
    }

    private function nextNumber(string $prefix, string $stamp): string
    {
        $key = $prefix.$stamp;
        $this->sequence[$key] = ($this->sequence[$key] ?? 0) + 1;

        return sprintf('%s-%s-%04d', $prefix, $stamp, $this->sequence[$key]);
    }

    /**
     * Largest-remainder apportionment: the parts always sum back to $total.
     *
     * @param  array<string, float>  $weights
     * @return array<string, int>
     */
    private function distribute(int $total, array $weights): array
    {
        $sum = array_sum($weights);

        if ($sum <= 0.0 || $total <= 0) {
            return array_map(static fn (): int => 0, $weights);
        }

        $exact = [];
        $result = [];
        foreach ($weights as $key => $weight) {
            $exact[$key] = $total * $weight / $sum;
            $result[$key] = (int) floor($exact[$key]);
        }

        $remainders = [];
        foreach ($exact as $key => $value) {
            $remainders[$key] = $value - $result[$key];
        }
        arsort($remainders);

        $shortfall = $total - array_sum($result);
        foreach (array_keys($remainders) as $key) {
            if ($shortfall <= 0) {
                break;
            }
            $result[$key]++;
            $shortfall--;
        }

        return $result;
    }

    /**
     * Weighted apportionment that respects a per-key ceiling, redistributing
     * whatever a capped key could not take.
     *
     * @param  array<string, float>  $weights
     * @param  array<string, int>  $capacity
     * @return array<string, int>
     */
    private function capacityAwareDistribution(int $total, array $weights, array $capacity): array
    {
        $result = $this->distribute($total, $weights);
        $leftover = 0;

        foreach ($result as $key => $count) {
            $ceiling = max(0, $capacity[$key] ?? 0);

            if ($count > $ceiling) {
                $leftover += $count - $ceiling;
                $result[$key] = $ceiling;
            }
        }

        foreach (array_keys($result) as $key) {
            if ($leftover <= 0) {
                break;
            }

            $room = max(0, ($capacity[$key] ?? 0) - $result[$key]);
            $take = min($leftover, $room);
            $result[$key] += $take;
            $leftover -= $take;
        }

        return $result;
    }

    /**
     * Hand out $count units in priority order, never exceeding each capacity.
     *
     * @param  array<string, int>  $capacity
     * @param  array<int, string>  $priority
     * @return array<string, int>
     */
    private function spread(int $count, array $capacity, array $priority): array
    {
        $result = array_map(static fn (): int => 0, $capacity);

        foreach ($priority as $key) {
            if ($count <= 0) {
                break;
            }

            $take = min($count, $capacity[$key] ?? 0);
            $result[$key] = $take;
            $count -= $take;
        }

        return $result;
    }

    /**
     * @param  array<string, int>  $values
     * @return array<int, string>
     */
    private function byDescendingValue(array $values): array
    {
        arsort($values);

        return array_keys($values);
    }
}
