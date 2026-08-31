<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Applies a case-insensitive LIKE across the model's declared searchable
 * columns. Models opt in by declaring `protected array $searchable`.
 *
 * @method static Builder<static> search(?string $term)
 */
trait HasSearch
{
    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return property_exists($this, 'searchable') ? $this->searchable : [];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        $columns = $this->searchableColumns();

        if ($term === '' || $columns === []) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($columns, $term): void {
            foreach ($columns as $column) {
                $inner->orWhere($column, 'like', '%'.$term.'%');
            }
        });
    }
}
