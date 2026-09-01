<?php

declare(strict_types=1);

namespace Tests\Feature\Problem;

use App\Enums\CorrectiveActionStatus;
use App\Enums\ProblemSeverity;
use App\Enums\ProblemStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CorrectiveAction;
use App\Models\Delivery;
use App\Models\DeliveryProblem;
use App\Models\Material;
use App\Models\Plant;
use App\Models\ProblemCategory;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Delivery\DeliveryService;
use App\Services\Problem\CorrectiveActionService;
use App\Services\Problem\ProblemService;
use App\Services\PurchaseOrder\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The problem lifecycle: OPEN -> IN_PROGRESS -> CLOSED, or CANCELLED.
 *
 * The rule this module exists for is the closing one - a problem may only be
 * closed once at least one corrective action is DONE - so "resolved" always has
 * evidence behind it rather than being an opinion somebody typed.
 */
final class ProblemLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private ProblemService $problems;

    private CorrectiveActionService $actions;

    private User $reporter;

    private Delivery $delivery;

    private Material $material;

    private ProblemCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        Carbon::setTestNow('2026-08-26 09:00:00');

        $this->problems = app(ProblemService::class);
        $this->actions = app(CorrectiveActionService::class);
        $this->reporter = $this->userWithRole('WAREHOUSE');
        $this->category = ProblemCategory::query()->firstOrFail();

        $this->delivery = $this->receipt();
        $this->material = Material::query()->findOrFail($this->delivery->items()->value('material_id'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function report(array $overrides = []): DeliveryProblem
    {
        return $this->problems->report($this->delivery, [
            'problem_category_id' => $this->category->getKey(),
            'problem_date' => '2026-08-26',
            'description' => 'Material tiba dalam kondisi kemasan rusak dan basah.',
            'severity' => ProblemSeverity::HIGH->value,
            'pic' => 'Budi',
            ...$overrides,
        ], $this->reporter);
    }

    private function receipt(): Delivery
    {
        $supplier = Supplier::factory()->create();
        $plant = Plant::factory()->create();
        $warehouse = Warehouse::factory()->forPlant($plant)->create();
        $material = Material::factory()->create();

        $orders = app(PurchaseOrderService::class);
        $order = $orders->create(
            [
                'po_date' => '2026-08-01',
                'supplier_id' => $supplier->getKey(),
                'plant_id' => $plant->getKey(),
                'currency' => 'IDR',
            ],
            [[
                'material_id' => $material->getKey(),
                'warehouse_id' => $warehouse->getKey(),
                'uom_id' => $material->uom_id,
                'schedule_delivery_date' => '2026-08-26',
                'qty_ordered' => 1000,
                'unit_price' => 5000,
            ]],
            $this->userWithRole('PURCHASING'),
        );

        $orders->submit($order, $this->userWithRole('PURCHASING'));
        $orders->approve($order, $this->userWithRole('MANAGEMENT'));

        /** @var PurchaseOrder $order */
        $line = $order->items()->firstOrFail();

        return app(DeliveryService::class)->receive(
            $order,
            ['delivery_date' => '2026-08-26', 'do_number' => 'DO-1'],
            [['purchase_order_item_id' => $line->getKey(), 'qty_received' => 1000, 'condition' => 'GOOD']],
            $this->reporter,
        );
    }

    #[Test]
    public function reporting_allocates_a_number_and_opens_the_problem(): void
    {
        $problem = $this->report();

        $this->assertSame('PRB-202608-0001', $problem->problem_number);
        $this->assertSame(ProblemStatus::OPEN, $problem->status);
        $this->assertSame($this->reporter->getKey(), $problem->created_by);
        // The supplier comes from the receipt, never from the form.
        $this->assertSame($this->delivery->supplier_id, $problem->supplier_id);
        $this->assertNotNull($problem->ulid);
    }

    #[Test]
    public function the_due_date_defaults_to_the_severity_resolution_window(): void
    {
        $problem = $this->report(['severity' => ProblemSeverity::CRITICAL->value]);

        // CRITICAL allows three days.
        $this->assertSame('2026-08-29', $problem->due_date?->toDateString());

        $relaxed = $this->report(['severity' => ProblemSeverity::LOW->value]);

        $this->assertSame('2026-09-25', $relaxed->due_date?->toDateString());
    }

    #[Test]
    public function an_explicit_due_date_survives_the_default(): void
    {
        $problem = $this->report(['due_date' => '2026-09-10']);

        $this->assertSame('2026-09-10', $problem->due_date?->toDateString());
    }

    #[Test]
    public function a_due_date_before_the_problem_date_is_refused(): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->report(['due_date' => '2026-08-20']);
    }

    #[Test]
    public function a_problem_cannot_be_dated_in_the_future(): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->report(['problem_date' => '2026-08-27']);
    }

    #[Test]
    public function a_problem_cannot_predate_the_delivery_it_describes(): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->report(['problem_date' => '2026-08-25']);
    }

    #[Test]
    public function a_material_the_delivery_never_carried_is_refused(): void
    {
        $stranger = Material::factory()->create();

        $this->expectException(BusinessRuleException::class);

        $this->report(['material_id' => $stranger->getKey()]);
    }

    #[Test]
    public function a_material_the_delivery_carried_is_accepted(): void
    {
        $problem = $this->report(['material_id' => $this->material->getKey()]);

        $this->assertSame($this->material->getKey(), $problem->material_id);
    }

    #[Test]
    public function a_problem_cannot_be_raised_against_a_cancelled_receipt(): void
    {
        app(DeliveryService::class)->cancel($this->delivery, $this->reporter, 'Salah input');

        $this->expectException(BusinessRuleException::class);

        $this->report();
    }

    #[Test]
    public function closing_without_a_completed_corrective_action_is_refused(): void
    {
        $problem = $this->report();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('minimal satu corrective action harus berstatus Done');

        $this->problems->close($problem, $this->reporter);
    }

    #[Test]
    public function an_outstanding_corrective_action_is_not_enough_to_close(): void
    {
        $problem = $this->report();
        $this->actions->add($problem, [
            'action_date' => '2026-08-26',
            'description' => 'Klarifikasi ke supplier mengenai kondisi kemasan.',
        ], $this->reporter);

        $this->expectException(BusinessRuleException::class);

        $this->problems->close($problem->refresh(), $this->reporter);
    }

    #[Test]
    public function a_completed_corrective_action_makes_closing_possible(): void
    {
        $problem = $this->report();
        $action = $this->actions->add($problem, [
            'action_date' => '2026-08-26',
            'description' => 'Supplier mengganti seluruh material yang rusak.',
        ], $this->reporter);

        $this->actions->complete($action, $this->reporter);
        $closed = $this->problems->close($problem->refresh(), $this->reporter, 'Penggantian material selesai.');

        $this->assertSame(ProblemStatus::CLOSED, $closed->status);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame('Penggantian material selesai.', $closed->root_cause);
    }

    #[Test]
    public function recording_the_first_action_moves_the_problem_to_in_progress(): void
    {
        $problem = $this->report();
        $this->assertSame(ProblemStatus::OPEN, $problem->status);

        $this->actions->add($problem, [
            'action_date' => '2026-08-26',
            'description' => 'Investigasi bersama tim quality control.',
        ], $this->reporter);

        $this->assertSame(ProblemStatus::IN_PROGRESS, $problem->refresh()->status);
    }

    #[Test]
    public function completing_an_action_stamps_it_and_leaves_the_problem_open(): void
    {
        $problem = $this->report();
        $action = $this->actions->add($problem, [
            'action_date' => '2026-08-26',
            'description' => 'Supplier menyerahkan corrective action plan tertulis.',
        ], $this->reporter);

        $completed = $this->actions->complete($action, $this->reporter);

        $this->assertSame(CorrectiveActionStatus::DONE, $completed->status);
        $this->assertNotNull($completed->completed_at);
        // Completing an action never closes the problem: that is a separate
        // decision carrying the problem.close permission.
        $this->assertSame(ProblemStatus::IN_PROGRESS, $problem->refresh()->status);
    }

    #[Test]
    public function a_closed_problem_is_no_longer_editable(): void
    {
        $problem = $this->report();
        $action = $this->actions->add($problem, [
            'action_date' => '2026-08-26',
            'description' => 'Supplier mengganti seluruh material yang rusak.',
        ], $this->reporter);
        $this->actions->complete($action, $this->reporter);
        $this->problems->close($problem->refresh(), $this->reporter);

        $this->expectException(BusinessRuleException::class);

        $this->problems->update($problem->refresh(), ['pic' => 'Siti'], $this->reporter);
    }

    #[Test]
    public function a_closed_problem_cannot_be_closed_again(): void
    {
        $problem = $this->report();
        $action = $this->actions->add($problem, [
            'action_date' => '2026-08-26',
            'description' => 'Supplier mengganti seluruh material yang rusak.',
        ], $this->reporter);
        $this->actions->complete($action, $this->reporter);
        $this->problems->close($problem->refresh(), $this->reporter);

        $this->expectException(BusinessRuleException::class);

        $this->problems->close($problem->refresh(), $this->reporter);
    }

    #[Test]
    public function cancelling_withdraws_the_problem_without_deleting_it(): void
    {
        $problem = $this->report();

        $cancelled = $this->problems->cancel($problem, $this->reporter, 'Duplikat dari PRB lain.');

        $this->assertSame(ProblemStatus::CANCELLED, $cancelled->status);
        $this->assertDatabaseHas('delivery_problems', ['id' => $problem->getKey()]);
    }

    #[Test]
    public function a_cancelled_problem_cannot_be_closed(): void
    {
        $problem = $this->report();
        $this->problems->cancel($problem, $this->reporter, 'Duplikat.');

        $this->expectException(BusinessRuleException::class);

        $this->problems->close($problem->refresh(), $this->reporter);
    }

    #[Test]
    public function a_corrective_action_cannot_be_added_to_a_settled_problem(): void
    {
        $problem = $this->report();
        $this->problems->cancel($problem, $this->reporter, 'Duplikat.');

        $this->expectException(BusinessRuleException::class);

        $this->actions->add($problem->refresh(), [
            'action_date' => '2026-08-26',
            'description' => 'Tindakan menyusul setelah problem dibatalkan.',
        ], $this->reporter);
    }

    #[Test]
    public function a_corrective_action_cannot_predate_its_problem(): void
    {
        $problem = $this->report();

        $this->expectException(BusinessRuleException::class);

        $this->actions->add($problem, [
            'action_date' => '2026-08-25',
            'description' => 'Tindakan yang mendahului laporan problem.',
        ], $this->reporter);
    }

    #[Test]
    public function a_completed_corrective_action_cannot_be_removed(): void
    {
        $problem = $this->report();
        $action = $this->actions->add($problem, [
            'action_date' => '2026-08-26',
            'description' => 'Supplier mengganti seluruh material yang rusak.',
        ], $this->reporter);
        $this->actions->complete($action, $this->reporter);

        $this->expectException(BusinessRuleException::class);

        $this->actions->remove($action->refresh());
    }

    #[Test]
    public function an_outstanding_corrective_action_can_be_removed(): void
    {
        $problem = $this->report();
        $action = $this->actions->add($problem, [
            'action_date' => '2026-08-26',
            'description' => 'Dicatat karena salah pilih problem.',
        ], $this->reporter);

        $this->actions->remove($action);

        $this->assertDatabaseMissing('corrective_actions', ['id' => $action->getKey()]);
    }

    #[Test]
    public function the_closing_rule_reads_the_database_rather_than_a_loaded_relation(): void
    {
        $problem = $this->report();
        CorrectiveAction::factory()->count(3)->create([
            'delivery_problem_id' => $problem->getKey(),
            'status' => CorrectiveActionStatus::OPEN,
        ]);

        $this->assertFalse($this->problems->hasCompletedAction($problem));

        CorrectiveAction::factory()->create([
            'delivery_problem_id' => $problem->getKey(),
            'status' => CorrectiveActionStatus::DONE,
        ]);

        $this->assertTrue($this->problems->hasCompletedAction($problem));
    }

    #[Test]
    public function every_transition_is_written_to_the_audit_trail(): void
    {
        $problem = $this->report();
        $action = $this->actions->add($problem, [
            'action_date' => '2026-08-26',
            'description' => 'Supplier mengganti seluruh material yang rusak.',
        ], $this->reporter);
        $this->actions->complete($action, $this->reporter);
        $this->problems->close($problem->refresh(), $this->reporter);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'DeliveryProblem',
            'record_id' => $problem->getKey(),
            'action' => 'CREATED',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'DeliveryProblem',
            'record_id' => $problem->getKey(),
            'action' => 'CLOSED',
        ]);
    }
}
