<?php

declare(strict_types=1);

namespace Tests\Feature\PurchaseOrder;

use App\Enums\AuditAction;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierStatus;
use App\Events\PurchaseOrder\PurchaseOrderApproved;
use App\Events\PurchaseOrder\PurchaseOrderCancelled;
use App\Events\PurchaseOrder\PurchaseOrderSubmitted;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Material;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseOrder\PurchaseOrderService;
use App\Services\Setting\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The purchase order lifecycle from docs/03 section 9.
 *
 *   DRAFT -> SUBMITTED -> APPROVED -> PARTIAL -> COMPLETED
 *      \         \            \
 *       `-------- cancel ------'--> CANCELLED
 */
final class PurchaseOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderService $service;

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

        $this->service = app(PurchaseOrderService::class);
        $this->buyer = $this->userWithRole('PURCHASING');
        $this->manager = $this->userWithRole('MANAGEMENT');
        $this->supplier = Supplier::factory()->create();
        $this->plant = Plant::factory()->create();
        $this->warehouse = Warehouse::factory()->forPlant($this->plant)->create();
        $this->material = Material::factory()->create();
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $lines
     */
    private function newOrder(?array $lines = null, ?User $actor = null): PurchaseOrder
    {
        return $this->service->create(
            [
                'po_date' => '2026-08-01',
                'supplier_id' => $this->supplier->getKey(),
                'plant_id' => $this->plant->getKey(),
                'currency' => 'IDR',
            ],
            $lines ?? [$this->line()],
            $actor ?? $this->buyer,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function line(float $qty = 100, float $price = 5000): array
    {
        return [
            'material_id' => $this->material->getKey(),
            'warehouse_id' => $this->warehouse->getKey(),
            'uom_id' => $this->material->uom_id,
            'schedule_delivery_date' => '2026-08-20',
            'qty_ordered' => $qty,
            'unit_price' => $price,
        ];
    }

    // --- creation --------------------------------------------------------

    #[Test]
    public function a_new_order_starts_as_a_draft_with_a_generated_number(): void
    {
        $order = $this->newOrder();

        $this->assertSame(PurchaseOrderStatus::DRAFT, $order->status);
        $this->assertMatchesRegularExpression('/^PO-\d{6}-\d{4}$/', $order->po_number);
        $this->assertSame($this->buyer->getKey(), $order->created_by);
        $this->assertNull($order->approved_at);
    }

    #[Test]
    public function the_order_total_is_derived_from_its_lines(): void
    {
        $order = $this->newOrder([$this->line(100, 5000), $this->line(50, 2000)]);

        // 100 x 5000 + 50 x 2000 = 600,000
        $this->assertSame(600000.0, (float) $order->total_amount);
        $this->assertSame(600000.0, (float) $order->items()->sum('amount'));
    }

    #[Test]
    public function line_numbers_are_sequenced_from_one(): void
    {
        $order = $this->newOrder([$this->line(), $this->line(), $this->line()]);

        $this->assertSame([1, 2, 3], $order->items()->orderBy('line_no')->pluck('line_no')->all());
    }

    #[Test]
    public function an_order_cannot_be_raised_against_a_blacklisted_supplier(): void
    {
        $this->supplier->forceFill(['status' => SupplierStatus::BLACKLISTED])->save();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/tidak dapat menerima purchase order/');

        $this->newOrder();
    }

    // --- editing ---------------------------------------------------------

    #[Test]
    public function a_draft_may_be_edited_and_its_total_follows(): void
    {
        $order = $this->newOrder();

        $this->service->update($order, ['payment_term' => 'NET 45'], [$this->line(200, 5000)]);

        $this->assertSame('NET 45', $order->refresh()->payment_term);
        $this->assertSame(1000000.0, (float) $order->total_amount);
    }

    #[Test]
    public function an_approved_order_can_no_longer_be_edited(): void
    {
        $order = $this->newOrder();
        $this->service->submit($order, $this->buyer);
        $this->service->approve($order, $this->manager);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/tidak dapat diubah/');

        $this->service->update($order, [], [$this->line()]);
    }

    #[Test]
    public function a_line_that_has_receipts_cannot_be_removed(): void
    {
        $order = $this->newOrder();
        $item = $order->items()->firstOrFail();
        $item->forceFill(['qty_received' => 40])->save();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/sudah menerima/');

        $this->service->update($order, [], []);
    }

    #[Test]
    public function a_line_cannot_be_reduced_below_what_has_already_arrived(): void
    {
        $order = $this->newOrder();
        $item = $order->items()->firstOrFail();
        $item->forceFill(['qty_received' => 80])->save();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/lebih kecil dari quantity/');

        $this->service->update($order, [], [[...$this->line(50), 'id' => $item->getKey()]]);
    }

    #[Test]
    public function an_existing_line_is_updated_in_place_rather_than_replaced(): void
    {
        $order = $this->newOrder();
        $item = $order->items()->firstOrFail();

        $this->service->update($order, [], [[...$this->line(250, 5000), 'id' => $item->getKey()]]);

        $this->assertSame(1, $order->items()->count());
        $this->assertSame($item->getKey(), $order->items()->firstOrFail()->getKey());
        $this->assertSame(250.0, (float) $item->refresh()->qty_ordered);
    }

    // --- submit ----------------------------------------------------------

    #[Test]
    public function submitting_moves_a_draft_to_submitted(): void
    {
        Event::fake([PurchaseOrderSubmitted::class]);
        $order = $this->newOrder();

        $this->service->submit($order, $this->buyer);

        $this->assertSame(PurchaseOrderStatus::SUBMITTED, $order->refresh()->status);
        Event::assertDispatched(PurchaseOrderSubmitted::class);
    }

    #[Test]
    public function an_order_without_lines_cannot_be_submitted(): void
    {
        $order = $this->newOrder();
        $order->items()->delete();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/belum memiliki baris item/');

        $this->service->submit($order, $this->buyer);
    }

    #[Test]
    public function an_order_cannot_be_submitted_twice(): void
    {
        $order = $this->newOrder();
        $this->service->submit($order, $this->buyer);

        $this->expectException(BusinessRuleException::class);

        $this->service->submit($order, $this->buyer);
    }

    // --- approve ---------------------------------------------------------

    #[Test]
    public function approving_records_who_released_the_order_and_when(): void
    {
        Event::fake([PurchaseOrderApproved::class]);
        $order = $this->newOrder();
        $this->service->submit($order, $this->buyer);

        $this->service->approve($order, $this->manager);

        $order->refresh();
        $this->assertSame(PurchaseOrderStatus::APPROVED, $order->status);
        $this->assertSame($this->manager->getKey(), $order->approved_by);
        $this->assertNotNull($order->approved_at);
        $this->assertTrue($order->acceptsDeliveries());
        Event::assertDispatched(PurchaseOrderApproved::class);
    }

    #[Test]
    public function a_draft_cannot_be_approved_without_being_submitted(): void
    {
        $order = $this->newOrder();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/hanya dapat disetujui dari status SUBMITTED/');

        $this->service->approve($order, $this->manager);
    }

    #[Test]
    public function a_buyer_cannot_approve_their_own_order(): void
    {
        $order = $this->newOrder(actor: $this->buyer);
        $this->service->submit($order, $this->buyer);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/tidak dapat disetujui oleh pembuatnya/');

        $this->service->approve($order, $this->buyer);
    }

    #[Test]
    public function self_approval_is_allowed_once_the_control_is_switched_off(): void
    {
        app(SystemSettingService::class)
            ->set(SystemSettingService::PO_REQUIRE_SEPARATE_APPROVER, false);

        $order = $this->newOrder(actor: $this->buyer);
        $this->service->submit($order, $this->buyer);
        $this->service->approve($order, $this->buyer);

        $this->assertSame(PurchaseOrderStatus::APPROVED, $order->refresh()->status);
    }

    // --- cancel ----------------------------------------------------------

    #[Test]
    public function a_draft_can_be_cancelled_with_a_reason(): void
    {
        Event::fake([PurchaseOrderCancelled::class]);
        $order = $this->newOrder();

        $this->service->cancel($order, $this->buyer, 'Supplier tidak sanggup memenuhi schedule');

        $order->refresh();
        $this->assertSame(PurchaseOrderStatus::CANCELLED, $order->status);
        $this->assertStringContainsString('tidak sanggup', (string) $order->remarks);
        Event::assertDispatched(PurchaseOrderCancelled::class);
    }

    #[Test]
    public function a_partially_received_order_may_still_be_cancelled(): void
    {
        $order = $this->newOrder();
        $this->service->submit($order, $this->buyer);
        $this->service->approve($order, $this->manager);
        $order->forceFill(['status' => PurchaseOrderStatus::PARTIAL])->save();

        $this->service->cancel($order, $this->manager, 'Sisa pengiriman dihentikan');

        $this->assertSame(PurchaseOrderStatus::CANCELLED, $order->refresh()->status);
    }

    #[Test]
    public function a_completed_order_cannot_be_cancelled(): void
    {
        $order = $this->newOrder();
        $order->forceFill(['status' => PurchaseOrderStatus::COMPLETED])->save();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/tidak dapat dibatalkan/');

        $this->service->cancel($order, $this->manager, 'Terlambat');
    }

    #[Test]
    public function a_cancelled_order_cannot_be_cancelled_again(): void
    {
        $order = $this->newOrder();
        $this->service->cancel($order, $this->buyer, 'Salah input');

        $this->expectException(BusinessRuleException::class);

        $this->service->cancel($order, $this->buyer, 'Sekali lagi');
    }

    #[Test]
    public function a_purchase_order_is_never_deleted(): void
    {
        $order = $this->newOrder();

        $this->assertFalse($this->manager->can('delete', $order));
        $this->assertFalse($this->userWithRole('SUPER_ADMIN')->can('delete', $order));
    }

    // --- audit -----------------------------------------------------------

    #[Test]
    public function every_transition_is_written_to_the_audit_trail(): void
    {
        $order = $this->newOrder();
        $this->service->submit($order, $this->buyer);
        $this->service->approve($order, $this->manager);

        $actions = AuditLog::query()
            ->where('module', 'PurchaseOrder')
            ->where('record_id', $order->getKey())
            ->pluck('action')
            ->all();

        $this->assertContains(AuditAction::CREATED, $actions);
        $this->assertContains(AuditAction::SUBMITTED, $actions);
        $this->assertContains(AuditAction::APPROVED, $actions);
    }

    #[Test]
    public function the_audit_entry_explains_what_happened(): void
    {
        $order = $this->newOrder();
        $this->service->cancel($order, $this->buyer, 'Budget ditarik');

        $entry = AuditLog::query()
            ->where('module', 'PurchaseOrder')
            ->where('action', AuditAction::CANCELLED)
            ->firstOrFail();

        $this->assertStringContainsString('Budget ditarik', $entry->new_values['description']);
    }

    #[Test]
    public function reordering_lines_renumbers_them_without_colliding(): void
    {
        $order = $this->newOrder([$this->line(10), $this->line(20), $this->line(30)]);
        $items = $order->items()->orderBy('line_no')->get();

        // Submit them back in reverse order - line 3 becomes line 1, which
        // would collide with the unique (order, line_no) key if the renumber
        // were done naively.
        $this->service->update($order, [], [
            [...$this->line(30), 'id' => $items[2]->getKey()],
            [...$this->line(20), 'id' => $items[1]->getKey()],
            [...$this->line(10), 'id' => $items[0]->getKey()],
        ]);

        $reordered = $order->items()->orderBy('line_no')->get();

        $this->assertSame([1, 2, 3], $reordered->pluck('line_no')->all());
        $this->assertSame(
            [30.0, 20.0, 10.0],
            $reordered->pluck('qty_ordered')->map(fn ($q): float => (float) $q)->all(),
        );
    }
}
