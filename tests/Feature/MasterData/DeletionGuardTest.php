<?php

declare(strict_types=1);

namespace Tests\Feature\MasterData;

use App\Enums\AuditAction;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\MasterData\DepartmentService;
use App\Services\MasterData\MaterialCategoryService;
use App\Services\MasterData\MaterialService;
use App\Services\MasterData\PlantService;
use App\Services\MasterData\SupplierService;
use App\Services\MasterData\UomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Master data soft-deletes, so retiring a record never destroys history. What
 * these rules protect is the present: a record still needed by work in flight
 * cannot be taken away underneath it.
 */
final class DeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    #[Test]
    public function a_supplier_with_an_open_order_cannot_be_retired(): void
    {
        $supplier = Supplier::factory()->create();
        PurchaseOrder::factory()->approved()->create(['supplier_id' => $supplier->getKey()]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/purchase order yang masih berjalan/');

        app(SupplierService::class)->delete($supplier);
    }

    #[Test]
    public function a_supplier_whose_orders_are_all_settled_can_be_retired(): void
    {
        $supplier = Supplier::factory()->create();
        PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->getKey(),
            'status' => PurchaseOrderStatus::COMPLETED,
        ]);

        app(SupplierService::class)->delete($supplier);

        $this->assertSoftDeleted($supplier);
    }

    #[Test]
    public function retiring_a_supplier_keeps_its_orders_reachable(): void
    {
        $supplier = Supplier::factory()->create();
        $order = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->getKey(),
            'status' => PurchaseOrderStatus::COMPLETED,
        ]);

        app(SupplierService::class)->delete($supplier);

        $this->assertDatabaseHas(PurchaseOrder::class, ['id' => $order->getKey()]);
        $this->assertNotNull($order->fresh()?->supplier()->withTrashed()->first());
    }

    #[Test]
    public function a_plant_that_still_has_a_warehouse_cannot_be_retired(): void
    {
        $plant = Plant::factory()->create();
        Warehouse::factory()->forPlant($plant)->create();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/warehouse/');

        app(PlantService::class)->delete($plant);
    }

    #[Test]
    public function a_material_on_an_open_order_line_cannot_be_retired(): void
    {
        $item = PurchaseOrderItem::factory()->create();
        $item->purchaseOrder->forceFill(['status' => PurchaseOrderStatus::APPROVED])->save();

        $this->expectException(BusinessRuleException::class);

        app(MaterialService::class)->delete($item->material);
    }

    #[Test]
    public function a_category_still_holding_materials_cannot_be_retired(): void
    {
        $category = MaterialCategory::factory()->create();
        Material::factory()->create(['category_id' => $category->getKey()]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/material/');

        app(MaterialCategoryService::class)->delete($category);
    }

    #[Test]
    public function a_unit_of_measure_in_use_cannot_be_retired(): void
    {
        $uom = Uom::factory()->create();
        Material::factory()->create(['uom_id' => $uom->getKey()]);

        $this->expectException(BusinessRuleException::class);

        app(UomService::class)->delete($uom);
    }

    #[Test]
    public function a_department_with_staff_cannot_be_retired(): void
    {
        $department = Department::factory()->create();
        User::factory()->create(['department_id' => $department->getKey()]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/user/');

        app(DepartmentService::class)->delete($department);
    }

    #[Test]
    public function an_unused_record_retires_cleanly(): void
    {
        $category = MaterialCategory::factory()->create();

        app(MaterialCategoryService::class)->delete($category);

        $this->assertSoftDeleted($category);
    }

    #[Test]
    public function a_refused_delete_reaches_the_user_as_a_flash_message(): void
    {
        $supplier = Supplier::factory()->create();
        PurchaseOrder::factory()->approved()->create(['supplier_id' => $supplier->getKey()]);

        $this->actingAs($this->userWithRole('ADMIN'))
            ->from(route('suppliers.index'))
            ->delete(route('suppliers.destroy', $supplier->ulid))
            ->assertRedirect(route('suppliers.index'))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($supplier);
    }

    #[Test]
    public function creating_and_updating_a_record_is_written_to_the_audit_trail(): void
    {
        $admin = $this->userWithRole('ADMIN');

        $this->actingAs($admin)->post(route('plants.store'), [
            'code' => 'PL-AUD', 'name' => 'Plant Audit', 'status' => 'ACTIVE',
        ]);

        $plant = Plant::query()->where('code', 'PL-AUD')->firstOrFail();

        $this->assertDatabaseHas(AuditLog::class, [
            'action' => AuditAction::CREATED->value,
            'module' => 'Plant',
            'record_id' => $plant->getKey(),
            'user_id' => $admin->getKey(),
        ]);

        $this->actingAs($admin)->put(route('plants.update', $plant->ulid), [
            'code' => 'PL-AUD', 'name' => 'Plant Audit Baru', 'status' => 'ACTIVE',
        ]);

        $update = AuditLog::query()
            ->where('module', 'Plant')
            ->where('record_id', $plant->getKey())
            ->where('action', AuditAction::UPDATED)
            ->firstOrFail();

        // Only what actually changed is recorded.
        $this->assertSame(['name' => 'Plant Audit'], $update->old_values);
        $this->assertSame(['name' => 'Plant Audit Baru'], $update->new_values);
    }

    #[Test]
    public function saving_a_record_without_changing_anything_writes_no_audit_row(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $plant = Plant::factory()->create(['code' => 'PL-SAME', 'name' => 'Tetap']);

        $before = AuditLog::query()->count();

        $this->actingAs($admin)->put(route('plants.update', $plant->ulid), [
            'code' => 'PL-SAME', 'name' => 'Tetap', 'status' => $plant->status->value,
        ]);

        $this->assertSame($before, AuditLog::query()->count());
    }
}
