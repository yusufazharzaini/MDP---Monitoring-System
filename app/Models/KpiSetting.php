<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Configurable KPI thresholds. Nothing in the frontend hard-codes a target -
 * every gauge and target line reads from here (requirement 11).
 */
class KpiSetting extends Model
{
    use HasFactory;

    public const SERVICE_RATE = 'SERVICE_RATE';

    public const ON_TIME_RATE = 'ON_TIME_RATE';

    public const QUANTITY_FULFILLMENT = 'QUANTITY_FULFILLMENT';

    protected $fillable = [
        'code', 'name', 'description', 'target_value',
        'warning_value', 'critical_value', 'unit', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_value' => 'float',
            'warning_value' => 'float',
            'critical_value' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Traffic-light band a measured value falls into.
     */
    public function severityFor(float $value): string
    {
        if ($this->critical_value !== null && $value < $this->critical_value) {
            return 'critical';
        }

        if ($this->warning_value !== null && $value < $this->warning_value) {
            return 'warning';
        }

        return $value >= $this->target_value ? 'success' : 'info';
    }
}
