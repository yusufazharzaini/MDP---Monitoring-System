<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryProblem;
use App\Models\PurchaseOrderItem;
use App\Models\SupplierEvaluation;
use App\Models\SupplierEvaluationItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The database-level business constraints.
 *
 * The unique keys are portable and asserted everywhere. The CHECK constraints
 * only exist on MySQL/PostgreSQL, so those assertions skip on SQLite rather
 * than pretending to have verified something.
 */
final class BusinessConstraintTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function one_receipt_cannot_record_the_same_order_line_twice(): void
    {
        $item = PurchaseOrderItem::factory()->create();
        $delivery = Delivery::factory()->create(['purchase_order_id' => $item->purchase_order_id]);

        DeliveryItem::factory()->fulfilling($item, 400)->create(['delivery_id' => $delivery->getKey()]);

        $this->expectException(QueryException::class);

        DeliveryItem::factory()->fulfilling($item, 600)->create(['delivery_id' => $delivery->getKey()]);
    }

    #[Test]
    public function the_same_order_line_may_still_be_received_across_separate_deliveries(): void
    {
        $item = PurchaseOrderItem::factory()->create();

        foreach ([400, 600] as $quantity) {
            $delivery = Delivery::factory()->create(['purchase_order_id' => $item->purchase_order_id]);
            DeliveryItem::factory()->fulfilling($item, (float) $quantity)->create([
                'delivery_id' => $delivery->getKey(),
            ]);
        }

        $this->assertSame(2, $item->deliveryItems()->count());
    }

    #[Test]
    public function a_criterion_cannot_be_scored_twice_in_one_evaluation(): void
    {
        $evaluation = SupplierEvaluation::factory()->create();

        SupplierEvaluationItem::factory()->create([
            'supplier_evaluation_id' => $evaluation->getKey(),
            'criteria_name' => 'Delivery',
        ]);

        $this->expectException(QueryException::class);

        SupplierEvaluationItem::factory()->create([
            'supplier_evaluation_id' => $evaluation->getKey(),
            'criteria_name' => 'Delivery',
        ]);
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function invalidValues(): array
    {
        return [
            'zero ordered quantity' => ['purchase_order_items', ['qty_ordered' => 0]],
            'negative ordered quantity' => ['purchase_order_items', ['qty_ordered' => -1]],
            'negative unit price' => ['purchase_order_items', ['unit_price' => -1]],
            'negative received quantity' => ['purchase_order_items', ['qty_received' => -5]],
        ];
    }

    #[Test]
    #[DataProvider('invalidValues')]
    public function the_database_rejects_impossible_quantities(string $table, array $attributes): void
    {
        $this->requiresCheckConstraints();

        $item = PurchaseOrderItem::factory()->create();

        $this->expectException(QueryException::class);

        DB::table($table)->where('id', $item->getKey())->update($attributes);
    }

    #[Test]
    public function the_database_rejects_a_thirteenth_month(): void
    {
        $this->requiresCheckConstraints();

        $evaluation = SupplierEvaluation::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('supplier_evaluations')
            ->where('id', $evaluation->getKey())
            ->update(['period_month' => 13]);
    }

    #[Test]
    public function the_database_rejects_a_due_date_before_the_problem_was_reported(): void
    {
        $this->requiresCheckConstraints();

        $problem = DeliveryProblem::factory()->create([
            'problem_date' => '2026-08-20',
            'due_date' => '2026-08-27',
        ]);

        $this->expectException(QueryException::class);

        DB::table('delivery_problems')
            ->where('id', $problem->getKey())
            ->update(['due_date' => '2026-08-10']);
    }

    private function requiresCheckConstraints(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            $this->markTestSkipped('CHECK constraints are only applied on MySQL/PostgreSQL.');
        }
    }
}
