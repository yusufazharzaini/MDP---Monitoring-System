<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryStatus;
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
    use HasFactory;
    use HasSearch;
    use HasUlid;

    protected $table = 'deliveries';

    protected $fillable = [
        'ulid', 'delivery_number', 'purchase_order_id', 'supplier_id', 'plant_id',
        'delivery_date', 'do_number', 'vehicle_number', 'driver_name',
        'received_by', 'status', 'remarks',
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

    /**
     * The population every KPI aggregate runs over (assumption A5).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCountable(Builder $query): Builder
    {
        return $query->where('status', '!=', DeliveryStatus::CANCELLED);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBetweenDates(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('delivery_date', [$from, $to]);
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
