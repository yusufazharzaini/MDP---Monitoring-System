<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model a public ULID identifier used as its route key, so sequential
 * database ids are never exposed in URLs (requirement 35).
 */
trait HasUlid
{
    protected static function bootHasUlid(): void
    {
        static::creating(static function (self $model): void {
            if (blank($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
