<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\CorrectiveAction;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryProblem;
use App\Models\Material;
use App\Models\Plant;
use App\Models\ProblemAttachment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\SupplierEvaluation;
use App\Models\SupplierEvaluationItem;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Walks the relationship graph documented in docs/02-ERD.md.
 */
final class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_plant_owns_warehouses_orders_and_deliveries(): void
    {
        $plant = Plant::factory()->create();
        Warehouse::factory()->forPlant($plant)->count(2)->create();
        $order = PurchaseOrder::factory()->create(['plant_id' => $plant->getKey()]);
        Delivery::factory()->forPurchaseOrder($order)->create();

        $this->assertCount(2, $plant->warehouses);
        $this->assertCount(1, $plant->purchaseOrders);
        $this->assertCount(1, $plant->deliveries);
    }

    #[Test]
    public function a_supplier_owns_contacts_orders_deliveries_problems_and_evaluations(): void
    {
        $supplier = Supplier::factory()->create();
        SupplierContact::factory()->primary()->create(['supplier_id' => $supplier->getKey()]);
        SupplierContact::factory()->create(['supplier_id' => $supplier->getKey()]);
        $order = PurchaseOrder::factory()->create(['supplier_id' => $supplier->getKey()]);
        $delivery = Delivery::factory()->forPurchaseOrder($order)->create();
        DeliveryProblem::factory()->create([
            'supplier_id' => $supplier->getKey(),
            'delivery_id' => $delivery->getKey(),
        ]);
        SupplierEvaluation::factory()->create(['supplier_id' => $supplier->getKey()]);

        $this->assertCount(2, $supplier->contacts);
        $this->assertNotNull($supplier->primaryContact);
        $this->assertTrue($supplier->primaryContact->is_primary);
        $this->assertCount(1, $supplier->purchaseOrders);
        $this->assertCount(1, $supplier->deliveries);
        $this->assertCount(1, $supplier->problems);
        $this->assertCount(1, $supplier->evaluations);
    }

    #[Test]
    public function an_order_line_links_material_warehouse_and_uom_and_reaches_its_receipts(): void
    {
        $item = PurchaseOrderItem::factory()->create();
        DeliveryItem::factory()->fulfilling($item)->create();

        $this->assertInstanceOf(Material::class, $item->material);
        $this->assertInstanceOf(Warehouse::class, $item->warehouse);
        $this->assertNotNull($item->uom);
        $this->assertCount(1, $item->deliveryItems);
        $this->assertInstanceOf(PurchaseOrder::class, $item->purchaseOrder);
    }

    #[Test]
    public function a_problem_owns_its_attachments_and_corrective_actions(): void
    {
        $problem = DeliveryProblem::factory()->create();
        ProblemAttachment::factory()->count(2)->create(['delivery_problem_id' => $problem->getKey()]);
        CorrectiveAction::factory()->done()->create(['delivery_problem_id' => $problem->getKey()]);
        CorrectiveAction::factory()->create(['delivery_problem_id' => $problem->getKey()]);

        $this->assertCount(2, $problem->attachments);
        $this->assertCount(2, $problem->correctiveActions);
        $this->assertCount(1, $problem->correctiveActions()->outstanding()->get());
        $this->assertNotNull($problem->category);
        $this->assertNotNull($problem->delivery);
    }

    #[Test]
    public function an_evaluation_owns_its_weighted_criteria(): void
    {
        $evaluation = SupplierEvaluation::factory()->create();
        SupplierEvaluationItem::factory()->count(4)->create([
            'supplier_evaluation_id' => $evaluation->getKey(),
            'weight' => 25,
            'score' => 80,
        ]);

        $this->assertCount(4, $evaluation->items);
        $this->assertSame(20.0, $evaluation->items->first()?->weightedScore());
    }

    #[Test]
    public function outstanding_quantity_never_goes_negative_on_an_over_receipt(): void
    {
        $item = PurchaseOrderItem::factory()->create(['qty_ordered' => 1000, 'qty_received' => 1200]);

        $this->assertSame(0.0, $item->outstandingQuantity());
    }

    #[Test]
    public function models_expose_their_ulid_as_the_route_key(): void
    {
        $supplier = Supplier::factory()->create();

        $this->assertSame('ulid', $supplier->getRouteKeyName());
        $this->assertNotEmpty($supplier->ulid);
        $this->assertSame($supplier->ulid, $supplier->getRouteKey());
    }

    #[Test]
    public function the_active_scope_filters_out_inactive_master_data(): void
    {
        Supplier::factory()->count(3)->create();
        Supplier::factory()->inactive()->create();
        Supplier::factory()->blacklisted()->create();

        $this->assertSame(3, Supplier::query()->active()->count());
        $this->assertSame(5, Supplier::query()->count());
    }

    #[Test]
    public function the_search_scope_matches_across_declared_columns(): void
    {
        Supplier::factory()->create(['name' => 'Yusuf Plastik Nusantara', 'code' => 'SUP-AAA']);
        Supplier::factory()->create(['name' => 'Other Company', 'code' => 'SUP-BBB']);

        $this->assertSame(1, Supplier::query()->search('Yusuf')->count());
        $this->assertSame(1, Supplier::query()->search('SUP-BBB')->count());
        $this->assertSame(2, Supplier::query()->search('')->count());
    }
}
