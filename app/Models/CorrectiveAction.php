<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CorrectiveActionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrectiveAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_problem_id', 'action_date', 'action_by', 'description',
        'status', 'due_date', 'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'status' => CorrectiveActionStatus::class,
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->where('status', '!=', CorrectiveActionStatus::DONE);
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(DeliveryProblem::class, 'delivery_problem_id');
    }

    public function actionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_by');
    }

    public function isOverdue(): bool
    {
        return $this->status !== CorrectiveActionStatus::DONE
            && $this->due_date !== null
            && $this->due_date->isBefore(now()->startOfDay());
    }
}
