<?php

declare(strict_types=1);

namespace App\Models;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\DeliveryItemCondition;
use App\Enums\DeliveryStatus;
use App\Enums\OverallDeliveryStatus;
use App\Enums\QuantityStatus;
use App\Enums\TimelinessStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The delivery line - the grain of every performance KPI (assumption A1).
 *
 * timeliness_status, quantity_status, overall_status and days_late are derived
 * columns owned exclusively by DeliveryStatusService.
 */
class DeliveryItem extends Model
{
    use HasFactory;

    /**
     * Only what the receiving clerk books.
     *
     * Deliberately absent: `timeliness_status`, `quantity_status`,
     * `overall_status` and `days_late`. They are the KPI itself; letting a
     * request set them would let a request set the dashboard.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'delivery_id',
        'purchase_order_item_id',
        'material_id',
        'uom_id',
        'qty_received',
        'condition',
        'remarks',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty_received' => 'decimal:4',
            'days_late' => 'integer',
            'condition' => DeliveryItemCondition::class,
            'timeliness_status' => TimelinessStatus::class,
            'quantity_status' => QuantityStatus::class,
            'overall_status' => OverallDeliveryStatus::class,
        ];
    }

    /**
     * Delivery lines that count towards performance.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCountable(Builder $query): Builder
    {
        return $query->whereHas(
            'delivery',
            fn (Builder $d) => $d->where('deliveries.status', '!=', DeliveryStatus::CANCELLED),
        );
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLate(Builder $query): Builder
    {
        return $query->where('timeliness_status', TimelinessStatus::LATE);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOnTime(Builder $query): Builder
    {
        return $query->where('timeliness_status', TimelinessStatus::ON_TIME);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeShort(Builder $query): Builder
    {
        return $query->where('quantity_status', QuantityStatus::SHORT);
    }

    /**
     * The full dashboard filter. `status` is read as an overall delivery status.
     *
     * This scope drives list screens. The dashboard aggregates in
     * DashboardRepository join `deliveries` explicitly instead, because an
     * EXISTS subquery is the wrong shape for a GROUP BY over a whole month.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForDashboard(Builder $query, DashboardFilter $filter): Builder
    {
        $query->whereHas('delivery', function (Builder $delivery) use ($filter): void {
            $delivery->countable()->forPeriod($filter);
            $this->whereOptional($delivery, 'deliveries.plant_id', $filter->plantId);
            $this->whereOptional($delivery, 'deliveries.supplier_id', $filter->supplierId);
        });

        $this->whereOptional($query, 'delivery_items.material_id', $filter->materialId);
        $this->whereOptional($query, 'delivery_items.overall_status', $filter->status);

        if ($filter->materialCategoryId !== null) {
            $query->whereHas(
                'material',
                fn (Builder $m) => $m->where('category_id', $filter->materialCategoryId),
            );
        }

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithListRelations(Builder $query): Builder
    {
        return $query->with([
            'delivery:id,ulid,delivery_number,delivery_date,supplier_id,plant_id,status',
            'delivery.supplier:id,ulid,code,name,short_name',
            'purchaseOrderItem:id,purchase_order_id,line_no,schedule_delivery_date,qty_ordered,qty_received',
            'purchaseOrderItem.purchaseOrder:id,ulid,po_number',
            'material:id,ulid,code,name,is_critical',
            'uom:id,code,name',
        ]);
    }

    /**
     * Apply an optional equality filter, skipping nulls.
     *
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    private function whereOptional(Builder $query, string $column, int|string|null $value): Builder
    {
        return $value === null ? $query : $query->where($column, $value);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    /**
     * Rejected goods are recorded but never count as fulfilled quantity.
     */
    public function effectiveQuantity(): float
    {
        return $this->condition->countsAsReceived() ? (float) $this->qty_received : 0.0;
    }
}
