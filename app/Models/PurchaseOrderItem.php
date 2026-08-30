<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OverallDeliveryStatus;
use App\Enums\QuantityStatus;
use App\Enums\TimelinessStatus;
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
    use HasFactory;

    protected $fillable = [
        'purchase_order_id', 'material_id', 'warehouse_id', 'uom_id', 'line_no',
        'schedule_delivery_date', 'qty_ordered', 'unit_price', 'amount',
        'qty_received', 'first_receipt_date', 'last_receipt_date',
        'fulfillment_status', 'timeliness_status', 'overall_status', 'remarks',
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
}
