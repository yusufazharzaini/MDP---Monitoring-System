<?php

declare(strict_types=1);

namespace App\Models;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\OverallDeliveryStatus;
use App\Enums\QuantityStatus;
use App\Enums\TimelinessStatus;
use App\Models\Concerns\HasDashboardScopes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A purchase order line, carrying the denormalised receipt rollup that the PO
 * monitoring table and critical-material queries aggregate over.
 *
 * The rollup columns are owned exclusively by DeliveryStatusService.
 */
class PurchaseOrderItem extends Model
{
    use HasDashboardScopes;
    use HasFactory;

    /**
     * Only what a user types on the PO line form.
     *
     * Deliberately absent: `amount` (computed from qty x price) and the whole
     * receipt rollup - `qty_received`, `first_receipt_date`, `last_receipt_date`,
     * `fulfillment_status`, `timeliness_status`, `overall_status`. Those are
     * derived, and DeliveryStatusService is their only writer.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'purchase_order_id',
        'material_id',
        'warehouse_id',
        'uom_id',
        'line_no',
        'schedule_delivery_date',
        'qty_ordered',
        'unit_price',
        'remarks',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schedule_delivery_date' => 'date',
            'first_receipt_date' => 'date',
            'last_receipt_date' => 'date',
            'qty_ordered' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'amount' => 'decimal:4',
            'qty_received' => 'decimal:4',
            'line_no' => 'integer',
            'fulfillment_status' => QuantityStatus::class,
            'timeliness_status' => TimelinessStatus::class,
            'overall_status' => OverallDeliveryStatus::class,
        ];
    }

    /**
     * An order line is reported on the date it was promised, not the date it
     * was raised - that is what makes a late line late.
     */
    public function dashboardDateColumn(): string
    {
        return 'purchase_order_items.schedule_delivery_date';
    }

    /**
     * Lines with quantity still outstanding.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('fulfillment_status', [QuantityStatus::PENDING, QuantityStatus::SHORT]);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeShort(Builder $query): Builder
    {
        return $query->where('fulfillment_status', QuantityStatus::SHORT);
    }

    /**
     * Lines promised before today that nobody has fully delivered.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->outstanding()
            ->whereDate('schedule_delivery_date', '<', now()->toDateString());
    }

    /**
     * The full dashboard filter. `status` is read as an overall delivery status.
     *
     * Excludes lines belonging to cancelled orders, so the PO monitoring table
     * and the KPI cards count the same population.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForDashboard(Builder $query, DashboardFilter $filter): Builder
    {
        $query->forPeriod($filter)
            ->whereHas('purchaseOrder', function (Builder $order) use ($filter): void {
                $order->notCancelled();
                $this->whereOptional($order, 'purchase_orders.plant_id', $filter->plantId);
                $this->whereOptional($order, 'purchase_orders.supplier_id', $filter->supplierId);
            });

        $this->whereOptional($query, 'purchase_order_items.material_id', $filter->materialId);
        $this->whereOptional($query, 'purchase_order_items.overall_status', $filter->status);

        if ($filter->materialCategoryId !== null) {
            $query->whereHas(
                'material',
                fn (Builder $m) => $m->where('category_id', $filter->materialCategoryId),
            );
        }

        return $query;
    }

    /**
     * Everything the PO monitoring table renders, in one pass.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithListRelations(Builder $query): Builder
    {
        return $query->with([
            'purchaseOrder:id,ulid,po_number,po_date,supplier_id,plant_id,status',
            'purchaseOrder.supplier:id,ulid,code,name,short_name',
            'purchaseOrder.plant:id,ulid,code,name',
            'material:id,ulid,code,name,is_critical',
            'warehouse:id,ulid,code,name',
            'uom:id,code,name',
        ]);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function deliveryItems(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    /**
     * Quantity still awaiting receipt; never negative.
     */
    public function outstandingQuantity(): float
    {
        return max(0.0, (float) $this->qty_ordered - (float) $this->qty_received);
    }

    /**
     * Percentage of the ordered quantity actually received.
     */
    public function fulfillmentPercentage(): float
    {
        $ordered = (float) $this->qty_ordered;

        return $ordered <= 0.0 ? 0.0 : round((float) $this->qty_received / $ordered * 100, 2);
    }
}
