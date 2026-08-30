<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupplierGrade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id', 'period_year', 'period_month', 'delivery_score',
        'quality_score', 'quantity_score', 'responsiveness_score',
        'total_score', 'grade', 'remarks', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'delivery_score' => 'float',
            'quality_score' => 'float',
            'quantity_score' => 'float',
            'responsiveness_score' => 'float',
            'total_score' => 'float',
            'grade' => SupplierGrade::class,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierEvaluationItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function periodLabel(): string
    {
        return sprintf('%04d-%02d', $this->period_year, $this->period_month);
    }
}
