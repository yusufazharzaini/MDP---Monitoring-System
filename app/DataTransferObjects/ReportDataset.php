<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\ReportType;
use Closure;
use Generator;
use Illuminate\Support\Carbon;

/**
 * A report, ready to be written out.
 *
 * The rows are a closure returning a generator rather than an array: a year of
 * receipts is thousands of rows, and every writer here - Excel, PDF, the on
 * screen preview - walks them once. Holding them in memory would defeat the
 * aggregation the queries were written to do.
 */
final readonly class ReportDataset
{
    /**
     * @param  array<int, ReportColumn>  $columns
     * @param  Closure(): Generator  $rows
     */
    public function __construct(
        public ReportType $type,
        public DashboardFilter $filter,
        public array $columns,
        private Closure $rows,
        public Carbon $generatedAt,
    ) {}

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function rows(): Generator
    {
        return ($this->rows)();
    }

    /**
     * The first `$limit` rows, for the preview the screen shows before anyone
     * commits to a download.
     *
     * @return array<int, array<string, mixed>>
     */
    public function preview(int $limit = 25): array
    {
        $rows = [];

        foreach ($this->rows() as $row) {
            if (count($rows) >= $limit) {
                break;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_map(static fn (ReportColumn $column): string => $column->label, $this->columns);
    }

    /**
     * One row flattened to the column order, for a writer that wants a list.
     *
     * @param  array<string, mixed>  $row
     * @return array<int, mixed>
     */
    public function values(array $row): array
    {
        return array_map(
            static fn (ReportColumn $column): mixed => $row[$column->key] ?? null,
            $this->columns,
        );
    }

    public function title(): string
    {
        return $this->type->label();
    }

    public function periodLabel(): string
    {
        return $this->filter->dateFrom.' '.__('ui.common.to').' '.$this->filter->dateTo;
    }

    /**
     * `laporan-delivery-2026-08-01-2026-08-31`
     */
    public function filename(): string
    {
        return sprintf('%s-%s-%s', $this->type->filename(), $this->filter->dateFrom, $this->filter->dateTo);
    }
}
