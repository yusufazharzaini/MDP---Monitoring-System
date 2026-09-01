<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\PurchaseOrder\SyncPurchaseOrderItems;
use App\Exceptions\BusinessRuleException;
use App\Models\Material;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Delivery\DeliveryService;
use App\Services\PurchaseOrder\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Line synchronisation.
 *
 * Editing an order is not "delete everything and re-insert" - a received line
 * carries history that must survive the edit - so lines are matched by id and
 * renumbered through two temporary parking ranges, because
 * (purchase_order_id, line_no) is unique and renumbering in place collides the
 * moment one line takes a number another still holds. That is the subtlest
 * code in the repository and it is reached only indirectly by the lifecycle
 * suites, so it gets its own tests here.
 */
final class SyncLineItemsTest extends TestCase
{
    use RefreshDatabase;

    private SyncPurchaseOrderItems $sync;

    private PurchaseOrder $order;

    private Plant $plant;

    private Warehouse $warehouse;

    /** @var array<int, Material> */
    private array $materials = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        Carbon::setTestNow('2026-08-26 09:00:00');

        $this->sync = app(SyncPurchaseOrderItems::class);
        $this->plant = Plant::factory()->create();
        $this->warehouse = Warehouse::factory()->forPlant($this->plant)->create();

        foreach (range(0, 3) as $index) {
            $this->materials[$index] = Material::factory()->create(['code' => 'MAT-'.$index]);
        }

