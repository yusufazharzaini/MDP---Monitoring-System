<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\DataTransferObjects\DashboardFilter;
use App\DataTransferObjects\ReportColumn;
use App\DataTransferObjects\ReportDataset;
use App\Enums\DeliveryItemCondition;
use App\Enums\OverallDeliveryStatus;
use App\Enums\ProblemSeverity;
use App\Enums\ProblemStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\QuantityStatus;
use App\Enums\ReportType;
use App\Enums\TimelinessStatus;
use App\Repositories\ReportRepository;
use App\Services\Dashboard\CriticalMaterialService;
use App\Services\Performance\SupplierPerformanceService;
use Generator;
use Illuminate\Support\Carbon;

/**
 * Assembles the reports.
 *
 * Each dataset is a column list plus a generator, so the writers - Excel, PDF,
 * the on screen preview - all walk the same rows once and none of them holds
 * the set in memory. Enum values are turned into their labels here rather than
 * in a template, so an .xlsx and a .pdf of the same report never disagree about
 * what a status is called.
 */
class ReportService
{
    public function __construct(
        private readonly ReportRepository $repository,
        private readonly SupplierPerformanceService $performance,
        private readonly CriticalMaterialService $criticalMaterials,
    ) {}

    public function dataset(ReportType $type, DashboardFilter $filter): ReportDataset
    {
        return match ($type) {
            ReportType::DELIVERY => $this->deliveryReport($filter),
            ReportType::PURCHASE_ORDER => $this->purchaseOrderReport($filter),
            ReportType::SUPPLIER_PERFORMANCE => $this->supplierPerformanceReport($filter),
            ReportType::PROBLEM => $this->problemReport($filter),
            ReportType::CRITICAL_MATERIAL => $this->criticalMaterialReport($filter),
        };
    }

