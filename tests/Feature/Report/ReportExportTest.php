<?php

declare(strict_types=1);

namespace Tests\Feature\Report;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\ReportType;
use App\Exports\ReportExport;
use App\Services\Performance\SupplierPerformanceService;
use App\Services\Report\ReportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Reporting - Phase 8's exit criterion.
 *
 * Four formats read one generator, so the tests that matter are the ones
 * proving they cannot drift apart: the same rows, the same headings, the same
 * labels for a status, and no format quietly loading the whole result set into
 * memory to do it.
 */
final class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $reports;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->reports = app(ReportService::class);
    }

    private function filter(): DashboardFilter
    {
        return DashboardFilter::currentMonth();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allRows(ReportType $type): array
    {
        return iterator_to_array($this->reports->dataset($type, $this->filter())->rows(), false);
    }

    #[Test]
    public function every_report_in_the_catalogue_produces_a_dataset(): void
    {
        foreach (ReportType::cases() as $type) {
            $dataset = $this->reports->dataset($type, $this->filter());

            $this->assertNotEmpty($dataset->columns, "{$type->value} has no columns");
            $this->assertSame($type, $dataset->type);
            $this->assertNotEmpty($dataset->headings());
        }
    }

    #[Test]
    public function the_catalogue_lists_every_report_type(): void
    {
        $catalogue = $this->reports->catalogue();

        $this->assertCount(count(ReportType::cases()), $catalogue);
        foreach ($catalogue as $entry) {
            $this->assertNotSame('', $entry['label']);
            $this->assertNotSame('', $entry['description']);
        }
    }

    #[Test]
    public function every_row_carries_exactly_the_declared_columns(): void
    {
        foreach (ReportType::cases() as $type) {
            $dataset = $this->reports->dataset($type, $this->filter());
            $keys = array_column(
                array_map(static fn ($c): array => $c->toArray(), $dataset->columns),
                'key',
            );

            foreach ($dataset->preview(5) as $row) {
                // A missing key would render as an empty cell in one format and
                // a dash in another.
                $this->assertSame([], array_diff($keys, array_keys($row)), "{$type->value} row is missing a column");
            }
        }
    }

    #[Test]
    public function the_delivery_report_covers_the_seeded_receipts(): void
    {
        $rows = $this->allRows(ReportType::DELIVERY);

        $expected = DB::table('delivery_items as di')
            ->join('deliveries as d', 'd.id', '=', 'di.delivery_id')
            ->where('d.status', '!=', 'CANCELLED')
            ->whereBetween('d.delivery_date', [$this->filter()->dateFrom, $this->filter()->dateTo])
            ->count();

        $this->assertSame($expected, count($rows));
        $this->assertGreaterThan(0, count($rows));
    }

    #[Test]
    public function a_cancelled_receipt_is_excluded_from_the_delivery_report(): void
    {
        $before = count($this->allRows(ReportType::DELIVERY));

        $delivery = DB::table('deliveries')
            ->whereBetween('delivery_date', [$this->filter()->dateFrom, $this->filter()->dateTo])
            ->first();
        $lines = DB::table('delivery_items')->where('delivery_id', $delivery->id)->count();

        DB::table('deliveries')->where('id', $delivery->id)->update(['status' => 'CANCELLED']);

        // A reversed receipt is not part of the period's performance, and a
        // report that still counted it would not match the dashboard.
        $this->assertSame($before - $lines, count($this->allRows(ReportType::DELIVERY)));
    }

    #[Test]
    public function statuses_are_written_as_labels_rather_than_raw_enum_values(): void
    {
        $row = $this->allRows(ReportType::DELIVERY)[0];

        $this->assertStringNotContainsString('_', (string) $row['overall_status']);
        $this->assertNotSame('ON_TIME_FULL', $row['overall_status']);
    }

    #[Test]
    public function the_supplier_report_matches_the_ranking_screen(): void
    {
        $rows = $this->allRows(ReportType::SUPPLIER_PERFORMANCE);
        $ranking = app(SupplierPerformanceService::class)
            ->getSupplierRanking($this->filter());

        // The report is the ranking, not a second opinion about it.
        $this->assertSame($ranking->count(), count($rows));
        $this->assertSame($ranking[0]['supplier_name'], $rows[0]['supplier_name']);
        $this->assertSame($ranking[0]['service_rate'], $rows[0]['service_rate']);
        $this->assertSame(1, $rows[0]['rank']);
    }

    #[Test]
    public function the_problem_report_counts_corrective_actions_without_multiplying_rows(): void
    {
        $rows = $this->allRows(ReportType::PROBLEM);

        $expected = DB::table('delivery_problems')
            ->whereBetween('problem_date', [$this->filter()->dateFrom, $this->filter()->dateTo])
            ->count();

        // Joining the actions instead of subquerying them would return one row
        // per action and inflate the whole export.
        $this->assertSame($expected, count($rows));
        $this->assertGreaterThan(0, array_sum(array_column($rows, 'action_count')));
    }

    #[Test]
    public function a_supplier_filter_narrows_every_report_that_has_a_supplier(): void
    {
        $supplierId = DB::table('suppliers')->where('code', 'SUP-001')->value('id');
        $filter = DashboardFilter::fromArray([
            'date_from' => $this->filter()->dateFrom,
            'date_to' => $this->filter()->dateTo,
            'supplier_id' => $supplierId,
        ]);

        foreach ([ReportType::DELIVERY, ReportType::PURCHASE_ORDER, ReportType::PROBLEM] as $type) {
            $rows = iterator_to_array($this->reports->dataset($type, $filter)->rows(), false);

            $this->assertNotEmpty($rows, "{$type->value} returned nothing for the filter");
            foreach ($rows as $row) {
                $this->assertStringStartsWith('SUP-001', (string) $row['supplier_name']);
            }
        }
    }

    #[Test]
    public function an_empty_period_produces_an_empty_report_rather_than_an_error(): void
    {
        $filter = DashboardFilter::fromArray(['date_from' => '2020-01-01', 'date_to' => '2020-01-31']);

        foreach (ReportType::cases() as $type) {
            $rows = iterator_to_array($this->reports->dataset($type, $filter)->rows(), false);

            $this->assertSame([], $rows, "{$type->value} should be empty");
        }
    }

    #[Test]
    public function the_dataset_streams_rather_than_materialising_its_rows(): void
    {
        $dataset = $this->reports->dataset(ReportType::DELIVERY, $this->filter());

        $before = memory_get_usage();
        $preview = $dataset->preview(5);
        $after = memory_get_usage();

        $this->assertCount(5, $preview);
        // Taking five rows must not cost what taking all of them would; the
        // generator stops at five and the cursor is never drained.
        $this->assertLessThan(2 * 1024 * 1024, $after - $before);
    }

    #[Test]
    public function the_preview_is_capped_and_the_full_set_is_not(): void
    {
        $dataset = $this->reports->dataset(ReportType::DELIVERY, $this->filter());

        $this->assertCount(25, $dataset->preview(25));
        $this->assertGreaterThan(25, count(iterator_to_array($dataset->rows(), false)));
    }

    #[Test]
    public function the_export_writes_the_headings_and_every_row(): void
    {
        $dataset = $this->reports->dataset(ReportType::SUPPLIER_PERFORMANCE, $this->filter());
        $export = new ReportExport($dataset);

        $written = iterator_to_array($export->generator(), false);

        $this->assertSame($dataset->headings(), $export->headings());
        $this->assertCount(count($this->allRows(ReportType::SUPPLIER_PERFORMANCE)), $written);
        // Flattened to the column order, so the values line up under their
        // headings whatever order the row array happens to be in.
        $this->assertCount(count($dataset->columns), $written[0]);
    }

    #[Test]
    public function the_sheet_name_is_safe_for_excel(): void
    {
        foreach (ReportType::cases() as $type) {
            $title = (new ReportExport($this->reports->dataset($type, $this->filter())))->title();

            $this->assertLessThanOrEqual(31, strlen($title));
            $this->assertDoesNotMatchRegularExpression('#[\\\\/?*\[\]:]#', $title);
        }
    }

    #[Test]
    public function numeric_columns_are_formatted_as_numbers(): void
    {
        $dataset = $this->reports->dataset(ReportType::SUPPLIER_PERFORMANCE, $this->filter());
        $formats = (new ReportExport($dataset))->columnFormats();

        // A quantity stored as text is a column a spreadsheet refuses to sum.
        $this->assertArrayHasKey('A', $formats);
        $this->assertArrayNotHasKey('B', $formats);
    }

    #[Test]
    public function counts_and_ranks_carry_no_decimals(): void
    {
        $dataset = $this->reports->dataset(ReportType::SUPPLIER_PERFORMANCE, $this->filter());
        $byKey = [];

        foreach ($dataset->columns as $column) {
            $byKey[$column->key] = $column;
        }

        // Rank 1 rendered as "1,00" reads as a measurement rather than an
        // ordinal, and a delivery count is never fractional.
        $this->assertSame(0, $byKey['rank']->decimals);
        $this->assertSame(0, $byKey['total_delivery']->decimals);
        // A rate is measured, so it keeps its decimals.
        $this->assertSame(2, $byKey['service_rate']->decimals);
    }

    #[Test]
    public function the_spreadsheet_formats_integers_and_decimals_differently(): void
    {
        $dataset = $this->reports->dataset(ReportType::SUPPLIER_PERFORMANCE, $this->filter());
        $formats = (new ReportExport($dataset))->columnFormats();

        // A: rank (integer), H: service rate (two decimals).
        $this->assertSame('0', $formats['A']);
        $this->assertSame('0.00', $formats['H']);
    }

    #[Test]
    public function the_filename_carries_the_report_and_its_period(): void
    {
        $dataset = $this->reports->dataset(ReportType::DELIVERY, $this->filter());

        $this->assertSame(
            'laporan-delivery-'.$this->filter()->dateFrom.'-'.$this->filter()->dateTo,
            $dataset->filename(),
        );
    }

    #[Test]
    public function an_xlsx_download_is_produced_for_every_report(): void
    {
        Excel::fake();

        foreach (ReportType::cases() as $type) {
            $this->actingAs($this->userWithRole('MANAGEMENT'))
                ->get(route('reports.export', ['type' => $type->value, 'format' => 'xlsx']))
                ->assertOk();

            Excel::assertDownloaded($this->reports->dataset($type, $this->filter())->filename().'.xlsx');
        }
    }

    #[Test]
    public function a_csv_download_is_produced(): void
    {
        Excel::fake();

        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.export', ['type' => 'problem', 'format' => 'csv']))
            ->assertOk();

        Excel::assertDownloaded($this->reports->dataset(ReportType::PROBLEM, $this->filter())->filename().'.csv');
    }
}
