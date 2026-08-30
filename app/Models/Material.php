<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\HasRecordStatus;
use App\Models\Concerns\HasSearch;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model
{
    use HasFactory;
    use HasRecordStatus;
    use HasSearch;
    use HasUlid;
    use SoftDeletes;

    protected $fillable = [
        'ulid', 'code', 'name', 'category_id', 'uom_id', 'specification',
        'minimum_stock', 'critical_stock', 'lead_time_days', 'is_critical', 'status',
    ];

    /**
     * @var array<int, string>
     */
    protected array $searchable = ['code', 'name', 'specification'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_stock' => 'decimal:4',
            'critical_stock' => 'decimal:4',
            'lead_time_days' => 'integer',
            'is_critical' => 'boolean',
            'status' => RecordStatus::class,
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('is_critical', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'category_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function deliveryItems(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function problems(): HasMany
    {
        return $this->hasMany(DeliveryProblem::class);
    }
}
