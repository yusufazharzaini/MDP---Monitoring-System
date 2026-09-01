<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Enums\DeliveryItemCondition;
use App\Enums\EvaluationStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Material;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Delivery\DeliveryService;
use App\Services\PurchaseOrder\PurchaseOrderService;
use App\Services\Supplier\SupplierEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sign-off, and what it protects.
 *
 * An evaluation is described in the ERD as a signed-off snapshot rather than a
 * cache. That only means something if approving it actually freezes it: last
 * quarter's approved grade must not move because a receipt was corrected this
 * morning. These tests are that guarantee.
 */
final class EvaluationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private SupplierEvaluationService $evaluations;

    private Supplier $supplier;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        Carbon::setTestNow('2026-08-26 09:00:00');

        $this->evaluations = app(SupplierEvaluationService::class);
        $this->manager = $this->userWithRole('MANAGEMENT');
        $this->supplier = Supplier::factory()->create(['code' => 'SUP-X']);

        // A scorecard needs something to score, so the supplier gets a real
        // July receipt behind it.
        $this->receiptFor($this->supplier);
    }

    /**
     * One punctual, complete receipt in July 2026.
     */
    private function receiptFor(Supplier $supplier): void
    {
        $plant = Plant::factory()->create();
        $material = Material::factory()->create();
        $buyer = $this->userWithRole('PURCHASING');

        $orders = app(PurchaseOrderService::class);
        $order = $orders->create(
            [
                'po_date' => '2026-07-01',
                'supplier_id' => $supplier->getKey(),
                'plant_id' => $plant->getKey(),
                'currency' => 'IDR',
            ],
            [[
                'material_id' => $material->getKey(),
                'warehouse_id' => Warehouse::factory()->forPlant($plant)->create()->getKey(),
                'uom_id' => $material->uom_id,
                'schedule_delivery_date' => '2026-07-20',
                'qty_ordered' => 100,
                'unit_price' => 1000,
            ]],
            $buyer,
        );

        $orders->submit($order, $buyer);
        $orders->approve($order, $this->manager);

        /** @var PurchaseOrder $order */
        $line = $order->items()->firstOrFail();

        app(DeliveryService::class)->receive(
            $order,
            ['delivery_date' => '2026-07-20'],
            [[
                'purchase_order_item_id' => $line->getKey(),
                'qty_received' => 100,
                'condition' => DeliveryItemCondition::GOOD->value,
            ]],
            $this->userWithRole('WAREHOUSE'),
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function draft(): SupplierEvaluation
    {
        return $this->evaluations->generateForPeriod($this->supplier, 2026, 7);
    }

    #[Test]
    public function a_generated_evaluation_starts_as_a_draft(): void
    {
        $evaluation = $this->draft();

        $this->assertSame(EvaluationStatus::DRAFT, $evaluation->status);
        $this->assertNull($evaluation->approved_at);
        $this->assertNull($evaluation->approved_by);
    }

    #[Test]
    public function a_draft_can_be_recomputed_as_often_as_needed(): void
    {
        $first = $this->draft();
        $second = $this->evaluations->generateForPeriod($this->supplier, 2026, 7);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertDatabaseCount('supplier_evaluations', 1);
    }

    #[Test]
    public function approving_freezes_the_scorecard_and_records_who_signed_it(): void
    {
        $approved = $this->evaluations->approve($this->draft(), $this->manager);

        $this->assertSame(EvaluationStatus::APPROVED, $approved->status);
        $this->assertSame($this->manager->getKey(), $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
    }

    #[Test]
    public function an_approved_scorecard_is_never_silently_recomputed(): void
    {
        $this->evaluations->approve($this->draft(), $this->manager);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('sudah disetujui dan tidak dapat dihitung ulang');

        $this->evaluations->generateForPeriod($this->supplier, 2026, 7);
    }

    #[Test]
    public function an_approved_scorecard_keeps_its_figures_when_the_data_beneath_it_changes(): void
    {
        $approved = $this->evaluations->approve($this->draft(), $this->manager);
        $scoreAtSignOff = $approved->total_score;
        $gradeAtSignOff = $approved->grade;

        // Whatever happens to the transactions afterwards, the row does not move.
        try {
            $this->evaluations->generateForPeriod($this->supplier, 2026, 7);
        } catch (BusinessRuleException) {
            // Expected - the point is what the stored row looks like afterwards.
        }

        $fresh = $approved->refresh();

        $this->assertSame($scoreAtSignOff, $fresh->total_score);
        $this->assertSame($gradeAtSignOff, $fresh->grade);
        $this->assertSame(EvaluationStatus::APPROVED, $fresh->status);
    }

    #[Test]
    public function approving_twice_is_refused(): void
    {
        $approved = $this->evaluations->approve($this->draft(), $this->manager);

        $this->expectException(BusinessRuleException::class);

        $this->evaluations->approve($approved->refresh(), $this->manager);
    }

    #[Test]
    public function reopening_returns_it_to_draft_and_clears_the_signature(): void
    {
        $approved = $this->evaluations->approve($this->draft(), $this->manager);

        $reopened = $this->evaluations->reopen($approved->refresh(), $this->manager, 'Koreksi penerimaan Juli.');

        $this->assertSame(EvaluationStatus::DRAFT, $reopened->status);
        $this->assertNull($reopened->approved_by);
        $this->assertNull($reopened->approved_at);
        $this->assertSame('Koreksi penerimaan Juli.', $reopened->remarks);
    }

    #[Test]
    public function a_reopened_scorecard_can_be_recomputed_again(): void
    {
        $approved = $this->evaluations->approve($this->draft(), $this->manager);
        $this->evaluations->reopen($approved->refresh(), $this->manager, 'Koreksi penerimaan Juli.');

        $regenerated = $this->evaluations->generateForPeriod($this->supplier, 2026, 7);

        $this->assertSame(EvaluationStatus::DRAFT, $regenerated->status);
        $this->assertDatabaseCount('supplier_evaluations', 1);
    }

    #[Test]
    public function a_draft_cannot_be_reopened(): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->evaluations->reopen($this->draft(), $this->manager);
    }

    #[Test]
    public function a_month_end_batch_skips_the_scorecards_already_signed_off(): void
    {
        $other = Supplier::factory()->create(['code' => 'SUP-Y']);
        $this->receiptFor($other);

        $this->evaluations->approve($this->draft(), $this->manager);
        $before = SupplierEvaluation::query()
            ->where('supplier_id', $this->supplier->getKey())
            ->forPeriod(2026, 7)
            ->firstOrFail()
            ->updated_at;

        // The batch must not abort on the approved one, and must not touch it.
        $this->evaluations->generateForAllSuppliers(2026, 7);

        $untouched = SupplierEvaluation::query()
            ->where('supplier_id', $this->supplier->getKey())
            ->forPeriod(2026, 7)
            ->firstOrFail();

        $this->assertSame(EvaluationStatus::APPROVED, $untouched->status);
        $this->assertEquals($before, $untouched->updated_at);

        // The rest of the run still landed.
        $this->assertDatabaseHas('supplier_evaluations', [
            'supplier_id' => $other->getKey(),
            'period_year' => 2026,
            'period_month' => 7,
            'status' => EvaluationStatus::DRAFT->value,
        ]);
    }

    #[Test]
    public function every_transition_is_written_to_the_audit_trail(): void
    {
        $approved = $this->evaluations->approve($this->draft(), $this->manager);
        $this->evaluations->reopen($approved->refresh(), $this->manager, 'Koreksi penerimaan Juli.');

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'SupplierEvaluation',
            'record_id' => $approved->getKey(),
            'action' => 'APPROVED',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'SupplierEvaluation',
            'record_id' => $approved->getKey(),
            'action' => 'UPDATED',
        ]);
    }

    #[Test]
    public function the_scores_still_come_from_the_transactions(): void
    {
        $evaluation = $this->draft();

        // One punctual, complete, problem-free receipt scores full marks on
        // every component - the figures are read from the month, not defaulted.
        $this->assertSame(100.0, $evaluation->delivery_score);
        $this->assertSame(100.0, $evaluation->quantity_score);
        $this->assertSame(100.0, $evaluation->total_score);
        $this->assertCount(4, $evaluation->items);
        $this->assertSame(
            100.0,
            round($evaluation->items->sum(fn ($item): float => (float) $item->weight), 2),
            'the criteria weights must sum to 100',
        );
    }

    #[Test]
    public function a_supplier_that_delivered_nothing_is_not_evaluated_at_all(): void
    {
        $dormant = Supplier::factory()->create(['code' => 'SUP-Z']);

        /*
         * Scoring a dormant supplier would publish 10/100 and grade POOR:
         * delivery, quantity and quality zero for want of data, responsiveness
         * full marks because no problem could be raised against a delivery
         * that never happened. That reads as terrible performance when it
         * means no activity.
         */
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('tidak memiliki penerimaan pada periode');

        $this->evaluations->generateForPeriod($dormant, 2026, 7);
    }

    #[Test]
    public function a_dormant_supplier_is_skipped_by_the_batch_rather_than_failing_it(): void
    {
        Supplier::factory()->create(['code' => 'SUP-DORMANT']);

        $generated = $this->evaluations->generateForAllSuppliers(2026, 7);

        $this->assertCount(1, $generated);
        $this->assertSame($this->supplier->getKey(), $generated[0]->supplier_id);
    }
}
