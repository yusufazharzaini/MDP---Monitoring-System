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
 */
final class DemoOrderPlanner
{
    /** Late lines in the current month that are also short, producing LATE_SHORT rows. */
    private const CURRENT_MONTH_LATE_AND_SHORT = 6;

    private const PENDING_ORDERS = 25;

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
            $allocation = $this->allocatePriorMonth($month['total'], $month['late']);
            $orders = [
                ...$orders,
                ...$this->buildMonth($month['offset'], $allocation, $month['short'], 0),
            ];
        }

        $orders = [
            ...$orders,
            ...$this->buildMonth(
                0,
                DemoBlueprint::CURRENT_MONTH_ALLOCATION,
                DemoBlueprint::CURRENT_MONTH_SHORT_LINES,
                self::CURRENT_MONTH_LATE_AND_SHORT,
            ),
            ...$this->buildPendingOrders(),
        ];

        return $orders;
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
     * @param  array<string, array{int, int}>  $allocation
     * @return array<int, DemoOrderSpec>
     */
    private function buildMonth(int $offset, array $allocation, int $shortLines, int $lateAndShort): array
    {
        $month = $this->anchorMonth->copy()->subMonths($offset);

        $lateCapacity = [];
        $volumeWeights = [];
        foreach ($allocation as $code => [$lines, $late]) {
            $lateCapacity[$code] = $late;
            $volumeWeights[$code] = (float) $lines;
        }

        $overlapPerSupplier = $this->spread(min($lateAndShort, array_sum($lateCapacity)), $lateCapacity, $this->byDescendingValue($lateCapacity));
        $shortOnlyTotal = max(0, $shortLines - array_sum($overlapPerSupplier));
        $shortOnlyPerSupplier = $this->distribute($shortOnlyTotal, $volumeWeights);

        $orders = [];
        $index = 0;

        foreach ($allocation as $code => [$lines, $late]) {
            $overlap = $overlapPerSupplier[$code] ?? 0;
            $shortOnly = min($shortOnlyPerSupplier[$code] ?? 0, max(0, $lines - $late));

            // Late lines get their own single-line order so the late count is exact.
            for ($i = 0; $i < $late; $i++) {
                $orders[] = $this->singleLineOrder($month, $code, $index++, late: true, short: $i < $overlap);
            }

            for ($i = 0; $i < $shortOnly; $i++) {
                $orders[] = $this->singleLineOrder($month, $code, $index++, late: false, short: true);
            }

            $remaining = $lines - $late - $shortOnly;
            $orders = [...$orders, ...$this->cleanOrders($month, $code, $remaining, $index)];
            $index += $remaining;
        }

        return $orders;
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

            $lines = [];
            for ($line = 0; $line < $size; $line++) {
                $lines[] = $this->makeLine($line + 1, $index + $line, late: false, short: false, delivered: true);
            }

            $orders[] = $this->makeOrder($month, $supplierCode, $index, $lines, daysLate: 0, delivered: true);

            $placed += $size;
            $group++;
        }

        return $orders;
    }

    private function singleLineOrder(Carbon $month, string $supplierCode, int $index, bool $late, bool $short): DemoOrderSpec
    {
        return $this->makeOrder(
            $month,
            $supplierCode,
            $index,
            [$this->makeLine(1, $index, $late, $short, delivered: true)],
            daysLate: $late ? 1 + ($index % 7) : 0,
            delivered: true,
        );
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
            $material = self::PENDING_MATERIALS[$i % count(self::PENDING_MATERIALS)];

            $line = new DemoLineSpec(
                lineNo: 1,
                materialCode: $material,
                qtyOrdered: $this->quantityFor($index),
                qtyReceived: 0.0,
                unitPrice: $this->priceFor($index),
                late: false,
                short: false,
                delivered: false,
            );

            $orders[] = $this->makeOrder(
                $this->anchorMonth,
                $codes[$i % count($codes)],
                $index,
                [$line],
                daysLate: 0,
                delivered: false,
            );
        }

        return $orders;
    }

    /**
     * @param  array<int, DemoLineSpec>  $lines
     */
    private function makeOrder(
        Carbon $month,
        string $supplierCode,
        int $index,
        array $lines,
        int $daysLate,
        bool $delivered,
    ): DemoOrderSpec {
        $schedule = $month->copy()->startOfMonth()->addDays($index % 20);
        $deliveryDate = $delivered ? $schedule->copy()->addDays($daysLate) : null;
        $stamp = $month->format('Ym');

        return new DemoOrderSpec(
            poNumber: $this->nextNumber('PO', $stamp),
            deliveryNumber: $this->nextNumber('DN', $stamp),
            supplierCode: $supplierCode,
            plantCode: $this->plantCodes[$index % count($this->plantCodes)],
            poDate: $schedule->copy()->subDays(14)->toDateString(),
            scheduleDate: $schedule->toDateString(),
            deliveryDate: $deliveryDate?->toDateString(),
            daysLate: $daysLate,
            lines: $lines,
        );
    }

    private function makeLine(int $lineNo, int $index, bool $late, bool $short, bool $delivered): DemoLineSpec
    {
        $ordered = $this->quantityFor($index);
        $received = $short ? max(1.0, $ordered - max(1.0, floor($ordered * 0.05))) : $ordered;

        return new DemoLineSpec(
            lineNo: $lineNo,
            materialCode: $this->materialFor($index, problem: $late || $short),
            qtyOrdered: $ordered,
            qtyReceived: $delivered ? $received : 0.0,
            unitPrice: $this->priceFor($index),
            late: $late,
            short: $short,
            delivered: $delivered,
        );
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
