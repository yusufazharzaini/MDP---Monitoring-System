<?php

declare(strict_types=1);

namespace App\Models;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\DeliveryStatus;
use App\Models\Concerns\HasDashboardScopes;
use App\Models\Concerns\HasSearch;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Deliveries are never soft deleted; cancellation is a status change so the
 * receiving history stays intact (requirement 36).
 */
class Delivery extends Model
{
    use HasDashboardScopes;
    use HasFactory;
    use HasSearch;
    use HasUlid;

    protected $table = 'deliveries';

    /**
     * Only what the receiving clerk fills in.
     *
     * Deliberately absent: `ulid`, `delivery_number` (allocated by
     * NumberGeneratorService), `status` (derived from the lines by
     * DeliveryStatusService) and `received_by` (taken from the authenticated
     * user, never from the payload).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'purchase_order_id',
        'supplier_id',
        'plant_id',
        'delivery_date',
        'do_number',
        'vehicle_number',
        'driver_name',
        'remarks',
    ];

    /**
     * @var array<int, string>
     */
    protected array $searchable = ['delivery_number', 'do_number', 'vehicle_number', 'driver_name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'status' => DeliveryStatus::class,
        ];
    }

    public function dashboardDateColumn(): string
    {
        return 'deliveries.delivery_date';
    }

    /**
     * The population every KPI aggregate runs over (assumption A5).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCountable(Builder $query): Builder
    {
        return $query->where('deliveries.status', '!=', DeliveryStatus::CANCELLED);
    }

    /**
     * The full dashboard filter. `status` is read as a delivery status.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForDashboard(Builder $query, DashboardFilter $filter): Builder
    {
        $query->countable()->forPeriod($filter);

        $this->whereOptional($query, 'deliveries.plant_id', $filter->plantId);
        $this->whereOptional($query, 'deliveries.supplier_id', $filter->supplierId);
        $this->whereOptional($query, 'deliveries.status', $filter->status);

        if ($filter->materialId !== null || $filter->materialCategoryId !== null) {
            $query->whereHas('items', function (Builder $items) use ($filter): void {
                $this->whereOptional($items, 'delivery_items.material_id', $filter->materialId);

                if ($filter->materialCategoryId !== null) {
                    $items->whereHas(
                        'material',
                        fn (Builder $m) => $m->where('category_id', $filter->materialCategoryId),
                    );
                }
            });
        }

        return $query;
    }

    /**
     * Everything an index row renders, eager loaded in one pass.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithListRelations(Builder $query): Builder
    {
        return $query
            ->with([
                'supplier:id,ulid,code,name,short_name',
                'plant:id,ulid,code,name',
                'purchaseOrder:id,ulid,po_number,po_date',
            ])
            ->withCount(['items', 'problems']);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithDetailRelations(Builder $query): Builder
    {
        return $query->with([
            'supplier',
            'plant',
            'purchaseOrder:id,ulid,po_number,po_date,status',
            'receiver:id,name',
            'items.material:id,ulid,code,name',
            'items.uom:id,code,name',
            'items.purchaseOrderItem:id,line_no,schedule_delivery_date,qty_ordered,qty_received',
            'problems:id,ulid,delivery_id,problem_number,severity,status,problem_date',
        ]);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function problems(): HasMany
    {
        return $this->hasMany(DeliveryProblem::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === DeliveryStatus::CANCELLED;
    }
}
