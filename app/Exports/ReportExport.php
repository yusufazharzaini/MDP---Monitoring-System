<?php

declare(strict_types=1);

namespace App\Exports;

use App\DataTransferObjects\ReportDataset;
use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One export class for every report.
 *
 * FromGenerator rather than FromArray or FromCollection: the dataset yields its
 * rows from a database cursor, so a year of receipts is written a row at a time
 * instead of being assembled in memory first. Five near-identical export
 * classes would be the abstraction to avoid here, not this one.
 */
class ReportExport implements FromGenerator, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private readonly ReportDataset $dataset,
    ) {}

    public function generator(): Generator
    {
        foreach ($this->dataset->rows() as $row) {
            yield $this->dataset->values($row);
        }
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return $this->dataset->headings();
    }

    public function title(): string
    {
        // Excel refuses more than 31 characters and a handful of punctuation
        // marks in a sheet name.
        return substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $this->dataset->title()) ?? 'Laporan', 0, 31);
    }

    /**
     * Numeric columns are formatted as numbers so a spreadsheet sums them
     * instead of treating a quantity as text.
     *
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        $formats = [];

        foreach ($this->dataset->columns as $index => $column) {
            if ($column->numeric) {
                $formats[$this->columnLetter($index)] = $column->decimals === 0
                    ? NumberFormat::FORMAT_NUMBER
                    : NumberFormat::FORMAT_NUMBER_00;
            }
        }

        return $formats;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    /**
     * A, B, ... Z, AA, AB - the spreadsheet column at this position.
     */
    private function columnLetter(int $index): string
    {
        $letter = '';

        for ($n = $index; $n >= 0; $n = intdiv($n, 26) - 1) {
            $letter = chr(65 + $n % 26).$letter;
        }

        return $letter;
    }
}
