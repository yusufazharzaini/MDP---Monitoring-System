<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
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
    use HasFactory;
    use HasSearch;
    use HasUlid;

    protected $fillable = [
        'ulid', 'po_number', 'po_date', 'supplier_id', 'plant_id', 'currency',
        'payment_term', 'status', 'total_amount', 'remarks', 'created_by',
        'approved_by', 'approved_at',
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
    public function scopeBetweenDates(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('po_date', [$from, $to]);
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
