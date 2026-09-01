<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Enums\AuditAction;
use App\Models\Delivery;
use App\Models\DeliveryProblem;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 10's exit criterion: no N+1, anywhere.
 *
 * Asserting a fixed query count would be a test about today's implementation.
 * What actually matters is the shape of the cost: a screen must cost the same
 * whether it renders five rows or fifty. Each test here measures a screen
 * twice, with the population grown in between, and fails if the count moved.
 */
final class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = $this->userWithRole('SUPER_ADMIN');
    }

    /**
     * Queries issued while rendering one screen.
     */
    private function cost(string $uri): int
    {
        // Warm anything cached per process - permissions, KPI thresholds - so
        // the measurement is of the screen, not of a cold cache.
        $this->actingAs($this->admin)->get($uri);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->admin)->get($uri)->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * The cost before and after the population grows.
     *
     * @param  callable():void  $grow
     * @return array{0: int, 1: int}
     */
    private function costAcrossGrowth(string $uri, callable $grow): array
    {
        $before = $this->cost($uri);
        $grow();

        return [$before, $this->cost($uri)];
    }

    #[Test]
    public function the_dashboard_costs_the_same_however_much_data_it_covers(): void
    {
        [$before, $after] = $this->costAcrossGrowth(
            route('dashboard', [], false),
            function (): void {
                // Twenty more suppliers, each with problems of their own.
                Supplier::factory()->count(20)->create()->each(function (Supplier $supplier): void {
                    DeliveryProblem::factory()->count(2)->create([
                        'delivery_id' => Delivery::query()->value('id'),
                        'supplier_id' => $supplier->getKey(),
                    ]);
                });
            },
        );

        $this->assertSame($before, $after, 'the dashboard must aggregate, not iterate');
    }

    #[Test]
    public function the_supplier_list_does_not_query_per_supplier(): void
    {
        [$before, $after] = $this->costAcrossGrowth(
            route('suppliers.index', [], false),
            fn () => Supplier::factory()->count(20)->create(),
        );

        $this->assertSame($before, $after);
    }

    #[Test]
    public function the_material_list_does_not_query_per_material(): void
    {
        [$before, $after] = $this->costAcrossGrowth(
            route('materials.index', [], false),
            fn () => Material::factory()->count(20)->create(),
        );

        $this->assertSame($before, $after);
    }

    #[Test]
    public function the_purchase_order_list_does_not_query_per_order(): void
    {
        $uri = route('purchase-orders.index', [], false);
        $before = $this->cost($uri);

        PurchaseOrder::factory()->count(15)->create();

        $this->assertSame($before, $this->cost($uri));
    }

    #[Test]
    public function the_delivery_list_does_not_query_per_receipt(): void
    {
        $uri = route('deliveries.index', [], false);
        $before = $this->cost($uri);

        Delivery::factory()->count(15)->create();

        $this->assertSame($before, $this->cost($uri));
    }

    #[Test]
    public function the_problem_list_does_not_query_per_problem(): void
    {
        $uri = route('problems.index', [], false);
        $before = $this->cost($uri);

        DeliveryProblem::factory()->count(15)->create([
            'delivery_id' => Delivery::query()->value('id'),
        ]);

        $this->assertSame($before, $this->cost($uri));
    }

    #[Test]
    public function the_supplier_ranking_does_not_query_per_supplier(): void
    {
        [$before, $after] = $this->costAcrossGrowth(
            route('supplier-performance.index', [], false),
            fn () => Supplier::factory()->count(20)->create(),
        );

        $this->assertSame($before, $after);
    }

    #[Test]
    public function the_evaluation_register_does_not_query_per_scorecard(): void
    {
        $scorecards = function (int $count, int $month): void {
            Supplier::factory()->count($count)->create()->each(function (Supplier $supplier) use ($month): void {
                SupplierEvaluation::factory()->create([
                    'supplier_id' => $supplier->getKey(),
                    'period_year' => 2026,
                    'period_month' => $month,
                ]);
            });
        };

        /*
         * Measured from a populated baseline, not an empty one. An eager load
         * against no rows issues no query at all, so 0 -> N would show a
         * constant rise that looks like an N+1 and is not one.
         */
        $scorecards(3, 6);
        $uri = route('supplier-evaluations.index', [], false);
        $before = $this->cost($uri);

        $scorecards(15, 7);

        $this->assertSame($before, $this->cost($uri));
    }

    #[Test]
    public function the_critical_material_list_does_not_query_per_material(): void
    {
        [$before, $after] = $this->costAcrossGrowth(
            route('critical-materials.index', [], false),
            fn () => Material::factory()->count(20)->create(),
        );

        $this->assertSame($before, $after);
    }

    #[Test]
    public function the_user_list_does_not_query_per_user(): void
    {
        $uri = route('users.index', [], false);
        $before = $this->cost($uri);

        User::factory()->count(15)->create()->each(fn (User $user) => $user->assignRole('VIEWER'));

        $this->assertSame($before, $this->cost($uri));
    }

    #[Test]
    public function the_role_matrix_does_not_query_per_role(): void
    {
        [$before, $after] = $this->costAcrossGrowth(
            route('roles.index', [], false),
            fn () => Role::findOrCreate('EXTRA_ROLE', 'web'),
        );

        $this->assertSame($before, $after);
    }

    #[Test]
    public function the_audit_log_does_not_query_per_entry(): void
    {
        $audit = app(AuditLogService::class);

        /*
         * Written while signed in, so every entry carries a user_id. Recording
         * them unauthenticated leaves user_id null, the eager load never fires,
         * and the baseline measures a differently shaped page rather than a
         * smaller one.
         */
        $this->actingAs($this->admin);

        $entries = function (int $count) use ($audit): void {
            foreach (range(1, $count) as $i) {
                $audit->record(AuditAction::UPDATED, 'Supplier', $i, ['name' => 'a'], ['name' => 'b']);
            }
        };

        $entries(3);
        $uri = route('audit-logs.index', [], false);
        $before = $this->cost($uri);

        $entries(20);

        $this->assertSame($before, $this->cost($uri));
    }

    #[Test]
    public function the_report_preview_does_not_query_per_row(): void
    {
        $uri = route('reports.index', [], false);
        $before = $this->cost($uri);

        Delivery::factory()->count(15)->create();

        $this->assertSame($before, $this->cost($uri));
    }
}
