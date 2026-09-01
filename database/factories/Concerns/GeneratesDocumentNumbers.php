<?php

declare(strict_types=1);

namespace Database\Factories\Concerns;

use Carbon\CarbonInterface;

/**
 * Document numbers for factories, guaranteed not to collide with seeded data.
 *
 * `faker->unique()` is unique only within one faker instance, while the demo
 * seeders write their numbers through a different path entirely - so a factory
 * building a record alongside a seeded run would sooner or later mint a number
 * the seeder already used, and the unique index would reject it. Starting the
 * sequence far above anything a real month produces keeps the two apart.
 */
trait GeneratesDocumentNumbers
{
    /**
     * Well clear of the few hundred documents a seeded month contains, and
     * still inside the column.
     */
    private static int $documentSequence = 500_000;

    protected function documentNumber(string $prefix, ?CarbonInterface $date = null): string
    {
        $date ??= now();

        return sprintf('%s-%s-%d', $prefix, $date->format('Ym'), ++self::$documentSequence);
    }

    /**
     * A master-data code that cannot collide with a seeded one.
     *
     * The same problem in a different shape: MaterialFactory generated
     * `MAT-####` at random while the demo seeder owns MAT-0001 to MAT-0020, so
     * a factory built alongside seeded data eventually minted a code that
     * already existed. Other factories are safe only because their patterns
     * happen to differ from their seeders' - reach for this the moment that
     * stops being true.
     */
    protected function uniqueCode(string $prefix): string
    {
        return sprintf('%s-%d', $prefix, ++self::$documentSequence);
    }
}
