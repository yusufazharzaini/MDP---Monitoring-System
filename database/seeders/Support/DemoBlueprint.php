<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * The single source of truth for the demo dataset's shape.
 *
 * The reference dashboard's headline KPIs and its per-supplier service rates
 * are arithmetically incompatible with each other (the five named suppliers
 * alone would need 56 late lines, while the KPI cards allow only 40 in total).
 * This blueprint reproduces BOTH the headline figures and the five published
 * service rates exactly, by giving the named suppliers the volumes that make
 * their rates come out right and letting the remaining suppliers carry the
 * balance of the month. See docs/05-DEMO-DATA.md.
 */
final class DemoBlueprint
{
    /**
     * Current-month allocation: [supplier code => [lines, late lines]].
     *
     * 1250 lines, 40 late  -> service rate 96.8%
     *  A 246/250 = 98.40%   B 171/180 = 95.00%   C 205/220 = 93.18%
     *  D  26/30  = 86.67%   E 112/120 = 93.33%
     *
     * @var array<string, array{int, int}>
     */
    public const CURRENT_MONTH_ALLOCATION = [
        'SUP-001' => [250, 4],
        'SUP-002' => [180, 9],
        'SUP-003' => [220, 15],
        'SUP-004' => [30, 4],
        'SUP-005' => [120, 8],
        'SUP-006' => [160, 0],
        'SUP-007' => [150, 0],
        'SUP-008' => [140, 0],
    ];

    /**
     * Trend targets for the five months preceding the current one, chosen so
     * the service-rate line reproduces the reference chart.
     *
     * @var array<int, array{offset: int, total: int, late: int, short: int}>
     */
    public const PRIOR_MONTHS = [
        ['offset' => 5, 'total' => 250, 'late' => 7, 'short' => 4, 'split' => 2, 'over' => 1],   // 97.2%
        ['offset' => 4, 'total' => 200, 'late' => 7, 'short' => 3, 'split' => 2, 'over' => 1],   // 96.5%
        ['offset' => 3, 'total' => 320, 'late' => 6, 'short' => 5, 'split' => 2, 'over' => 1],   // 98.1%
        ['offset' => 2, 'total' => 240, 'late' => 10, 'short' => 4, 'split' => 2, 'over' => 1],  // 95.8%
        ['offset' => 1, 'total' => 300, 'late' => 9, 'short' => 5, 'split' => 2, 'over' => 1],   // 97.0%
    ];

    public const CURRENT_MONTH_SHORT_LINES = 18;

    /**
     * Order lines fulfilled by two receipts instead of one - genuine split
     * shipments, so partial and multiple delivery are visible in the demo data
     * and not merely supported by the schema.
     *
     * Each split contributes two delivery lines and one SHORT line (the first,
     * cumulatively incomplete receipt). Both receipts land on or before the
     * scheduled date, so a split never changes a supplier's service rate.
     */
    public const CURRENT_MONTH_SPLIT_LINES = 6;

    /** Fraction of the ordered quantity delivered by the first receipt of a split. */
    public const SPLIT_FIRST_RECEIPT_RATIO = 0.4;

    /**
     * Lines received above the ordered quantity, producing OVER_DELIVERY rows.
     *
     * An over-receipt is punctual and satisfies its line, so it changes neither
     * the late count nor the short count nor the total - it simply replaces a
     * clean line, which is what keeps every published figure intact.
     */
    public const CURRENT_MONTH_OVER_LINES = 4;

    /** Fraction of the ordered quantity actually received on an over-delivery. */
    public const OVER_RECEIPT_RATIO = 1.05;

    /**
     * Materials that carry the current month's late and short lines. Together
     * with the two extra flagged materials below they produce exactly seven
     * critical materials for the period.
     *
     * @var array<int, string>
     */
    public const PROBLEM_MATERIALS = ['MAT-0001', 'MAT-0002', 'MAT-0003', 'MAT-0004', 'MAT-0005'];

    /**
     * Materials flagged is_critical that deliver cleanly, so they are counted
     * by the is_critical rule rather than by the late/short rules.
     *
     * @var array<int, string>
     */
    public const EXTRA_CRITICAL_MATERIALS = ['MAT-0006', 'MAT-0007'];

    /**
     * Every material carrying materials.is_critical = true.
     *
     * MAT-0001/0002 overlap with the problem set on purpose: the union of the
     * four rules is {0001..0007} - seven materials.
     *
     * @var array<int, string>
     */
    public const CRITICAL_FLAGGED_MATERIALS = ['MAT-0001', 'MAT-0002', 'MAT-0006', 'MAT-0007'];

    public const EXPECTED_CRITICAL_MATERIALS = 7;

    /**
     * Pareto targets for the current month: 83 problems whose cumulative
     * percentages reproduce the reference chart (46 / 75 / 89 / 96 / 100).
     *
     * @var array<string, int>
     */
    public const PROBLEM_DISTRIBUTION = [
        'LATE_DELIVERY' => 38,
        'SHORT_DELIVERY' => 24,
        'WRONG_MATERIAL' => 12,
        'DOCUMENT_PROBLEM' => 6,
        'SCHEDULE_PROBLEM' => 3,
    ];

    /**
     * @return array<int, string>
     */
    public static function supplierCodes(): array
    {
        return array_keys(self::CURRENT_MONTH_ALLOCATION);
    }

    public static function currentMonthTotal(): int
    {
        return array_sum(array_column(self::CURRENT_MONTH_ALLOCATION, 0));
    }

    public static function currentMonthLate(): int
    {
        return array_sum(array_column(self::CURRENT_MONTH_ALLOCATION, 1));
    }
}
