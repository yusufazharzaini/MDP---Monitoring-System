<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use RuntimeException;

/**
 * A demo seeder must never touch a production database.
 *
 * The demo set plants roughly 1250 invented deliveries, the suppliers and
 * purchase orders behind them, and seven accounts that share one password. Run
 * against a live deployment it would mix fiction into the record operators are
 * audited against - and `migrate:fresh --seed`, which the README hands to
 * developers, drops every table first.
 *
 * The guard is deliberately loud rather than a silent no-op: somebody typing
 * this against production has misunderstood something, and should be told.
 */
trait RefusesToRunInProduction
{
    protected function guardAgainstProduction(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        throw new RuntimeException(sprintf(
            '%s seeds demo data and refuses to run in production. Seed a real '
            .'deployment with `php artisan db:seed --class=ProductionSeeder`, '
            .'then create the first account with `php artisan mdp:create-admin`.',
            static::class,
        ));
    }
}
