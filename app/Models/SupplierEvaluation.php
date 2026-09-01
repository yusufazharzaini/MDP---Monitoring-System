<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EvaluationStatus;
use App\Enums\SupplierGrade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierEvaluation extends Model
{
    use HasFactory;

    /**
     * A reviewer may enter the four component scores and a remark.
     *
     * `total_score` and `grade` are absent: they are derived from the
     * components and the kpi_settings grade bands by SupplierEvaluationService,
     * so a scorecard can never claim a grade its scores do not support.
     * `created_by` comes from the authenticated user, and `status`,
     * `approved_by` and `approved_at` move together when it is signed off.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'supplier_id', 'period_year', 'period_month',
        'delivery_score', 'quality_score', 'quantity_score', 'responsiveness_score',
        'remarks',
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
            'status' => EvaluationStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForPeriod(Builder $query, int $year, int $month): Builder
    {
        return $query->where('period_year', $year)->where('period_month', $month);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', EvaluationStatus::DRAFT);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', EvaluationStatus::APPROVED);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfGrade(Builder $query, SupplierGrade $grade): Builder
    {
        return $query->where('grade', $grade);
    }

    /**
     * Newest period first.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLatestPeriodFirst(Builder $query): Builder
    {
        return $query->orderByDesc('period_year')->orderByDesc('period_month');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithListRelations(Builder $query): Builder
    {
        return $query
            ->with(['supplier:id,ulid,code,name,short_name', 'approver:id,name'])
            ->withCount('items');
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * A signed-off scorecard is frozen: it is no longer recomputed from
     * transactions, so a later correction cannot rewrite what was approved.
     */
    public function isApproved(): bool
    {
        return $this->status->isApproved();
    }

    public function periodLabel(): string
    {
        return sprintf('%04d-%02d', $this->period_year, $this->period_month);
    }
}
