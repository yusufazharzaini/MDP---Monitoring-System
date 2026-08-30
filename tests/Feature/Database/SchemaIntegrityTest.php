<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\CorrectiveAction;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryProblem;
use App\Models\Material;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The constraints the specification asks for in sections 34 and 36: unique keys,
 * foreign keys, soft deletes on master data, and no soft delete on transactions.
 */
final class SchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array{string}>
     */
    public static function tables(): array
    {
        return array_map(static fn (string $table): array => [$table], [
            'departments', 'plants', 'warehouses', 'suppliers', 'supplier_contacts',
            'material_categories', 'uoms', 'materials',
            'purchase_orders', 'purchase_order_items',
            'deliveries', 'delivery_items',
            'problem_categories', 'delivery_problems', 'problem_attachments', 'corrective_actions',
            'kpi_settings', 'supplier_evaluations', 'supplier_evaluation_items',
            'notifications', 'audit_logs', 'system_settings',
            'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions',
        ]);
    }

    #[Test]
    #[DataProvider('tables')]
    public function the_specified_table_exists(string $table): void
    {
        $this->assertTrue(Schema::hasTable($table), "Table {$table} is missing.");
    }

    /**
     * @return array<int, array{string, array<int, string>}>
     */
    public static function softDeletedTables(): array
    {
        return [
            ['suppliers', ['deleted_at']],
            ['materials', ['deleted_at']],
            ['plants', ['deleted_at']],
            ['warehouses', ['deleted_at']],
            ['problem_categories', ['deleted_at']],
            ['material_categories', ['deleted_at']],
            ['uoms', ['deleted_at']],
            ['departments', ['deleted_at']],
            ['users', ['deleted_at']],
        ];
    }

    #[Test]
    #[DataProvider('softDeletedTables')]
    public function master_data_keeps_its_history_via_soft_deletes(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn($table, $column), "{$table}.{$column} is missing.");
        }
    }

    #[Test]
    public function transactional_tables_are_never_soft_deleted(): void
    {
        $this->assertFalse(Schema::hasColumn('purchase_orders', 'deleted_at'));
        $this->assertFalse(Schema::hasColumn('deliveries', 'deleted_at'));
    }

    #[Test]
    public function purchase_order_numbers_are_unique(): void
    {
        $order = PurchaseOrder::factory()->create();

        $this->expectException(QueryException::class);

        PurchaseOrder::factory()->create(['po_number' => $order->po_number]);
    }

    #[Test]
    public function delivery_numbers_are_unique(): void
    {
        $delivery = Delivery::factory()->create();

        $this->expectException(QueryException::class);

        Delivery::factory()->create(['delivery_number' => $delivery->delivery_number]);
    }

    #[Test]
    public function supplier_codes_are_unique(): void
    {
        $supplier = Supplier::factory()->create();

        $this->expectException(QueryException::class);

        Supplier::factory()->create(['code' => $supplier->code]);
    }

    #[Test]
    public function a_warehouse_code_only_has_to_be_unique_within_its_plant(): void
    {
        $first = Plant::factory()->create();
        $second = Plant::factory()->create();

        Warehouse::factory()->forPlant($first)->create(['code' => 'WH-01']);
        $other = Warehouse::factory()->forPlant($second)->create(['code' => 'WH-01']);

        $this->assertTrue($other->exists);

        $this->expectException(QueryException::class);
        Warehouse::factory()->forPlant($first)->create(['code' => 'WH-01']);
    }

    #[Test]
    public function a_supplier_can_only_be_evaluated_once_per_period(): void
    {
        $supplier = Supplier::factory()->create();

        SupplierEvaluation::factory()->create([
            'supplier_id' => $supplier->getKey(),
            'period_year' => 2026,
            'period_month' => 8,
        ]);

        $this->expectException(QueryException::class);

        SupplierEvaluation::factory()->create([
            'supplier_id' => $supplier->getKey(),
            'period_year' => 2026,
            'period_month' => 8,
        ]);
    }

    #[Test]
    public function foreign_keys_reject_orphan_rows(): void
    {
        $this->expectException(QueryException::class);

        Material::factory()->create(['category_id' => 99_999]);
    }

    #[Test]
    public function a_material_referenced_by_an_order_cannot_be_hard_deleted(): void
    {
        $material = Material::factory()->create();
        PurchaseOrderItem::factory()->create(['material_id' => $material->getKey()]);

        $this->expectException(QueryException::class);

        $material->forceDelete();
    }

    #[Test]
    public function a_purchase_order_with_lines_cannot_be_deleted(): void
    {
        $item = PurchaseOrderItem::factory()->create();

        $this->expectException(QueryException::class);

        $item->purchaseOrder->delete();
    }

    #[Test]
    public function a_delivery_with_receipt_lines_cannot_be_deleted(): void
    {
        $item = DeliveryItem::factory()->create();

        $this->expectException(QueryException::class);

        $item->delivery->delete();
    }

    #[Test]
    public function a_delivery_that_raised_a_problem_cannot_be_deleted(): void
    {
        $problem = DeliveryProblem::factory()->create();

        $this->expectException(QueryException::class);

        $problem->delivery->delete();
    }

    #[Test]
    public function a_problem_with_a_corrective_action_cannot_be_deleted(): void
    {
        $action = CorrectiveAction::factory()->create();

        $this->expectException(QueryException::class);

        $action->problem->delete();
    }

    #[Test]
    public function a_supplier_with_a_signed_off_evaluation_cannot_be_force_deleted(): void
    {
        $evaluation = SupplierEvaluation::factory()->create();

        $this->expectException(QueryException::class);

        $evaluation->supplier->forceDelete();
    }

    #[Test]
    public function an_order_line_can_still_be_removed_from_a_draft_order(): void
    {
        $order = PurchaseOrder::factory()->draft()->create();
        $item = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->getKey()]);

        // RESTRICT protects the parent, never the child: editing a draft order
        // must still be able to drop one of its own lines.
        $item->delete();

        $this->assertDatabaseMissing(PurchaseOrderItem::class, ['id' => $item->getKey()]);
        $this->assertDatabaseHas(PurchaseOrder::class, ['id' => $order->getKey()]);
    }

    #[Test]
    public function soft_deleting_a_supplier_keeps_its_orders_reachable(): void
    {
        $supplier = Supplier::factory()->create();
        $order = PurchaseOrder::factory()->create(['supplier_id' => $supplier->getKey()]);

        $supplier->delete();

        $this->assertSoftDeleted($supplier);
        $this->assertDatabaseHas(PurchaseOrder::class, ['id' => $order->getKey()]);
        $this->assertNotNull($order->fresh()?->supplier()->withTrashed()->first());
    }
}
