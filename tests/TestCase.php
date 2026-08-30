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
