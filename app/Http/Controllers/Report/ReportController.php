<?php

declare(strict_types=1);

namespace App\Http\Controllers\Report;

use App\DataTransferObjects\ReportColumn;
use App\DataTransferObjects\ReportDataset;
use App\Enums\AuditAction;
use App\Enums\ReportType;
use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportRequest;
use App\Models\MaterialCategory;
use App\Models\Plant;
use App\Models\Supplier;
use App\Services\Audit\AuditLogService;
use App\Services\Report\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Reporting.
 *
 * One catalogue screen with a live preview, and one export action that writes
 * the same dataset as .xlsx, .csv, a PDF, or a print-ready page. The four
 * formats read the identical generator, so a spreadsheet and a printout of the
 * same report can never disagree.
 */
class ReportController extends Controller
{
    /**
     * How many rows a PDF or print view carries.
     *
     * A paginated document is for reading, not for holding a year of receipts;
     * past this the footer points the reader at the spreadsheet, which streams
     * without limit.
     */
    private const DOCUMENT_ROW_LIMIT = 2000;

    private const PREVIEW_ROWS = 25;

    public function __construct(
        private readonly ReportService $reports,
        private readonly AuditLogService $audit,
    ) {}

    public function index(ReportRequest $request): InertiaResponse
    {
        $type = $request->reportType();
        $filter = $request->toFilter();
        $dataset = $this->reports->dataset($type, $filter);

        return Inertia::render('Reports/Index', [
            'catalogue' => $this->reports->catalogue(),
            'selected' => $type->value,
            'filters' => $filter->toArray(),
            'formats' => ReportRequest::FORMATS,
            'columns' => array_map(
                static fn (ReportColumn $column): array => $column->toArray(),
                $dataset->columns,
            ),
            // A sample of the real thing, so nobody downloads a file to find
            // out it is empty.
            'preview' => $dataset->preview(self::PREVIEW_ROWS),
            'previewLimit' => self::PREVIEW_ROWS,
            'options' => $this->filterOptions(),
            'can' => ['export' => $request->user()?->can('report.export') ?? false],
        ]);
    }

    /**
     * Write the report out.
     *
     * Exports are audited: who took what data out of the system, and for which
     * period, is exactly the question an auditor asks first.
     */
    public function export(ReportRequest $request, string $type): BinaryFileResponse|Response
    {
        $this->authorizeExport();

        $reportType = ReportType::tryFrom($type) ?? abort(404);
        $filter = $request->toFilter();
        $dataset = $this->reports->dataset($reportType, $filter);
        $format = $request->exportFormat();

        $this->audit->record(AuditAction::EXPORTED, 'Report', null, null, [
            'report' => $reportType->value,
            'format' => $format,
            'date_from' => $filter->dateFrom,
            'date_to' => $filter->dateTo,
        ]);

        return match ($format) {
            'csv' => Excel::download(new ReportExport($dataset), $dataset->filename().'.csv', \Maatwebsite\Excel\Excel::CSV),
            'pdf' => $this->pdf($dataset),
            'print' => $this->printable($dataset),
            default => Excel::download(new ReportExport($dataset), $dataset->filename().'.xlsx'),
        };
    }

    private function pdf(ReportDataset $dataset): Response
    {
        return $this->asDocument(fn (): Response => Pdf::loadView('reports.document', $this->documentData($dataset))
            ->setPaper('a4', 'landscape')
            ->download($dataset->filename().'.pdf'));
    }

    /**
     * The same document as HTML, for the browser's own print dialog.
     */
    private function printable(ReportDataset $dataset): Response
    {
        return $this->asDocument(fn (): Response => response()->view('reports.document', $this->documentData($dataset)));
    }

    /**
     * Render a printed document in the fixed document language.
     *
     * DomPDF draws with DejaVu Sans, which carries no CJK glyphs: a Japanese or
     * Chinese report would come out as empty boxes rather than as an error - the
     * kind of failure that reaches a manager's desk unnoticed. Rather than let
     * the language of a filed document depend on who happened to export it, both
     * the PDF and the printable HTML use config('locales.documents'), so the same
     * report is the same document whoever produced it.
     *
     * The interface stays in the reader's own language; only the artefact is
     * fixed. Excel exports are unaffected - PhpSpreadsheet writes UTF-8 - and so
     * they follow the interface language.
     *
     * @param  callable(): Response  $render
     */
    private function asDocument(callable $render): Response
    {
        $previous = app()->getLocale();
        app()->setLocale((string) config('locales.documents'));

        try {
            return $render();
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function documentData(ReportDataset $dataset): array
    {
        $rows = [];

        foreach ($dataset->rows() as $row) {
            if (count($rows) >= self::DOCUMENT_ROW_LIMIT) {
                break;
            }

            $rows[] = $row;
        }

        return [
            'dataset' => $dataset,
            'rows' => $rows,
            'limit' => self::DOCUMENT_ROW_LIMIT,
            'truncated' => count($rows) >= self::DOCUMENT_ROW_LIMIT,
            'printedBy' => request()->user()?->name,
        ];
    }

    /**
     * Viewing a report and taking its data out of the building are different
     * permissions, so the export action checks the second one explicitly.
     */
    private function authorizeExport(): void
    {
        abort_unless(request()->user()?->can('report.export') ?? false, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn (Supplier $s): array => ['value' => $s->id, 'label' => $s->code.' - '.$s->name])->all(),
            'plants' => Plant::query()->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn (Plant $p): array => ['value' => $p->id, 'label' => $p->code.' - '.$p->name])->all(),
            'materialCategories' => MaterialCategory::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (MaterialCategory $c): array => ['value' => $c->id, 'label' => $c->name])->all(),
        ];
    }
}
