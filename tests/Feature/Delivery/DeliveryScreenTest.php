<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Enums\DeliveryStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Delivery;
use App\Models\Material;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseOrder\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DeliveryScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $clerk;

    private PurchaseOrder $order;

    private PurchaseOrderItem $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        Carbon::setTestNow('2026-08-26 09:00:00');

        $this->clerk = $this->userWithRole('WAREHOUSE');

        $plant = Plant::factory()->create();
        $material = Material::factory()->create();

        $orders = app(PurchaseOrderService::class);
        $this->order = $orders->create(
            [
                'po_date' => '2026-08-01',
                'supplier_id' => Supplier::factory()->create()->getKey(),
                'plant_id' => $plant->getKey(),
                'currency' => 'IDR',
            ],
            [[
                'material_id' => $material->getKey(),
                'warehouse_id' => Warehouse::factory()->forPlant($plant)->create()->getKey(),
                'uom_id' => $material->uom_id,
                'schedule_delivery_date' => '2026-08-26',
                'qty_ordered' => 1000,
                'unit_price' => 5000,
            ]],
            $this->userWithRole('PURCHASING'),
        );

        $orders->submit($this->order, $this->userWithRole('PURCHASING'));
        $orders->approve($this->order, $this->userWithRole('MANAGEMENT'));

        $this->line = $this->order->items()->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'delivery_date' => '2026-08-26',
            'do_number' => 'DO-9001',
            'items' => [[
                'purchase_order_item_id' => $this->line->getKey(),
                'qty_received' => 1000,
                'condition' => 'GOOD',
            ]],
            ...$overrides,
        ];
    }

    #[Test]
    public function the_index_renders_for_a_permitted_user(): void
    {
        Delivery::factory()->count(2)->create();

        $this->actingAs($this->clerk)
            ->get(route('deliveries.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Deliveries/Index')
                ->has('records.data', 2)
                ->where('can.create', true)
            );
    }

    #[Test]
    public function a_user_without_the_permission_cannot_see_deliveries(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('deliveries.index'))
            ->assertForbidden();
    }

    #[Test]
    public function the_receiving_form_lists_the_orders_outstanding_lines(): void
    {
        $this->actingAs($this->clerk)
            ->get(route('deliveries.create', $this->order->ulid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Deliveries/Create')
                ->where('purchaseOrder.po_number', $this->order->po_number)
                ->has('purchaseOrder.lines', 1)
                // json_encode drops the zero fraction, so compare numerically.
                ->where('purchaseOrder.lines.0.outstanding', fn (mixed $v): bool => (float) $v === 1000.0)
                ->has('options.conditions')
            );
    }

    #[Test]
    public function a_draft_order_offers_no_receiving_form(): void
    {
        $draft = PurchaseOrder::factory()->draft()->create();

        $this->actingAs($this->clerk)
            ->get(route('deliveries.create', $draft->ulid))
            ->assertForbidden();
    }

    #[Test]
    public function receiving_through_the_screen_settles_the_order(): void
    {
        $this->actingAs($this->clerk)
            ->post(route('deliveries.store', $this->order->ulid), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(PurchaseOrderStatus::COMPLETED, $this->order->refresh()->status);
        $this->assertSame(1000.0, (float) $this->line->refresh()->qty_received);
    }

    #[Test]
    public function a_future_delivery_date_is_rejected(): void
    {
        $this->actingAs($this->clerk)
            ->post(route('deliveries.store', $this->order->ulid), $this->payload([
                'delivery_date' => '2026-09-30',
            ]))
            ->assertSessionHasErrors('delivery_date');
    }

    #[Test]
    public function a_receipt_without_lines_is_rejected(): void
    {
        $this->actingAs($this->clerk)
            ->post(route('deliveries.store', $this->order->ulid), $this->payload(['items' => []]))
            ->assertSessionHasErrors('items');
    }

    #[Test]
    public function the_detail_page_shows_the_derived_verdict(): void
    {
        $this->actingAs($this->clerk)->post(route('deliveries.store', $this->order->ulid), $this->payload());
        $delivery = Delivery::query()->latest('id')->firstOrFail();

        $this->actingAs($this->clerk)
            ->get(route('deliveries.show', $delivery->ulid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Deliveries/Show')
                ->where('record.items.0.overall_status', 'ON_TIME_FULL')
                ->where('record.status', DeliveryStatus::COMPLETED->value)
            );
    }

    #[Test]
    public function a_warehouse_clerk_may_correct_but_not_cancel_a_receipt(): void
    {
        $this->actingAs($this->clerk)->post(route('deliveries.store', $this->order->ulid), $this->payload());
        $delivery = Delivery::query()->latest('id')->firstOrFail();

        $this->actingAs($this->clerk)
            ->get(route('deliveries.show', $delivery->ulid))
            ->assertInertia(fn ($page) => $page
                ->where('can.update', true)
                // delivery.cancel belongs to logistics, not the receiving desk.
                ->where('can.cancel', false)
            );

        $this->actingAs($this->clerk)
            ->post(route('deliveries.cancel', $delivery->ulid), ['reason' => 'Salah input barang'])
            ->assertForbidden();
    }

    #[Test]
    public function logistics_can_cancel_and_must_give_a_reason(): void
    {
        $this->actingAs($this->clerk)->post(route('deliveries.store', $this->order->ulid), $this->payload());
        $delivery = Delivery::query()->latest('id')->firstOrFail();
        $logistics = $this->userWithRole('LOGISTIC');

        $this->actingAs($logistics)
            ->post(route('deliveries.cancel', $delivery->ulid), [])
            ->assertSessionHasErrors('reason');

        $this->actingAs($logistics)
            ->post(route('deliveries.cancel', $delivery->ulid), ['reason' => 'Barang dikembalikan'])
            ->assertSessionHas('success');

        $this->assertSame(DeliveryStatus::CANCELLED, $delivery->refresh()->status);
        $this->assertSame(PurchaseOrderStatus::APPROVED, $this->order->refresh()->status);
    }

    #[Test]
    public function the_correction_form_folds_this_receipts_own_quantity_back_into_outstanding(): void
    {
        $this->actingAs($this->clerk)->post(route('deliveries.store', $this->order->ulid), $this->payload([
            'items' => [[
                'purchase_order_item_id' => $this->line->getKey(),
                'qty_received' => 400,
                'condition' => 'GOOD',
            ]],
        ]));
        $delivery = Delivery::query()->latest('id')->firstOrFail();

        $this->actingAs($this->clerk)
            ->get(route('deliveries.edit', $delivery->ulid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Deliveries/Edit')
                // 600 outstanding plus the 400 this receipt booked: correcting
                // it upward must not look like an over-delivery.
                ->where('purchaseOrder.lines.0.outstanding', fn (mixed $v): bool => (float) $v === 1000.0)
                ->where('purchaseOrder.lines.0.booked_here', fn (mixed $v): bool => (float) $v === 400.0)
            );
    }

    #[Test]
    public function a_cancelled_receipt_cannot_be_edited_through_the_screen(): void
    {
        $this->actingAs($this->clerk)->post(route('deliveries.store', $this->order->ulid), $this->payload());
        $delivery = Delivery::query()->latest('id')->firstOrFail();

        $this->actingAs($this->userWithRole('LOGISTIC'))
            ->post(route('deliveries.cancel', $delivery->ulid), ['reason' => 'Dibatalkan supplier']);

        $this->actingAs($this->clerk)
            ->get(route('deliveries.edit', $delivery->ulid))
            ->assertForbidden();
    }

    #[Test]
    public function there_is_no_route_to_delete_a_delivery(): void
    {
        $this->assertFalse(
            collect(app('router')->getRoutes())->contains(
                fn ($route): bool => $route->getName() === 'deliveries.destroy',
            ),
        );
    }
}
