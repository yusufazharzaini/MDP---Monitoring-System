<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryItemCondition;
use App\Enums\OverallDeliveryStatus;
use App\Enums\QuantityStatus;
use App\Enums\TimelinessStatus;
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

    protected $fillable = [
        'delivery_id', 'purchase_order_item_id', 'material_id', 'uom_id',
        'qty_received', 'condition', 'timeliness_status', 'quantity_status',
        'overall_status', 'days_late', 'remarks',
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
