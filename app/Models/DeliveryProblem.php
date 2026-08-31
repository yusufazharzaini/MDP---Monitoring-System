<?php

declare(strict_types=1);

namespace App\Models;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\CorrectiveActionStatus;
use App\Enums\ProblemSeverity;
use App\Enums\ProblemStatus;
use App\Models\Concerns\HasDashboardScopes;
use App\Models\Concerns\HasSearch;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryProblem extends Model
{
    use HasDashboardScopes;
    use HasFactory;
    use HasSearch;
    use HasUlid;

    /**
     * Only what the reporter fills in.
     *
     * Deliberately absent: `ulid`, `problem_number` (allocated by
     * NumberGeneratorService), `status` and `closed_at` (lifecycle, owned by
     * ProblemService, which enforces "a problem may only close once a
     * corrective action is done"), and `created_by`.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'delivery_id',
        'supplier_id',
        'material_id',
        'problem_category_id',
        'problem_date',
        'description',
        'severity',
        'root_cause',
        'pic',
        'due_date',
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

    public function dashboardDateColumn(): string
    {
        return 'delivery_problems.problem_date';
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

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', ProblemSeverity::CRITICAL);
    }

    /**
     * Open problems whose corrective actions are all still outstanding - the
     * ones nobody has actually started resolving.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutCompletedAction(Builder $query): Builder
    {
        return $query->whereDoesntHave(
            'correctiveActions',
            fn (Builder $action) => $action->where('status', CorrectiveActionStatus::DONE),
        );
    }

    /**
     * The full dashboard filter. `status` is read as a problem status.
     *
     * This is the population the Pareto chart groups by category.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForDashboard(Builder $query, DashboardFilter $filter): Builder
    {
        $query->forPeriod($filter);

        $this->whereOptional($query, 'delivery_problems.supplier_id', $filter->supplierId);
        $this->whereOptional($query, 'delivery_problems.material_id', $filter->materialId);
        $this->whereOptional($query, 'delivery_problems.status', $filter->status);

        if ($filter->plantId !== null) {
            $query->whereHas(
                'delivery',
                fn (Builder $d) => $d->where('deliveries.plant_id', $filter->plantId),
            );
        }

        if ($filter->materialCategoryId !== null) {
            $query->whereHas(
                'material',
                fn (Builder $m) => $m->where('category_id', $filter->materialCategoryId),
            );
        }

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithListRelations(Builder $query): Builder
    {
        return $query
            ->with([
                'supplier:id,ulid,code,name,short_name',
                'material:id,ulid,code,name',
                'category:id,code,name',
                'delivery:id,ulid,delivery_number,delivery_date',
            ])
            ->withCount(['attachments', 'correctiveActions']);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithDetailRelations(Builder $query): Builder
    {
        return $query->with([
            'supplier',
            'material',
            'category',
            'delivery.purchaseOrder:id,ulid,po_number',
            'creator:id,name',
            'attachments.uploader:id,name',
            'correctiveActions.actionBy:id,name',
        ]);
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

    /**
     * Days remaining until the due date; negative once overdue.
     */
    public function daysUntilDue(): ?int
    {
        return $this->due_date === null
            ? null
            : (int) now()->startOfDay()->diffInDays($this->due_date->copy()->startOfDay(), false);
    }
}
