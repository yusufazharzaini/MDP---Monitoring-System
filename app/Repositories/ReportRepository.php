<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\DeliveryStatus;
use App\Enums\ProblemStatus;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Export dataset queries.
 *
 * Every one of these returns a lazy cursor rather than a collection: a year of
 * receipts is thousands of rows and the exporters walk them exactly once, so
 * nothing here ever holds the whole result set. Joins do the assembling in SQL
 * for the same reason - a per-row lookup would turn one query into thousands.
 */
class ReportRepository
{
    /**
     * One row per delivery line, which is the KPI measurement grain.
     *
     * @return LazyCollection<int, object>
     */
    public function deliveryLines(DashboardFilter $filter): LazyCollection
    {
        $query = DB::table('delivery_items as di')
            ->join('deliveries as d', 'd.id', '=', 'di.delivery_id')
            ->join('suppliers as s', 's.id', '=', 'd.supplier_id')
            ->join('plants as p', 'p.id', '=', 'd.plant_id')
            ->join('materials as m', 'm.id', '=', 'di.material_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'di.uom_id')
            ->leftJoin('purchase_order_items as poi', 'poi.id', '=', 'di.purchase_order_item_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->where('d.status', '!=', DeliveryStatus::CANCELLED->value)
            ->whereBetween('d.delivery_date', [$filter->dateFrom, $filter->dateTo])
            ->select([
                'd.delivery_number',
                'd.delivery_date',
                'd.do_number',
                'po.po_number',
                's.code as supplier_code',
                's.name as supplier_name',
                'p.name as plant_name',
                'm.code as material_code',
                'm.name as material_name',
                'u.code as uom_code',
                'poi.schedule_delivery_date',
                'poi.qty_ordered',
                'di.qty_received',
                'di.condition',
                'di.timeliness_status',
                'di.quantity_status',
                'di.overall_status',
                'di.days_late',
            ])
            ->orderBy('d.delivery_date')
            ->orderBy('d.delivery_number');

        $this->applyScope($query, $filter, supplierColumn: 'd.supplier_id', plantColumn: 'd.plant_id', materialColumn: 'di.material_id');

        return $query->lazy();
    }

    /**
     * One row per purchase order line, with what has arrived against it.
     *
     * Dated by the promise rather than by arrival, because an order line that
     * never arrived still belongs in the period it was due.
     *
     * @return LazyCollection<int, object>
     */
    public function orderLines(DashboardFilter $filter): LazyCollection
    {
        $query = DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->join('plants as p', 'p.id', '=', 'po.plant_id')
            ->join('materials as m', 'm.id', '=', 'poi.material_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'poi.uom_id')
            ->where('po.status', '!=', PurchaseOrderStatus::CANCELLED->value)
            ->whereBetween('poi.schedule_delivery_date', [$filter->dateFrom, $filter->dateTo])
            ->select([
                'po.po_number',
                'po.po_date',
                'po.status as po_status',
                's.code as supplier_code',
                's.name as supplier_name',
                'p.name as plant_name',
                'poi.line_no',
                'm.code as material_code',
                'm.name as material_name',
                'u.code as uom_code',
                'poi.schedule_delivery_date',
                'poi.qty_ordered',
                'poi.qty_received',
                'poi.unit_price',
                'poi.amount',
                'poi.fulfillment_status',
                'poi.timeliness_status',
                'poi.overall_status',
                'poi.first_receipt_date',
                'poi.last_receipt_date',
            ])
            ->orderBy('poi.schedule_delivery_date')
            ->orderBy('po.po_number')
            ->orderBy('poi.line_no');

        $this->applyScope($query, $filter, supplierColumn: 'po.supplier_id', plantColumn: 'po.plant_id', materialColumn: 'poi.material_id');

        return $query->lazy();
    }

    /**
     * One row per problem, with its corrective-action progress folded in.
     *
     * The two counts are correlated subqueries rather than a join, because
     * joining the actions would multiply the problem row per action and quietly
     * inflate every count in the export.
     *
     * @return LazyCollection<int, object>
     */
    public function problems(DashboardFilter $filter): LazyCollection
    {
        $query = DB::table('delivery_problems as dp')
            ->join('suppliers as s', 's.id', '=', 'dp.supplier_id')
            ->join('problem_categories as pc', 'pc.id', '=', 'dp.problem_category_id')
            ->join('deliveries as d', 'd.id', '=', 'dp.delivery_id')
            ->leftJoin('materials as m', 'm.id', '=', 'dp.material_id')
            ->whereBetween('dp.problem_date', [$filter->dateFrom, $filter->dateTo])
            ->select([
                'dp.problem_number',
                'dp.problem_date',
                'd.delivery_number',
                's.code as supplier_code',
                's.name as supplier_name',
                'm.code as material_code',
                'm.name as material_name',
                'pc.name as category_name',
                'dp.severity',
                'dp.status',
                'dp.pic',
                'dp.due_date',
                'dp.closed_at',
                'dp.root_cause',
            ])
            ->selectSub(
                DB::table('corrective_actions')
                    ->selectRaw('count(*)')
                    ->whereColumn('corrective_actions.delivery_problem_id', 'dp.id'),
                'action_count',
            )
            ->selectSub(
                DB::table('corrective_actions')
                    ->selectRaw('count(*)')
                    ->whereColumn('corrective_actions.delivery_problem_id', 'dp.id')
                    ->where('status', 'DONE'),
                'action_done_count',
            )
            ->orderBy('dp.problem_date')
            ->orderBy('dp.problem_number');

        $this->applyScope($query, $filter, supplierColumn: 'dp.supplier_id', plantColumn: 'd.plant_id', materialColumn: 'dp.material_id');

        if ($filter->status !== null) {
            $query->where('dp.status', $filter->status);
        }

        return $query->lazy();
    }

    /**
     * How many open problems the period leaves behind, for the report footer.
     */
    public function openProblemCount(DashboardFilter $filter): int
    {
        return (int) DB::table('delivery_problems')
            ->whereBetween('problem_date', [$filter->dateFrom, $filter->dateTo])
            ->whereIn('status', [ProblemStatus::OPEN->value, ProblemStatus::IN_PROGRESS->value])
            ->when($filter->supplierId !== null, fn (Builder $q) => $q->where('supplier_id', $filter->supplierId))
            ->count();
    }

    /**
     * The optional narrowing every report shares.
     *
     * Applied to the joined columns rather than through a subquery, so the
     * filter costs nothing beyond the index it already uses.
     */
    private function applyScope(
        Builder $query,
        DashboardFilter $filter,
        string $supplierColumn,
        string $plantColumn,
        string $materialColumn,
    ): void {
        if ($filter->supplierId !== null) {
            $query->where($supplierColumn, $filter->supplierId);
        }

        if ($filter->plantId !== null) {
            $query->where($plantColumn, $filter->plantId);
        }

        if ($filter->materialId !== null) {
            $query->where($materialColumn, $filter->materialId);
        }

        if ($filter->materialCategoryId !== null) {
            $query->where('m.category_id', $filter->materialCategoryId);
        }
    }
}
