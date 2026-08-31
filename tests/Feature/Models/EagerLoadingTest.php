<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\CorrectiveAction;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryProblem;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Relationships must not cause N+1 queries."
 *
 * Two mechanisms, both tested here:
 *
 *  1. Model::shouldBeStrict() turns any lazy load outside production into an
 *     exception, so an N+1 fails loudly in development and CI instead of
 *     quietly in production.
 *  2. Each heavy model exposes a withListRelations() / withDetailRelations()
 *     scope, so screens declare their eager loads once instead of each caller
 *     remembering. These tests assert the query count stays flat as the row
 *     count grows - the definition of "no N+1".
 */
final class EagerLoadingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function lazy_loading_is_an_error_outside_production(): void
    {
        $this->assertTrue(
            Model::preventsLazyLoading(),
            'Strict mode is off, so an N+1 would pass unnoticed in CI.',
        );
    }

    #[Test]
    public function the_purchase_order_list_costs_the_same_for_one_row_as_for_twenty(): void
    {
        $this->assertQueryCountIsFlat(
            fn () => PurchaseOrder::query()->withListRelations()->get(),
            fn (int $n) => PurchaseOrder::factory()->count($n)->create(),
        );
    }

    #[Test]
    public function the_delivery_list_costs_the_same_for_one_row_as_for_twenty(): void
    {
        $this->assertQueryCountIsFlat(
            fn () => Delivery::query()->withListRelations()->get(),
            fn (int $n) => Delivery::factory()->count($n)->create(),
        );
    }

    #[Test]
    public function the_po_monitoring_table_costs_the_same_for_one_row_as_for_twenty(): void
    {
        $this->assertQueryCountIsFlat(
            fn () => PurchaseOrderItem::query()->withListRelations()->get(),
            fn (int $n) => PurchaseOrderItem::factory()->count($n)->create(),
        );
    }

    #[Test]
    public function the_delivery_item_list_costs_the_same_for_one_row_as_for_twenty(): void
    {
        $this->assertQueryCountIsFlat(
            fn () => DeliveryItem::query()->withListRelations()->get(),
            fn (int $n) => DeliveryItem::factory()->count($n)->create(),
        );
    }

    #[Test]
    public function the_problem_list_costs_the_same_for_one_row_as_for_twenty(): void
    {
        $this->assertQueryCountIsFlat(
            fn () => DeliveryProblem::query()->withListRelations()->get(),
            fn (int $n) => DeliveryProblem::factory()->count($n)->create(),
        );
    }

    #[Test]
    public function the_corrective_action_list_costs_the_same_for_one_row_as_for_twenty(): void
    {
        $this->assertQueryCountIsFlat(
            fn () => CorrectiveAction::query()->withListRelations()->get(),
            fn (int $n) => CorrectiveAction::factory()->count($n)->create(),
        );
    }

    #[Test]
    public function the_supplier_list_costs_the_same_for_one_row_as_for_twenty(): void
    {
        $this->assertQueryCountIsFlat(
            fn () => Supplier::query()->withListRelations()->get(),
            fn (int $n) => Supplier::factory()->count($n)->create(),
        );
    }

    #[Test]
    public function the_material_list_costs_the_same_for_one_row_as_for_twenty(): void
    {
        $this->assertQueryCountIsFlat(
            fn () => Material::query()->withListRelations()->get(),
            fn (int $n) => Material::factory()->count($n)->create(),
        );
    }

    #[Test]
    public function the_user_list_costs_the_same_for_one_row_as_for_twenty(): void
    {
        $this->seedReferenceData();

        $this->assertQueryCountIsFlat(
            fn () => User::query()->withListRelations()->get(),
            fn (int $n) => User::factory()->count($n)->create(),
        );
    }

    #[Test]
    public function a_purchase_order_detail_page_loads_its_whole_tree_without_lazy_loading(): void
    {
        $order = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->count(3)->create(['purchase_order_id' => $order->getKey()]);
        Delivery::factory()->count(2)->create(['purchase_order_id' => $order->getKey()]);

        $loaded = PurchaseOrder::query()->withDetailRelations()->findOrFail($order->getKey());

        // Every one of these would throw under strict mode if it were lazy.
        $this->assertNotNull($loaded->supplier->name);
        $this->assertCount(3, $loaded->items);
        $this->assertNotNull($loaded->items->first()?->material->code);
        $this->assertNotNull($loaded->items->first()?->warehouse->code);
        $this->assertCount(2, $loaded->deliveries);
    }

    #[Test]
    public function a_delivery_detail_page_loads_its_whole_tree_without_lazy_loading(): void
    {
        $item = PurchaseOrderItem::factory()->create();
        $delivery = Delivery::factory()->create(['purchase_order_id' => $item->purchase_order_id]);
        DeliveryItem::factory()->fulfilling($item)->create(['delivery_id' => $delivery->getKey()]);

        $loaded = Delivery::query()->withDetailRelations()->findOrFail($delivery->getKey());

        $this->assertNotNull($loaded->supplier->name);
        $this->assertNotNull($loaded->plant->code);
        $this->assertNotNull($loaded->purchaseOrder->po_number);
        $this->assertCount(1, $loaded->items);
        $this->assertNotNull($loaded->items->first()?->material->code);
        $this->assertNotNull($loaded->items->first()?->purchaseOrderItem->qty_ordered);
    }

    #[Test]
    public function reaching_an_unloaded_relation_in_a_loop_is_caught(): void
    {
        PurchaseOrder::factory()->count(3)->create();

        $this->expectException(LazyLoadingViolationException::class);

        foreach (PurchaseOrder::query()->get() as $order) {
            $order->supplier->name;
        }
    }

    /**
     * Run the query against one row, then against twenty, and assert the number
     * of database round trips did not change.
     *
     * @param  callable():mixed  $query
     * @param  callable(int):mixed  $seed
     */
    private function assertQueryCountIsFlat(callable $query, callable $seed): void
    {
        $seed(1);
        $baseline = $this->countQueries($query);

        $seed(19);
        $scaled = $this->countQueries($query);

        $this->assertSame(
            $baseline,
            $scaled,
            "Query count grew from {$baseline} to {$scaled} when the row count grew - that is an N+1.",
        );
    }

    /**
     * @param  callable():mixed  $callback
     */
    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
