<?php

declare(strict_types=1);

namespace Tests\Feature\MasterData;

use App\Models\Department;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Plant;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The CRUD surface shared by all eight master-data screens.
 *
 * The cases are data-driven because every entity is meant to behave the same
 * way - if one of them quietly diverges, the shared expectation catches it.
 */
final class MasterDataCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    /**
     * route, model, Inertia page directory, permission prefix.
     *
     * @return array<string, array{string, class-string<Model>, string, string}>
     */
    public static function entities(): array
    {
        return [
            'suppliers' => ['suppliers', Supplier::class, 'Suppliers', 'supplier'],
            'plants' => ['plants', Plant::class, 'Plants', 'plant'],
            'warehouses' => ['warehouses', Warehouse::class, 'Warehouses', 'warehouse'],
            'materials' => ['materials', Material::class, 'Materials', 'material'],
            'material categories' => ['material-categories', MaterialCategory::class, 'MaterialCategories', 'material'],
            'uoms' => ['uoms', Uom::class, 'Uoms', 'material'],
            'departments' => ['departments', Department::class, 'Departments', 'user'],
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[Test]
    #[DataProvider('entities')]
    public function the_index_lists_records_for_a_permitted_user(string $route, string $model, string $page, string $permission): void
    {
        unset($permission);

        // Reference data is already seeded, so the created rows are isolated by
        // a search token rather than by asserting on the grand total.
        $model::factory()->count(3)->create(['name' => fn (): string => 'Zeta Uji '.uniqid()]);

        $this->actingAs($this->userWithRole('ADMIN'))
            ->get(route("{$route}.index", ['search' => 'Zeta Uji']))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component("{$page}/Index")
                ->has('records.data', 3)
                ->has('can')
            );
    }

    #[Test]
    #[DataProvider('entities')]
    public function guests_are_redirected_to_login(string $route, string $model, string $page, string $permission): void
    {
        unset($model, $page, $permission);

        $this->get(route("{$route}.index"))->assertRedirect(route('login'));
    }

    #[Test]
    #[DataProvider('entities')]
    public function a_user_without_the_view_permission_is_refused(string $route, string $model, string $page, string $permission): void
    {
        unset($model, $page, $permission);

        $this->actingAs(User::factory()->create())
            ->get(route("{$route}.index"))
            ->assertForbidden();
    }

    #[Test]
    #[DataProvider('entities')]
    public function a_read_only_user_cannot_reach_the_create_screen(string $route, string $model, string $page, string $permission): void
    {
        unset($model, $page);

        $this->actingAs($this->userWithPermissions(["{$permission}.view"]))
            ->get(route("{$route}.create"))
            ->assertForbidden();
    }

    #[Test]
    #[DataProvider('entities')]
    public function the_index_reports_what_a_read_only_user_may_do(string $route, string $model, string $page, string $permission): void
    {
        unset($model, $page);

        $this->actingAs($this->userWithPermissions(["{$permission}.view"]))
            ->get(route("{$route}.index"))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('can.create', false)
                ->where('can.update', false)
                ->where('can.delete', false)
            );
    }

    #[Test]
    #[DataProvider('entities')]
    public function search_narrows_the_listing(string $route, string $model, string $page, string $permission): void
    {
        unset($permission);

        $model::factory()->create(['name' => 'Kandidat Unik Sekali']);
        $model::factory()->count(2)->create();

        $this->actingAs($this->userWithRole('ADMIN'))
            ->get(route("{$route}.index", ['search' => 'Kandidat Unik']))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component("{$page}/Index")
                ->has('records.data', 1)
            );
    }

    #[Test]
    public function a_supplier_can_be_created_updated_and_retired(): void
    {
        $admin = $this->userWithRole('ADMIN');

        $this->actingAs($admin)->post(route('suppliers.store'), [
            'code' => 'SUP-900',
            'name' => 'Supplier Baru',
            'country' => 'Indonesia',
            'lead_time_days' => 10,
            'supplier_type' => 'LOCAL',
            'status' => 'ACTIVE',
        ])->assertRedirect(route('suppliers.index'))->assertSessionHas('success');

        $supplier = Supplier::query()->where('code', 'SUP-900')->firstOrFail();

        $this->actingAs($admin)->put(route('suppliers.update', $supplier->ulid), [
            'code' => 'SUP-900',
            'name' => 'Supplier Diperbarui',
            'country' => 'Indonesia',
            'lead_time_days' => 14,
            'supplier_type' => 'IMPORT',
            'status' => 'ACTIVE',
        ])->assertRedirect(route('suppliers.index'));

        $supplier->refresh();
        $this->assertSame('Supplier Diperbarui', $supplier->name);
        $this->assertSame(14, $supplier->lead_time_days);

        $this->actingAs($admin)
            ->delete(route('suppliers.destroy', $supplier->ulid))
            ->assertRedirect(route('suppliers.index'));

        $this->assertSoftDeleted($supplier);
    }

    #[Test]
    public function a_duplicate_code_is_rejected(): void
    {
        $existing = Supplier::factory()->create(['code' => 'SUP-777']);

        $this->actingAs($this->userWithRole('ADMIN'))
            ->post(route('suppliers.store'), [
                'code' => $existing->code,
                'name' => 'Duplikat',
                'country' => 'Indonesia',
                'lead_time_days' => 5,
                'supplier_type' => 'LOCAL',
                'status' => 'ACTIVE',
            ])
            ->assertSessionHasErrors('code');
    }

    #[Test]
    public function a_warehouse_code_may_repeat_across_plants_but_not_within_one(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $first = Plant::factory()->create();
        $second = Plant::factory()->create();

        Warehouse::factory()->forPlant($first)->create(['code' => 'WH-01']);

        $this->actingAs($admin)->post(route('warehouses.store'), [
            'plant_id' => $second->getKey(), 'code' => 'WH-01', 'name' => 'Gudang Lain', 'status' => 'ACTIVE',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('warehouses.store'), [
            'plant_id' => $first->getKey(), 'code' => 'WH-01', 'name' => 'Bentrok', 'status' => 'ACTIVE',
        ])->assertSessionHasErrors('code');
    }

    #[Test]
    public function critical_stock_may_not_exceed_minimum_stock(): void
    {
        $category = MaterialCategory::factory()->create();
        $uom = Uom::factory()->create();

        $this->actingAs($this->userWithRole('ADMIN'))
            ->post(route('materials.store'), [
                'code' => 'MAT-9001',
                'name' => 'Material Uji',
                'category_id' => $category->getKey(),
                'uom_id' => $uom->getKey(),
                'minimum_stock' => 100,
                'critical_stock' => 500,
                'lead_time_days' => 7,
                'status' => 'ACTIVE',
            ])
            ->assertSessionHasErrors('critical_stock');
    }

    #[Test]
    public function editing_a_record_without_changing_its_code_is_allowed(): void
    {
        $plant = Plant::factory()->create(['code' => 'PL-99']);

        $this->actingAs($this->userWithRole('ADMIN'))
            ->put(route('plants.update', $plant->ulid), [
                'code' => 'PL-99',
                'name' => 'Nama Baru',
                'status' => 'ACTIVE',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('plants.index'));

        $this->assertSame('Nama Baru', $plant->refresh()->name);
    }
}
