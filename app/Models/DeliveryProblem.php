<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProblemSeverity;
use App\Enums\ProblemStatus;
use App\Models\Concerns\HasSearch;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryProblem extends Model
{
    use HasFactory;
    use HasSearch;
    use HasUlid;

    protected $fillable = [
        'ulid', 'problem_number', 'delivery_id', 'supplier_id', 'material_id',
        'problem_category_id', 'problem_date', 'description', 'severity',
        'root_cause', 'status', 'pic', 'due_date', 'closed_at', 'created_by',
    ];

    /**
     * @var array<int, string>
     */
    protected array $searchable = ['problem_number', 'description', 'root_cause', 'pic'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'problem_date' => 'date',
            'due_date' => 'date',
            'closed_at' => 'datetime',
            'severity' => ProblemSeverity::class,
            'status' => ProblemStatus::class,
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [ProblemStatus::OPEN, ProblemStatus::IN_PROGRESS]);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString());
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProblemCategory::class, 'problem_category_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProblemAttachment::class);
    }

    public function correctiveActions(): HasMany
    {
        return $this->hasMany(CorrectiveAction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen()
            && $this->due_date !== null
            && $this->due_date->isBefore(now()->startOfDay());
    }
}