    /**
     * The catalogue the index screen lists.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalogue(): array
    {
        return array_map(static fn (ReportType $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'row_level' => $type->isRowLevel(),
        ], ReportType::cases());
    }

    private function deliveryReport(DashboardFilter $filter): ReportDataset
    {
        return new ReportDataset(
            type: ReportType::DELIVERY,
            filter: $filter,
            columns: [
                ReportColumn::text('delivery_number', 'No Delivery'),
                ReportColumn::text('delivery_date', 'Tanggal Terima'),
                ReportColumn::text('do_number', 'No Surat Jalan'),
                ReportColumn::text('po_number', 'No PO'),
                ReportColumn::text('supplier_name', 'Supplier'),
                ReportColumn::text('plant_name', 'Plant'),
                ReportColumn::text('material_code', 'Kode Material'),
                ReportColumn::text('material_name', 'Material'),
                ReportColumn::text('uom_code', 'Satuan'),
                ReportColumn::text('schedule_delivery_date', 'Jadwal'),
                ReportColumn::number('qty_ordered', 'Qty PO'),
                ReportColumn::number('qty_received', 'Qty Terima'),
                ReportColumn::text('condition', 'Kondisi'),
                ReportColumn::text('timeliness_status', 'Ketepatan Waktu'),
                ReportColumn::text('quantity_status', 'Status Quantity'),
                ReportColumn::text('overall_status', 'Status Keseluruhan'),
                ReportColumn::integer('days_late', 'Hari Terlambat'),
            ],
            rows: fn (): Generator => $this->streamDeliveryLines($filter),
            generatedAt: Carbon::now(),
        );
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private function streamDeliveryLines(DashboardFilter $filter): Generator
    {
        foreach ($this->repository->deliveryLines($filter) as $row) {
            yield [
                'delivery_number' => $row->delivery_number,
                'delivery_date' => $this->date($row->delivery_date),
                'do_number' => $row->do_number ?? '-',
                'po_number' => $row->po_number ?? '-',
                'supplier_name' => $row->supplier_code.' - '.$row->supplier_name,
                'plant_name' => $row->plant_name,
                'material_code' => $row->material_code,
                'material_name' => $row->material_name,
                'uom_code' => $row->uom_code ?? '-',
                'schedule_delivery_date' => $this->date($row->schedule_delivery_date),
                'qty_ordered' => (float) ($row->qty_ordered ?? 0),
                'qty_received' => (float) $row->qty_received,
                'condition' => DeliveryItemCondition::from($row->condition)->label(),
                'timeliness_status' => TimelinessStatus::from($row->timeliness_status)->label(),
                'quantity_status' => QuantityStatus::from($row->quantity_status)->label(),
                'overall_status' => OverallDeliveryStatus::from($row->overall_status)->label(),
                'days_late' => (int) $row->days_late,
            ];
        }
    }

    private function purchaseOrderReport(DashboardFilter $filter): ReportDataset
    {
        return new ReportDataset(
            type: ReportType::PURCHASE_ORDER,
            filter: $filter,
            columns: [
                ReportColumn::text('po_number', 'No PO'),
                ReportColumn::text('po_date', 'Tanggal PO'),
                ReportColumn::text('po_status', 'Status PO'),
                ReportColumn::text('supplier_name', 'Supplier'),
                ReportColumn::text('plant_name', 'Plant'),
                ReportColumn::integer('line_no', 'Baris'),
                ReportColumn::text('material_code', 'Kode Material'),
                ReportColumn::text('material_name', 'Material'),
                ReportColumn::text('uom_code', 'Satuan'),
                ReportColumn::text('schedule_delivery_date', 'Jadwal'),
                ReportColumn::number('qty_ordered', 'Qty PO'),
                ReportColumn::number('qty_received', 'Qty Terima'),
                ReportColumn::number('outstanding', 'Outstanding'),
                ReportColumn::number('unit_price', 'Harga Satuan'),
                ReportColumn::number('amount', 'Nilai'),
                ReportColumn::text('fulfillment_status', 'Pemenuhan'),
                ReportColumn::text('overall_status', 'Status Keseluruhan'),
                ReportColumn::text('last_receipt_date', 'Penerimaan Terakhir'),
            ],
            rows: fn (): Generator => $this->streamOrderLines($filter),
            generatedAt: Carbon::now(),
        );
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private function streamOrderLines(DashboardFilter $filter): Generator
    {
        foreach ($this->repository->orderLines($filter) as $row) {
            $ordered = (float) $row->qty_ordered;
            $received = (float) $row->qty_received;

            yield [
                'po_number' => $row->po_number,
                'po_date' => $this->date($row->po_date),
                'po_status' => PurchaseOrderStatus::from($row->po_status)->label(),
                'supplier_name' => $row->supplier_code.' - '.$row->supplier_name,
                'plant_name' => $row->plant_name,
                'line_no' => (int) $row->line_no,
                'material_code' => $row->material_code,
                'material_name' => $row->material_name,
                'uom_code' => $row->uom_code ?? '-',
                'schedule_delivery_date' => $this->date($row->schedule_delivery_date),
                'qty_ordered' => $ordered,
                'qty_received' => $received,
                'outstanding' => round(max(0.0, $ordered - $received), 4),
                'unit_price' => (float) $row->unit_price,
                'amount' => (float) $row->amount,
                'fulfillment_status' => QuantityStatus::from($row->fulfillment_status)->label(),
                'overall_status' => OverallDeliveryStatus::from($row->overall_status)->label(),
                'last_receipt_date' => $this->date($row->last_receipt_date),
            ];
        }
    }

    private function problemReport(DashboardFilter $filter): ReportDataset
    {
        return new ReportDataset(
            type: ReportType::PROBLEM,
            filter: $filter,
            columns: [
                ReportColumn::text('problem_number', 'No Problem'),
                ReportColumn::text('problem_date', 'Tanggal'),
                ReportColumn::text('delivery_number', 'No Delivery'),
                ReportColumn::text('supplier_name', 'Supplier'),
                ReportColumn::text('material_name', 'Material'),
                ReportColumn::text('category_name', 'Kategori'),
                ReportColumn::text('severity', 'Severity'),
                ReportColumn::text('status', 'Status'),
                ReportColumn::text('pic', 'PIC'),
                ReportColumn::text('due_date', 'Target Selesai'),
                ReportColumn::integer('action_count', 'Corrective Action'),
                ReportColumn::integer('action_done_count', 'Action Selesai'),
                ReportColumn::text('root_cause', 'Root Cause'),
            ],
            rows: fn (): Generator => $this->streamProblems($filter),
            generatedAt: Carbon::now(),
        );
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private function streamProblems(DashboardFilter $filter): Generator
    {
        foreach ($this->repository->problems($filter) as $row) {
            yield [
                'problem_number' => $row->problem_number,
                'problem_date' => $this->date($row->problem_date),
                'delivery_number' => $row->delivery_number,
                'supplier_name' => $row->supplier_code.' - '.$row->supplier_name,
                'material_name' => $row->material_name ?? 'Tidak spesifik',
                'category_name' => $row->category_name,
                'severity' => ProblemSeverity::from($row->severity)->label(),
                'status' => ProblemStatus::from($row->status)->label(),
                'pic' => $row->pic ?? '-',
                'due_date' => $this->date($row->due_date),
                'action_count' => (int) $row->action_count,
                'action_done_count' => (int) $row->action_done_count,
                'root_cause' => $row->root_cause ?? '-',
            ];
        }
    }

    private function supplierPerformanceReport(DashboardFilter $filter): ReportDataset
    {
        return new ReportDataset(
            type: ReportType::SUPPLIER_PERFORMANCE,
            filter: $filter,
            columns: [
                ReportColumn::integer('rank', 'Rank'),
                ReportColumn::text('supplier_code', 'Kode'),
                ReportColumn::text('supplier_name', 'Supplier'),
                ReportColumn::integer('total_delivery', 'Total Delivery'),
                ReportColumn::integer('on_time_delivery', 'Tepat Waktu'),
                ReportColumn::integer('late_delivery', 'Terlambat'),
                ReportColumn::integer('short_delivery', 'Quantity Kurang'),
                ReportColumn::number('service_rate', 'Service Rate (%)'),
                ReportColumn::text('grade', 'Grade'),
            ],
            rows: fn (): Generator => $this->streamSupplierPerformance($filter),
            generatedAt: Carbon::now(),
        );
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private function streamSupplierPerformance(DashboardFilter $filter): Generator
    {
        foreach ($this->performance->getSupplierRanking($filter) as $row) {
            yield [
                'rank' => $row['rank'],
                'supplier_code' => $row['supplier_code'],
                'supplier_name' => $row['supplier_name'],
                'total_delivery' => $row['total_delivery'],
                'on_time_delivery' => $row['on_time_delivery'],
                'late_delivery' => $row['late_delivery'],
                'short_delivery' => $row['short_delivery'],
                'service_rate' => $row['service_rate'],
                'grade' => $row['grade_label'],
            ];
        }
    }

    private function criticalMaterialReport(DashboardFilter $filter): ReportDataset
    {
        return new ReportDataset(
            type: ReportType::CRITICAL_MATERIAL,
            filter: $filter,
            columns: [
                ReportColumn::text('material_code', 'Kode Material'),
                ReportColumn::text('material_name', 'Material'),
                ReportColumn::text('category', 'Kategori'),
                ReportColumn::text('uom', 'Satuan'),
                ReportColumn::integer('late_count', 'Terlambat'),
                ReportColumn::integer('short_count', 'Quantity Kurang'),
                ReportColumn::number('shortage_quantity', 'Kekurangan Qty'),
                ReportColumn::integer('critical_problem_count', 'Problem Critical'),
                ReportColumn::text('reasons', 'Alasan'),
                ReportColumn::text('risk_level', 'Tingkat Risiko'),
            ],
            rows: fn (): Generator => $this->streamCriticalMaterials($filter),
            generatedAt: Carbon::now(),
        );
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private function streamCriticalMaterials(DashboardFilter $filter): Generator
    {
        foreach ($this->criticalMaterials->getCriticalMaterials($filter) as $row) {
            yield [
                'material_code' => $row['material_code'],
                'material_name' => $row['material_name'],
                'category' => $row['category'],
                'uom' => $row['uom'],
                'late_count' => $row['late_count'],
                'short_count' => $row['short_count'],
                'shortage_quantity' => $row['shortage_quantity'],
                'critical_problem_count' => $row['critical_problem_count'],
                // Joined into one cell: a spreadsheet column holds one value,
                // and the reasons are read together or not at all.
                'reasons' => implode('; ', $row['reasons']),
                'risk_level' => $row['risk_label'],
            ];
        }
    }

    private function date(mixed $value): string
    {
        return $value === null ? '-' : Carbon::parse((string) $value)->toDateString();
    }
}
