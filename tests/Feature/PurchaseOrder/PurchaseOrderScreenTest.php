<?php

declare(strict_types=1);

namespace Tests\Feature\PurchaseOrder;

use App\Enums\PurchaseOrderStatus;
use App\Models\Material;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The purchase order screens and the permissions gating each transition.
 */
final class PurchaseOrderScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $manager;

    private Supplier $supplier;

    private Plant $plant;

    private Warehouse $warehouse;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();

        $this->buyer = $this->userWithRole('PURCHASING');
        $this->manager = $this->userWithRole('MANAGEMENT');
        $this->supplier = Supplier::factory()->create();
        $this->plant = Plant::factory()->create();
        $this->warehouse = Warehouse::factory()->forPlant($this->plant)->create();
        $this->material = Material::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'po_date' => '2026-08-01',
            'supplier_id' => $this->supplier->getKey(),
            'plant_id' => $this->plant->getKey(),
            'currency' => 'IDR',
            'items' => [[
                'material_id' => $this->material->getKey(),
                'warehouse_id' => $this->warehouse->getKey(),
                'uom_id' => $this->material->uom_id,
                'schedule_delivery_date' => '2026-08-20',
                'qty_ordered' => 100,
                'unit_price' => 5000,
            ]],
            ...$overrides,
        ];
    }

    #[Test]
    public function the_index_renders_for_a_permitted_user(): void
    {
        PurchaseOrder::factory()->count(3)->create();

        $this->actingAs($this->buyer)
            ->get(route('purchase-orders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PurchaseOrders/Index')
                ->has('records.data', 3)
                ->has('options.statuses')
            );
    }

    #[Test]
    public function a_user_without_the_permission_cannot_see_purchase_orders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('purchase-orders.index'))
            ->assertForbidden();
    }

    #[Test]
    public function a_buyer_can_create_a_purchase_order_through_the_form(): void
    {
        $this->actingAs($this->buyer)
            ->post(route('purchase-orders.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $order = PurchaseOrder::query()->latest('id')->firstOrFail();

        $this->assertSame(PurchaseOrderStatus::DRAFT, $order->status);
        $this->assertSame(500000.0, (float) $order->total_amount);
    }

    #[Test]
    public function an_order_without_lines_is_rejected(): void
    {
        $this->actingAs($this->buyer)
            ->post(route('purchase-orders.store'), $this->payload(['items' => []]))
            ->assertSessionHasErrors('items');
    }

    #[Test]
    public function a_schedule_before_the_order_date_is_rejected(): void
    {
        $payload = $this->payload();
        $payload['items'][0]['schedule_delivery_date'] = '2026-07-01';

        $this->actingAs($this->buyer)
            ->post(route('purchase-orders.store'), $payload)
            ->assertSessionHasErrors('items.0.schedule_delivery_date');
    }

    #[Test]
    public function a_warehouse_from_another_plant_is_rejected(): void
    {
        $elsewhere = Warehouse::factory()->forPlant(Plant::factory()->create())->create();
        $payload = $this->payload();
        $payload['items'][0]['warehouse_id'] = $elsewhere->getKey();

        $this->actingAs($this->buyer)
            ->post(route('purchase-orders.store'), $payload)
            ->assertSessionHasErrors('items.0.warehouse_id');
    }

    #[Test]
    public function a_zero_quantity_line_is_rejected(): void
    {
        $payload = $this->payload();
        $payload['items'][0]['qty_ordered'] = 0;

        $this->actingAs($this->buyer)
            ->post(route('purchase-orders.store'), $payload)
            ->assertSessionHasErrors('items.0.qty_ordered');
    }

    #[Test]
    public function a_line_id_from_another_order_is_rejected(): void
    {
        $mine = PurchaseOrder::factory()->create();
        $theirs = PurchaseOrderItem::factory()->create();

        $payload = $this->payload();
        $payload['items'][0]['id'] = $theirs->getKey();

        $this->actingAs($this->buyer)
            ->put(route('purchase-orders.update', $mine->ulid), $payload)
            ->assertSessionHasErrors('items.0.id');
    }

    #[Test]
    public function the_detail_page_reports_which_transitions_are_available(): void
    {
        $this->actingAs($this->buyer)->post(route('purchase-orders.store'), $this->payload());
        $order = PurchaseOrder::query()->latest('id')->firstOrFail();

        // A buyer may edit and submit their draft, but never approve it.
        $this->actingAs($this->buyer)
            ->get(route('purchase-orders.show', $order->ulid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PurchaseOrders/Show')
                ->where('can.update', true)
                ->where('can.submit', true)
                ->where('can.approve', false)
                ->has('record.items', 1)
            );
    }

    #[Test]
    public function a_manager_sees_approve_only_once_the_order_is_submitted(): void
    {
        $this->actingAs($this->buyer)->post(route('purchase-orders.store'), $this->payload());
        $order = PurchaseOrder::query()->latest('id')->firstOrFail();

        $this->actingAs($this->manager)
            ->get(route('purchase-orders.show', $order->ulid))
            ->assertInertia(fn ($page) => $page->where('can.approve', false));

        $this->actingAs($this->buyer)->post(route('purchase-orders.submit', $order->ulid));

        $this->actingAs($this->manager)
            ->get(route('purchase-orders.show', $order->ulid))
            ->assertInertia(fn ($page) => $page->where('can.approve', true));
    }

    #[Test]
    public function a_buyer_cannot_approve_even_by_posting_directly(): void
    {
        $this->actingAs($this->buyer)->post(route('purchase-orders.store'), $this->payload());
        $order = PurchaseOrder::query()->latest('id')->firstOrFail();
        $this->actingAs($this->buyer)->post(route('purchase-orders.submit', $order->ulid));

        $this->actingAs($this->buyer)
            ->post(route('purchase-orders.approve', $order->ulid))
            ->assertForbidden();

        $this->assertSame(PurchaseOrderStatus::SUBMITTED, $order->refresh()->status);
    }

    #[Test]
    public function an_approved_order_can_no_longer_be_edited_through_the_screen(): void
    {
        $this->actingAs($this->buyer)->post(route('purchase-orders.store'), $this->payload());
        $order = PurchaseOrder::query()->latest('id')->firstOrFail();
        $this->actingAs($this->buyer)->post(route('purchase-orders.submit', $order->ulid));
        $this->actingAs($this->manager)->post(route('purchase-orders.approve', $order->ulid));

        $this->actingAs($this->buyer)
            ->get(route('purchase-orders.edit', $order->ulid))
            ->assertForbidden();
    }

    #[Test]
    public function cancelling_requires_a_reason(): void
    {
        $this->actingAs($this->buyer)->post(route('purchase-orders.store'), $this->payload());
        $order = PurchaseOrder::query()->latest('id')->firstOrFail();

        $this->actingAs($this->buyer)
            ->post(route('purchase-orders.cancel', $order->ulid), [])
            ->assertSessionHasErrors('reason');

        $this->assertSame(PurchaseOrderStatus::DRAFT, $order->refresh()->status);
    }

    #[Test]
    public function there_is_no_route_to_delete_a_purchase_order(): void
    {
        $this->assertFalse(
            collect(app('router')->getRoutes())->contains(
                fn ($route): bool => $route->getName() === 'purchase-orders.destroy',
            ),
        );
    }
}