        $this->order = app(PurchaseOrderService::class)->create(
            [
                'po_date' => '2026-08-01',
                'supplier_id' => Supplier::factory()->create()->getKey(),
                'plant_id' => $this->plant->getKey(),
                'currency' => 'IDR',
            ],
            [$this->line(0), $this->line(1), $this->line(2)],
            $this->userWithRole('PURCHASING'),
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function line(int $materialIndex, ?int $id = null, float $qty = 100): array
    {
        return array_filter([
            'id' => $id,
            'material_id' => $this->materials[$materialIndex]->getKey(),
            'warehouse_id' => $this->warehouse->getKey(),
            'uom_id' => $this->materials[$materialIndex]->uom_id,
            'schedule_delivery_date' => '2026-08-26',
            'qty_ordered' => $qty,
            'unit_price' => 1000,
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * @return array<int, string> line_no => material code
     */
    private function layout(): array
    {
        return $this->order->items()
            ->with('material:id,code')
            ->orderBy('line_no')
            ->get()
            ->mapWithKeys(fn (PurchaseOrderItem $item): array => [$item->line_no => $item->material?->code])
            ->all();
    }

    private function itemFor(int $materialIndex): PurchaseOrderItem
    {
        return $this->order->items()
            ->where('material_id', $this->materials[$materialIndex]->getKey())
            ->firstOrFail();
    }

    #[Test]
    public function lines_are_numbered_from_one_in_submission_order(): void
    {
        $this->assertSame([1 => 'MAT-0', 2 => 'MAT-1', 3 => 'MAT-2'], $this->layout());
    }

    #[Test]
    public function reversing_the_order_renumbers_without_colliding(): void
    {
        // The collision this guards against: line 3 moving to 1 while line 1
        // still holds 1, against a unique (order, line_no).
        ($this->sync)($this->order, [
            $this->line(2, $this->itemFor(2)->getKey()),
            $this->line(1, $this->itemFor(1)->getKey()),
            $this->line(0, $this->itemFor(0)->getKey()),
        ]);

        $this->assertSame([1 => 'MAT-2', 2 => 'MAT-1', 3 => 'MAT-0'], $this->layout());
    }

    #[Test]
    public function swapping_two_adjacent_lines_is_the_tightest_collision(): void
    {
        ($this->sync)($this->order, [
            $this->line(1, $this->itemFor(1)->getKey()),
            $this->line(0, $this->itemFor(0)->getKey()),
            $this->line(2, $this->itemFor(2)->getKey()),
        ]);

        $this->assertSame([1 => 'MAT-1', 2 => 'MAT-0', 3 => 'MAT-2'], $this->layout());
    }

    #[Test]
    public function a_new_line_inserted_in_the_middle_pushes_the_rest_down(): void
    {
        ($this->sync)($this->order, [
            $this->line(0, $this->itemFor(0)->getKey()),
            // No id: a brand new line, parked in the separate range so it
            // cannot land on a parked existing one.
            $this->line(3),
            $this->line(1, $this->itemFor(1)->getKey()),
            $this->line(2, $this->itemFor(2)->getKey()),
        ]);

        $this->assertSame(
            [1 => 'MAT-0', 2 => 'MAT-3', 3 => 'MAT-1', 4 => 'MAT-2'],
            $this->layout(),
        );
    }

    #[Test]
    public function removing_the_first_line_closes_the_gap(): void
    {
        ($this->sync)($this->order, [
            $this->line(1, $this->itemFor(1)->getKey()),
            $this->line(2, $this->itemFor(2)->getKey()),
        ]);

        $this->assertSame([1 => 'MAT-1', 2 => 'MAT-2'], $this->layout());
        $this->assertSame(2, $this->order->items()->count());
    }

    #[Test]
    public function reordering_inserting_and_deleting_in_one_save_all_settle(): void
    {
        ($this->sync)($this->order, [
            $this->line(2, $this->itemFor(2)->getKey()),
            $this->line(3),
            $this->line(0, $this->itemFor(0)->getKey()),
        ]);

        $this->assertSame([1 => 'MAT-2', 2 => 'MAT-3', 3 => 'MAT-0'], $this->layout());
        // MAT-1 was dropped in the same pass.
        $this->assertSame(3, $this->order->items()->count());
    }

    #[Test]
    public function an_existing_line_is_updated_in_place_and_keeps_its_id(): void
    {
        $before = $this->itemFor(1);

        ($this->sync)($this->order, [
            $this->line(0, $this->itemFor(0)->getKey()),
            $this->line(1, $before->getKey(), qty: 250),
            $this->line(2, $this->itemFor(2)->getKey()),
        ]);

        $after = $this->itemFor(1);

        // A replaced row would lose its receipts and its rollup.
        $this->assertSame($before->getKey(), $after->getKey());
        $this->assertSame(250.0, (float) $after->qty_ordered);
    }

    #[Test]
    public function the_amount_is_derived_rather_than_taken_from_the_form(): void
    {
        ($this->sync)($this->order, [
            // A client that posts its own total is a client that can post the
            // wrong one, so `amount` is ignored and recomputed.
            [...$this->line(0, $this->itemFor(0)->getKey(), qty: 7), 'amount' => 999_999],
        ]);

        $this->assertSame(7000.0, (float) $this->itemFor(0)->amount);
    }

    #[Test]
    public function a_line_that_has_received_goods_cannot_be_dropped(): void
    {
        $this->receiveAgainst($this->itemFor(1), 40);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('tidak dapat dihapus');

        ($this->sync)($this->order, [
            $this->line(0, $this->itemFor(0)->getKey()),
            $this->line(2, $this->itemFor(2)->getKey()),
        ]);
    }

    #[Test]
    public function a_refused_removal_leaves_the_layout_untouched(): void
    {
        $this->receiveAgainst($this->itemFor(1), 40);

        try {
            ($this->sync)($this->order, [$this->line(0, $this->itemFor(0)->getKey())]);
        } catch (BusinessRuleException) {
            // The guard runs before anything is parked, so nothing moved.
        }

        $this->assertSame([1 => 'MAT-0', 2 => 'MAT-1', 3 => 'MAT-2'], $this->layout());
    }

    #[Test]
    public function a_line_cannot_be_cut_below_what_already_arrived(): void
    {
        $this->receiveAgainst($this->itemFor(1), 60);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('lebih kecil dari');

        ($this->sync)($this->order, [
            $this->line(0, $this->itemFor(0)->getKey()),
            $this->line(1, $this->itemFor(1)->getKey(), qty: 50),
            $this->line(2, $this->itemFor(2)->getKey()),
        ]);
    }

    #[Test]
    public function a_line_may_be_cut_to_exactly_what_arrived(): void
    {
        $this->receiveAgainst($this->itemFor(1), 60);

        ($this->sync)($this->order, [
            $this->line(0, $this->itemFor(0)->getKey()),
            $this->line(1, $this->itemFor(1)->getKey(), qty: 60),
            $this->line(2, $this->itemFor(2)->getKey()),
        ]);

        $this->assertSame(60.0, (float) $this->itemFor(1)->qty_ordered);
    }

    #[Test]
    public function a_received_line_keeps_its_rollup_across_a_reorder(): void
    {
        $this->receiveAgainst($this->itemFor(1), 40);

        ($this->sync)($this->order, [
            $this->line(2, $this->itemFor(2)->getKey()),
            $this->line(1, $this->itemFor(1)->getKey()),
            $this->line(0, $this->itemFor(0)->getKey()),
        ]);

        $moved = $this->itemFor(1);

        $this->assertSame(2, $moved->line_no);
        $this->assertSame(40.0, (float) $moved->qty_received);
        $this->assertSame(1, $moved->deliveryItems()->count());
    }

    #[Test]
    public function every_line_number_stays_unique_and_contiguous_after_churn(): void
    {
        foreach ([[2, 0, 1], [1, 2, 0], [0, 1, 2]] as $order) {
            ($this->sync)($this->order, array_map(
                fn (int $index): array => $this->line($index, $this->itemFor($index)->getKey()),
                $order,
            ));
        }

        $numbers = $this->order->items()->orderBy('line_no')->pluck('line_no')->all();

        $this->assertSame([1, 2, 3], $numbers);
        $this->assertSame($numbers, array_values(array_unique($numbers)));
    }

    #[Test]
    public function the_parking_ranges_never_survive_a_sync(): void
    {
        ($this->sync)($this->order, [
            $this->line(2, $this->itemFor(2)->getKey()),
            $this->line(3),
        ]);

        // A line left parked at 10_00x or 20_00x would be a visible, wrong
        // number on the printed order.
        $this->assertSame(
            0,
            $this->order->items()->where('line_no', '>', 500)->count(),
        );
    }

    private function receiveAgainst(PurchaseOrderItem $item, float $qty): void
    {
        $orders = app(PurchaseOrderService::class);
        $orders->submit($this->order, $this->userWithRole('PURCHASING'));
        $orders->approve($this->order, $this->userWithRole('MANAGEMENT'));

        app(DeliveryService::class)->receive(
            $this->order->refresh(),
            ['delivery_date' => '2026-08-26'],
            [['purchase_order_item_id' => $item->getKey(), 'qty_received' => $qty, 'condition' => 'GOOD']],
            $this->userWithRole('WAREHOUSE'),
        );
    }
}
