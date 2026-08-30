<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierEvaluationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_evaluation_id', 'criteria_name', 'weight', 'score', 'remarks',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'score' => 'float',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForEvaluation(Builder $query, int $evaluationId): Builder
    {
        return $query->where('supplier_evaluation_id', $evaluationId);
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(SupplierEvaluation::class, 'supplier_evaluation_id');
    }

    public function weightedScore(): float
    {
        return round($this->score * ($this->weight / 100), 4);
    }
}
