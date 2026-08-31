<?php

declare(strict_types=1);

namespace App\Models;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\HasDashboardScopes;
use App\Models\Concerns\HasSearch;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Purchase orders are never soft deleted; a cancelled order keeps its history
 * and simply moves to PurchaseOrderStatus::CANCELLED (requirement 36).
 */
class PurchaseOrder extends Model
{
    use HasDashboardScopes;
    use HasFactory;
    use HasSearch;
    use HasUlid;

    /**
     * Only what a user may legitimately type on the PO form.
     *
     * Deliberately absent: `ulid` (set by HasUlid), `po_number` (allocated by
     * NumberGeneratorService), `status`, `total_amount`, `created_by`,
     * `approved_by` and `approved_at`. Those belong to PurchaseOrderService and
     * are written with forceFill(), so no request payload can approve its own
     * order or rewrite its value.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'po_date',
        'supplier_id',
        'plant_id',
        'currency',
        'payment_term',
        'remarks',
    ];

    /**
     * @var array<int, string>
     */
    protected array $searchable = ['po_number', 'remarks'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'po_date' => 'date',
            'approved_at' => 'datetime',
            'total_amount' => 'decimal:4',
            'status' => PurchaseOrderStatus::class,
        ];
    }

    public function dashboardDateColumn(): string
    {
        return 'purchase_orders.po_date';
    }

    /**
     * Orders that still participate in delivery performance.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', PurchaseOrderStatus::CANCELLED);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::PARTIAL]);
    }

    /**
     * The full dashboard filter. `status` is read as a purchase order status.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForDashboard(Builder $query, DashboardFilter $filter): Builder
    {
        $query->forPeriod($filter);

        $this->whereOptional($query, 'purchase_orders.plant_id', $filter->plantId);
        $this->whereOptional($query, 'purchase_orders.supplier_id', $filter->supplierId);
        $this->whereOptional($query, 'purchase_orders.status', $filter->status);

        if ($filter->materialId !== null || $filter->materialCategoryId !== null) {
            $query->whereHas('items', function (Builder $items) use ($filter): void {
                $this->whereOptional($items, 'purchase_order_items.material_id', $filter->materialId);

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
     * Index screens call this instead of assembling `with()` by hand, which is
     * how the N+1 stays fixed rather than fixed-once (requirement 33).
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
            ])
            ->withCount('items');
    }

    /**
     * Everything the detail screen renders.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithDetailRelations(Builder $query): Builder
    {
        return $query->with([
            'supplier',
            'plant',
            'creator:id,name',
            'approver:id,name',
            'items.material:id,ulid,code,name',
            'items.warehouse:id,ulid,code,name',
            'items.uom:id,code,name',
            'deliveries:id,ulid,purchase_order_id,delivery_number,delivery_date,status',
        ]);
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
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function acceptsDeliveries(): bool
    {
        return $this->status->acceptsDeliveries();
    }
}
