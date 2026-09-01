<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use App\Models\Delivery;
use App\Models\Department;
use App\Models\KpiSetting;
use App\Models\MaterialCategory;
use App\Models\Plant;
use App\Models\ProblemCategory;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDeliverySeeder;
use Database\Seeders\DemoMaterialSeeder;
use Database\Seeders\DemoPlantSeeder;
use Database\Seeders\DemoProblemSeeder;
use Database\Seeders\DemoPurchaseOrderSeeder;
use Database\Seeders\DemoSupplierSeeder;
use Database\Seeders\ProductionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Seeding a real deployment.
 *
 * The demo set plants ~1250 invented deliveries and seven accounts sharing one
 * password, and the README hands developers `migrate:fresh --seed`, which drops
 * every table first. Nothing stopped that command from being pointed at
 * production. These tests are the thing that stops it.
 */
final class ProductionSeedingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every seeder that plants demo data or demo credentials.
     *
     * @return array<string, array{class-string}>
     */
    public static function demoSeeders(): array
    {
        return [
            'the full demo run' => [DatabaseSeeder::class],
            'demo accounts' => [UserSeeder::class],
            'demo plants' => [DemoPlantSeeder::class],
            'demo suppliers' => [DemoSupplierSeeder::class],
            'demo materials' => [DemoMaterialSeeder::class],
            'demo purchase orders' => [DemoPurchaseOrderSeeder::class],
            'demo deliveries' => [DemoDeliverySeeder::class],
            'demo problems' => [DemoProblemSeeder::class],
        ];
    }

    /**
     * @param  class-string  $seeder
     */
    #[Test]
    #[DataProvider('demoSeeders')]
    public function a_demo_seeder_refuses_to_run_in_production(string $seeder): void
    {
        $this->inProduction(function () use ($seeder): void {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/refuses to run in production/');

            $this->runSeeder($seeder);
        });
    }

    #[Test]
    public function a_demo_seeder_still_runs_everywhere_else(): void
    {
        $this->seed(DemoPlantSeeder::class);

        $this->assertGreaterThan(0, Plant::query()->count());
    }

    #[Test]
    public function the_production_seeder_installs_the_reference_data_the_app_cannot_start_without(): void
    {
        $this->seed(ProductionSeeder::class);

        $this->assertGreaterThan(0, Role::query()->count(), 'roles are missing');
        $this->assertGreaterThan(0, Permission::query()->count(), 'permissions are missing');
        $this->assertDatabaseCount('departments', Department::query()->count());
        $this->assertGreaterThan(0, Uom::query()->count(), 'units of measure are missing');
        $this->assertGreaterThan(0, MaterialCategory::query()->count());
        $this->assertGreaterThan(0, ProblemCategory::query()->count());
        $this->assertGreaterThan(0, KpiSetting::query()->count(), 'the dashboard reads these');
    }

    #[Test]
    public function the_production_seeder_plants_no_accounts_and_no_invented_history(): void
    {
        $this->seed(ProductionSeeder::class);

        // The whole point: a deployment's own data is its own.
        $this->assertSame(0, User::query()->withTrashed()->count(), 'production must start with no accounts');
        $this->assertSame(0, Supplier::query()->withTrashed()->count());
        $this->assertSame(0, PurchaseOrder::query()->count());
        $this->assertSame(0, Delivery::query()->count());
    }

    #[Test]
    public function the_production_seeder_can_be_run_again_after_an_upgrade(): void
    {
        $this->seed(ProductionSeeder::class);
        $first = Role::query()->count();

        $this->seed(ProductionSeeder::class);

        $this->assertSame($first, Role::query()->count(), 'a second run duplicated roles');
        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function the_production_seeder_is_safe_in_production(): void
    {
        $this->inProduction(function (): void {
            $this->runSeeder(ProductionSeeder::class);
        });

        $this->assertGreaterThan(0, Role::query()->count());
    }

    #[Test]
    public function the_admin_command_creates_an_account_that_can_actually_sign_in(): void
    {
        $this->seed(ProductionSeeder::class);
        $password = 'KataSandiPanjang123';

        $this->artisan('mdp:create-admin', ['--name' => 'Operator Satu', '--email' => 'ops@example.test'])
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->assertSuccessful();

        $admin = User::query()->where('email', 'ops@example.test')->firstOrFail();
        $this->assertTrue($admin->hasRole('SUPER_ADMIN'));

        // Proving the hash is usable, not merely present: the 'hashed' cast and
        // an explicit Hash::make must not have combined into a double hash.
        $this->post(route('login'), ['email' => 'ops@example.test', 'password' => $password])
            ->assertRedirect();
        $this->assertAuthenticatedAs($admin);
    }

    #[Test]
    public function the_admin_command_normalises_the_address(): void
    {
        $this->seed(ProductionSeeder::class);
        $password = 'KataSandiPanjang123';

        $this->artisan('mdp:create-admin', ['--name' => 'Operator', '--email' => '  OPS@Example.Test '])
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'ops@example.test']);
    }

    #[Test]
    public function the_admin_command_rejects_a_short_password(): void
    {
        $this->seed(ProductionSeeder::class);

        $this->artisan('mdp:create-admin', ['--name' => 'Operator', '--email' => 'ops@example.test'])
            ->expectsQuestion('Password', 'pendek')
            ->expectsQuestion('Confirm password', 'pendek')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function the_admin_command_rejects_a_mistyped_confirmation(): void
    {
        $this->seed(ProductionSeeder::class);

        $this->artisan('mdp:create-admin', ['--name' => 'Operator', '--email' => 'ops@example.test'])
            ->expectsQuestion('Password', 'KataSandiPanjang123')
            ->expectsQuestion('Confirm password', 'KataSandiPanjang124')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function the_admin_command_refuses_an_address_a_retired_account_still_owns(): void
    {
        $this->seed(ProductionSeeder::class);
        $existing = User::factory()->create(['email' => 'ops@example.test']);
        $existing->delete();

        $this->assertSoftDeleted($existing);

        $this->artisan('mdp:create-admin', ['--name' => 'Operator', '--email' => 'ops@example.test'])
            ->expectsQuestion('Password', 'KataSandiPanjang123')
            ->expectsQuestion('Confirm password', 'KataSandiPanjang123')
            ->assertFailed();

        $this->assertSame(1, User::query()->withTrashed()->where('email', 'ops@example.test')->count());
    }

    #[Test]
    public function the_admin_command_refuses_a_role_that_does_not_exist(): void
    {
        $this->seed(ProductionSeeder::class);

        $this->artisan('mdp:create-admin', [
            '--name' => 'Operator', '--email' => 'ops@example.test', '--role' => 'DEWA',
        ])
            ->expectsQuestion('Password', 'KataSandiPanjang123')
            ->expectsQuestion('Confirm password', 'KataSandiPanjang123')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function the_force_flag_does_not_get_the_demo_data_past_the_guard(): void
    {
        // --force is the real danger: it is exactly what a deploy script passes
        // to skip Laravel's own production confirmation prompt.
        $this->inProduction(function (): void {
            $this->expectException(RuntimeException::class);

            $this->artisan('db:seed', [
                '--class' => DatabaseSeeder::class,
                '--force' => true,
            ])->run();
        });
    }

    /**
     * Invoke a seeder directly.
     *
     * Not $this->seed(): that goes through db:seed, which in production stops at
     * Laravel's own interactive confirmation before reaching the guard under
     * test. The dangerous path is the one that skips that prompt.
     *
     * @param  class-string  $seeder
     */
    private function runSeeder(string $seeder): void
    {
        $instance = $this->app->make($seeder);
        $instance->setContainer($this->app);
        $instance->run();
    }

    /**
     * Run a closure with the application believing it is in production.
     *
     * Restored in a finally: mutating the environment and leaving it mutated
     * makes the rest of the suite order-dependent, because CSRF enforcement is
     * gated on it.
     */
    private function inProduction(callable $callback): void
    {
        $original = $this->app->environment();

        try {
            $this->app->detectEnvironment(static fn (): string => 'production');
            $callback();
        } finally {
            $this->app->detectEnvironment(static fn (): string => $original);
        }
    }
}
