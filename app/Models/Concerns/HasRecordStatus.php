<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query helpers shared by every master-data table carrying an ACTIVE/INACTIVE flag.
 *
 * @method static Builder<static> active()
 */
trait HasRecordStatus
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === RecordStatus::ACTIVE;
    }
}
