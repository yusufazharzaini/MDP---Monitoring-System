<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Database\Seeders\KpiSettingSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /**
     * Backend tests must not depend on a compiled frontend bundle.
     *
     * The Inertia root view calls @vite(...), which throws when
     * public/build/manifest.json is absent - and that directory is gitignored,
     * so it is absent on every fresh checkout. CI's backend jobs do not build
     * assets (the frontend job does), so every test rendering a page returned
     * 500 and 90 of them failed, while the same suite passed locally purely
     * because a previous `npm run build` had left the manifest behind.
     *
     * withoutVite() swaps in a stub, so what these tests assert - the payload,
     * the policy, the query - is independent of whether anyone has run the
     * bundler.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Reference data every feature test needs: roles, permissions, KPI
     * thresholds and the master lookups.
     */
    protected function seedReferenceData(): void
    {
        $this->seed([
            RolesAndPermissionsSeeder::class,
            MasterDataSeeder::class,
            KpiSettingSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * A user holding exactly the given permissions - the cleanest way to assert
     * that a route is gated by the permission it claims.
     *
     * @param  array<int, string>  $permissions
     */
    protected function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user->fresh() ?? $user;
    }

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh() ?? $user;
    }
}
