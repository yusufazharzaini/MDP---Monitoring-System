<?php

declare(strict_types=1);

namespace Tests\Feature\Report;

use App\Enums\ReportType;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The reporting screens, and the split between reading a report and taking its
 * data out of the building.
 */
final class ReportScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function the_catalogue_lists_the_reports_with_a_live_preview(): void
    {
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/Index')
                ->has('catalogue', count(ReportType::cases()))
                ->has('columns')
                ->has('preview')
                ->where('selected', ReportType::DELIVERY->value)
                ->where('can.export', true));
    }

    #[Test]
    public function choosing_a_report_changes_the_columns_and_the_preview(): void
    {
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.index', ['type' => ReportType::SUPPLIER_PERFORMANCE->value]))
            ->assertInertia(function ($page) {
                $props = $page->toArray()['props'];

                $this->assertSame(ReportType::SUPPLIER_PERFORMANCE->value, $props['selected']);
                $this->assertSame('Rank', $props['columns'][0]['label']);
                $this->assertNotEmpty($props['preview']);
            });
    }

    #[Test]
    public function the_preview_is_capped_so_the_page_stays_small(): void
    {
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.index'))
            ->assertInertia(function ($page) {
                $props = $page->toArray()['props'];

                $this->assertLessThanOrEqual($props['previewLimit'], count($props['preview']));
            });
    }

    #[Test]
    public function an_unknown_report_type_falls_back_rather_than_erroring_on_the_catalogue(): void
    {
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.index', ['type' => 'tidak-ada']))
            ->assertSessionHasErrors('type');
    }

    #[Test]
    public function an_unknown_report_type_is_not_exportable(): void
    {
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.export', ['type' => 'tidak-ada', 'format' => 'xlsx']))
            ->assertNotFound();
    }

    #[Test]
    public function a_reader_without_report_view_cannot_reach_the_catalogue(): void
    {
        $this->actingAs($this->userWithPermissions(['dashboard.view']))
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    #[Test]
    public function viewing_a_report_does_not_grant_the_right_to_export_it(): void
    {
        // WAREHOUSE holds report.view but not report.export.
        $clerk = $this->userWithRole('WAREHOUSE');

        $this->actingAs($clerk)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.export', false));

        $this->actingAs($clerk)
            ->get(route('reports.export', ['type' => 'delivery', 'format' => 'xlsx']))
            ->assertForbidden();
    }

    #[Test]
    public function a_pdf_is_rendered_for_download(): void
    {
        $response = $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.export', ['type' => 'supplier-performance', 'format' => 'pdf']));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString(
            'laporan-performa-supplier',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    #[Test]
    public function the_print_view_is_html_carrying_the_same_rows(): void
    {
        $response = $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.export', ['type' => 'supplier-performance', 'format' => 'print']));

        $response->assertOk();
        $response->assertSee('PT. YUSUF AZHAR ZAINI');
        $response->assertSee('Laporan Performa Supplier');
        // The same data the PDF carries, in a page the browser can print.
        $response->assertSee('Supplier A');
        $response->assertSee('Service Rate (%)');
    }

    #[Test]
    public function the_document_names_the_period_and_who_printed_it(): void
    {
        $user = $this->userWithRole('MANAGEMENT');

        $this->actingAs($user)
            ->get(route('reports.export', [
                'type' => 'delivery',
                'format' => 'print',
                'date_from' => '2026-08-01',
                'date_to' => '2026-08-31',
            ]))
            ->assertSee('2026-08-01 s/d 2026-08-31')
            ->assertSee($user->name);
    }

    #[Test]
    public function an_empty_period_prints_a_statement_rather_than_a_blank_table(): void
    {
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.export', [
                'type' => 'delivery',
                'format' => 'print',
                'date_from' => '2020-01-01',
                'date_to' => '2020-01-31',
            ]))
            ->assertOk()
            ->assertSee('Tidak ada data pada periode ini.');
    }

    #[Test]
    public function an_invalid_format_is_refused(): void
    {
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.export', ['type' => 'delivery', 'format' => 'docx']))
            ->assertSessionHasErrors('format');
    }

    #[Test]
    public function a_reversed_date_range_is_refused(): void
    {
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.index', ['date_from' => '2026-08-31', 'date_to' => '2026-08-01']))
            ->assertSessionHasErrors('date_to');
    }

    #[Test]
    public function every_export_is_written_to_the_audit_trail(): void
    {
        Excel::fake();

        $user = $this->userWithRole('MANAGEMENT');

        $this->actingAs($user)->get(route('reports.export', [
            'type' => 'problem',
            'format' => 'xlsx',
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]))->assertOk();

        // Who took what data out, and for which period, is the first question
        // an auditor asks.
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Report',
            'action' => 'EXPORTED',
            'user_id' => $user->getKey(),
        ]);
    }

    #[Test]
    public function a_refused_export_leaves_no_audit_entry(): void
    {
        $this->actingAs($this->userWithRole('WAREHOUSE'))
            ->get(route('reports.export', ['type' => 'delivery', 'format' => 'xlsx']))
            ->assertForbidden();

        // The trail records what happened, not what was attempted and blocked
        // before the service ever ran.
        $this->assertDatabaseMissing('audit_logs', ['module' => 'Report', 'action' => 'EXPORTED']);
    }
}
