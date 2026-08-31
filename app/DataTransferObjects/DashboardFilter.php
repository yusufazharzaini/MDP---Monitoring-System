<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The single filter object every dashboard query is driven by.
 *
 * Requirement 21 of the specification: the KPI cards, trend, supplier ranking,
 * Pareto chart, PO monitoring table and critical-material list must all agree.
 * They agree because they all narrow their population with this one object
 * instead of each assembling its own where clauses.
 */
final readonly class DashboardFilter
{
    public function __construct(
        public string $dateFrom,
        public string $dateTo,
        public ?int $plantId = null,
        public ?int $supplierId = null,
        public ?int $materialId = null,
        public ?int $materialCategoryId = null,
        public ?string $status = null,
    ) {}

    /**
     * Build from request input, defaulting to the current month.
     *
     * `period` (YYYY-MM) is a convenience for the month picker; an explicit
     * date_from/date_to pair overrides it.
     */
    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->all());
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        [$from, $to] = self::resolveRange($input);

        return new self(
            dateFrom: $from,
            dateTo: $to,
            plantId: self::intOrNull($input['plant_id'] ?? null),
            supplierId: self::intOrNull($input['supplier_id'] ?? null),
            materialId: self::intOrNull($input['material_id'] ?? null),
            materialCategoryId: self::intOrNull($input['material_category_id'] ?? null),
            status: self::stringOrNull($input['status'] ?? null),
        );
    }

    /**
     * The current month, unfiltered - the dashboard's landing state.
     */
    public static function currentMonth(): self
    {
        return new self(
            dateFrom: Carbon::now()->startOfMonth()->toDateString(),
            dateTo: Carbon::now()->endOfMonth()->toDateString(),
        );
    }

    /**
     * The same filter shifted onto another month, used to build the trend
     * series without letting each point drift from the others' criteria.
     */
    public function forMonth(Carbon $month): self
    {
        return new self(
            dateFrom: $month->copy()->startOfMonth()->toDateString(),
            dateTo: $month->copy()->endOfMonth()->toDateString(),
            plantId: $this->plantId,
            supplierId: $this->supplierId,
            materialId: $this->materialId,
            materialCategoryId: $this->materialCategoryId,
            status: $this->status,
        );
    }

    /**
     * The N months ending with this filter's own period, oldest first.
     *
     * @return array<int, self>
     */
    public function trailingMonths(int $months): array
    {
        $anchor = Carbon::parse($this->dateTo)->startOfMonth();

        return array_map(
            fn (int $offset): self => $this->forMonth($anchor->copy()->subMonths($offset)),
            array_reverse(range(0, $months - 1)),
        );
    }

    /**
     * The same filter widened to cover the N months ending with its own period.
     *
     * The trend chart needs one query over the whole window rather than one
     * query per point, so it widens the range and groups by month.
     */
    public function spanningMonths(int $months): self
    {
        $anchor = Carbon::parse($this->dateTo)->startOfMonth();

        return new self(
            dateFrom: $anchor->copy()->subMonths($months - 1)->startOfMonth()->toDateString(),
            dateTo: $anchor->copy()->endOfMonth()->toDateString(),
            plantId: $this->plantId,
            supplierId: $this->supplierId,
            materialId: $this->materialId,
            materialCategoryId: $this->materialCategoryId,
            status: $this->status,
        );
    }

    public function periodLabel(): string
    {
        return Carbon::parse($this->dateFrom)->format('Y-m');
    }

    public function hasScopeFilters(): bool
    {
        return $this->plantId !== null
            || $this->supplierId !== null
            || $this->materialId !== null
            || $this->materialCategoryId !== null
            || $this->status !== null;
    }

    /**
     * Echoed back in the dashboard payload so the UI can render its active state.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period' => $this->periodLabel(),
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'plant_id' => $this->plantId,
            'supplier_id' => $this->supplierId,
            'material_id' => $this->materialId,
            'material_category_id' => $this->materialCategoryId,
            'status' => $this->status,
        ];
    }

    /**
     * Stable key for caching an aggregate computed under this filter.
     */
    public function cacheKey(string $prefix): string
    {
        return $prefix.':'.md5(serialize($this->toArray()));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{string, string}
     */
    private static function resolveRange(array $input): array
    {
        $from = self::stringOrNull($input['date_from'] ?? null);
        $to = self::stringOrNull($input['date_to'] ?? null);

        if ($from !== null && $to !== null) {
            // Swap rather than reject: a reversed range is a UI slip, not an attack.
            return $from <= $to ? [$from, $to] : [$to, $from];
        }

        // Anchor on the first of the month explicitly. Parsing 'Y-m' alone
        // makes Carbon fill in today's day number, so '2026-02' read on a 30th
        // overflows into March.
        $period = self::stringOrNull($input['period'] ?? null);
        $anchor = $period !== null && preg_match('/^\d{4}-\d{2}$/', $period) === 1
            ? Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfMonth()
            : Carbon::now();

        return [
            $from ?? $anchor->copy()->startOfMonth()->toDateString(),
            $to ?? $anchor->copy()->endOfMonth()->toDateString(),
        ];
    }

    private static function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
