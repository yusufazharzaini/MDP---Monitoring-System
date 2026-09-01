<?php

declare(strict_types=1);

namespace Tests\Feature\Problem;

use App\Enums\ProblemSeverity;
use App\Enums\ProblemStatus;
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
use App\Services\PurchaseOrder\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The problem screens, and the permissions that gate them.
 *
 * Reporting and closing are separate rights, so the tests here mostly ask who
 * can reach what: a warehouse clerk raises problems and works them, a
 * supervisor is the one who signs them off.
 */
final class ProblemScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $clerk;

    private Delivery $delivery;

    private ProblemCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        Carbon::setTestNow('2026-08-26 09:00:00');

        $this->clerk = $this->userWithRole('WAREHOUSE');
        $this->category = ProblemCategory::query()->firstOrFail();
        $this->delivery = $this->receipt();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function receipt(): Delivery
    {
        $plant = Plant::factory()->create();
        $material = Material::factory()->create();

        $orders = app(PurchaseOrderService::class);
        $order = $orders->create(
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

        $orders->submit($order, $this->userWithRole('PURCHASING'));
        $orders->approve($order, $this->userWithRole('MANAGEMENT'));

        /** @var PurchaseOrder $order */
        $line = $order->items()->firstOrFail();

        return app(DeliveryService::class)->receive(
            $order,
            ['delivery_date' => '2026-08-26'],
            [['purchase_order_item_id' => $line->getKey(), 'qty_received' => 1000, 'condition' => 'GOOD']],
            $this->clerk,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'problem_category_id' => $this->category->getKey(),
            'problem_date' => '2026-08-26',
            'description' => 'Material tiba dalam kondisi kemasan rusak dan basah.',
            'severity' => ProblemSeverity::HIGH->value,
            'pic' => 'Budi',
            ...$overrides,
        ];
    }

    #[Test]
    public function the_index_lists_problems_with_a_queue_summary(): void
    {
        DeliveryProblem::factory()->count(3)->create([
            'delivery_id' => $this->delivery->getKey(),
            'supplier_id' => $this->delivery->supplier_id,
            'status' => ProblemStatus::OPEN,
        ]);

        $this->actingAs($this->clerk)
            ->get(route('problems.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Problems/Index')
                ->has('records.data', 3)
                ->where('summary.open', 3)
                ->has('options.statuses')
                ->has('options.severities')
                ->has('options.categories'));
    }

    #[Test]
    public function the_queue_summary_counts_only_open_problems(): void
    {
        DeliveryProblem::factory()->count(2)->create([
            'delivery_id' => $this->delivery->getKey(),
            'supplier_id' => $this->delivery->supplier_id,
            'status' => ProblemStatus::OPEN,
            'severity' => ProblemSeverity::CRITICAL,
        ]);
        DeliveryProblem::factory()->create([
            'delivery_id' => $this->delivery->getKey(),
            'supplier_id' => $this->delivery->supplier_id,
            'status' => ProblemStatus::CLOSED,
            'severity' => ProblemSeverity::CRITICAL,
        ]);

        $this->actingAs($this->clerk)
            ->get(route('problems.index'))
            ->assertInertia(fn ($page) => $page
                ->where('summary.open', 2)
                ->where('summary.critical', 2));
    }

    #[Test]
    public function the_overdue_filter_narrows_to_problems_past_their_due_date(): void
    {
        DeliveryProblem::factory()->create([
            'delivery_id' => $this->delivery->getKey(),
            'supplier_id' => $this->delivery->supplier_id,
            'status' => ProblemStatus::OPEN,
            'problem_date' => '2026-08-01',
            'due_date' => '2026-08-05',
        ]);
        DeliveryProblem::factory()->create([
            'delivery_id' => $this->delivery->getKey(),
            'supplier_id' => $this->delivery->supplier_id,
            'status' => ProblemStatus::OPEN,
            'problem_date' => '2026-08-20',
            'due_date' => '2026-09-30',
        ]);

        $this->actingAs($this->clerk)
            ->get(route('problems.index', ['overdue' => 1]))
            ->assertInertia(fn ($page) => $page
                ->has('records.data', 1)
                ->where('records.data.0.is_overdue', true)
                ->where('summary.overdue', 1));
    }

    #[Test]
    public function the_reporting_form_offers_only_the_materials_this_receipt_carried(): void
    {
        $stranger = Material::factory()->create();

        $this->actingAs($this->clerk)
            ->get(route('problems.create', $this->delivery->ulid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Problems/Create')
                ->has('delivery.materials', 1)
                ->where('delivery.materials.0.value', $this->delivery->items()->value('material_id'))
                ->where('delivery.delivery_number', $this->delivery->delivery_number));

        $this->assertNotSame($stranger->getKey(), $this->delivery->items()->value('material_id'));
    }

    #[Test]
    public function reporting_through_the_screen_creates_the_problem(): void
    {
        $this->actingAs($this->clerk)
            ->post(route('problems.store', $this->delivery->ulid), $this->payload())
            ->assertRedirect();

        $problem = DeliveryProblem::query()->firstOrFail();

        $this->assertSame('PRB-202608-0001', $problem->problem_number);
        $this->assertSame($this->clerk->getKey(), $problem->created_by);
        $this->assertSame(ProblemStatus::OPEN, $problem->status);
    }

    #[Test]
    public function the_detail_screen_answers_the_closing_rule_for_the_page(): void
    {
        $problem = DeliveryProblem::factory()->create([
            'delivery_id' => $this->delivery->getKey(),
            'supplier_id' => $this->delivery->supplier_id,
            'status' => ProblemStatus::OPEN,
        ]);

        $this->actingAs($this->userWithRole('LOGISTIC'))
            ->get(route('problems.show', $problem->ulid))
            ->assertInertia(fn ($page) => $page
                ->component('Problems/Show')
                ->where('closable', false)
                ->where('can.close', true));

        CorrectiveAction::factory()->done()->create(['delivery_problem_id' => $problem->getKey()]);

        $this->actingAs($this->userWithRole('LOGISTIC'))
            ->get(route('problems.show', $problem->ulid))
            ->assertInertia(fn ($page) => $page->where('closable', true));
    }

    #[Test]
    public function a_reporter_without_the_close_permission_is_refused_the_close_route(): void
    {
        $problem = DeliveryProblem::factory()->create([
            'delivery_id' => $this->delivery->getKey(),
            'supplier_id' => $this->delivery->supplier_id,
            'status' => ProblemStatus::OPEN,
        ]);
        CorrectiveAction::factory()->done()->create(['delivery_problem_id' => $problem->getKey()]);

        // WAREHOUSE may report and work a problem but never sign it off.
        $this->actingAs($this->clerk)
            ->post(route('problems.close', $problem->ulid))
            ->assertForbidden();

        $this->assertSame(ProblemStatus::OPEN, $problem->refresh()->status);
    }

    #[Test]
    public function a_supervisor_can_close_a_problem_that_has_a_completed_action(): void
    {
        $problem = DeliveryProblem::factory()->create([
            'delivery_id' => $this->delivery->getKey(),
            'supplier_id' => $this->delivery->supplier_id,
            'status' => ProblemStatus::IN_PROGRESS,
        ]);
        CorrectiveAction::factory()->done()->create(['delivery_problem_id' => $problem->getKey()]);

        $this->actingAs($this->userWithRole('LOGISTIC'))
            ->post(route('problems.close', $problem->ulid), ['note' => 'Supplier sudah mengganti material.'])
            ->assertRedirect();

        $this->assertSame(ProblemStatus::CLOSED, $problem->refresh()->status);
    }

    #[Test]
    public function closing_without_a_completed_action_flashes_the_rule_instead_of_closing(): void
    {
        $problem = DeliveryProblem::factory()->create([
            'delivery_id' => $this->delivery->getKey(),
            'supplier_id' => $this->delivery->supplier_id,
            'status' => ProblemStatus::OPEN,
        ]);

        $this->from(route('problems.show', $problem->ulid))
            ->actingAs($this->userWithRole('LOGISTIC'))
            ->post(route('problems.close', $problem->ulid))
            ->assertRedirect(route('problems.show', $problem->ulid))
            ->assertSessionHas('error');

        $this->assertSame(ProblemStatus::OPEN, $problem->refresh()->status);
    }

    #[Test]
    public function cancelling_requires_a_reason(): void
    {
        $problem = DeliveryProblem::factory()->create([
            'delivery_id' => $this->delivery->getKey(),
            'supplier_id' => $this->delivery->supplier_id,
            'status' => ProblemStatus::OPEN,
        ]);

        $this->actingAs($this->userWithRole('LOGISTIC'))
            ->post(route('problems.cancel', $problem->ulid))
            ->assertSessionHasErrors('reason');

        $this->assertSame(ProblemStatus::OPEN, $problem->refresh()->status);
    }

    #[Test]
    public function a_corrective_action_is_not_reachable_through_another_problems_url(): void
    {
        $problem = DeliveryProblem::factory()->create([
            'delivery_id' => $this->delivery->getKey(),
            'supplier_id' => $this->delivery->supplier_id,
        ]);
        $other = DeliveryProblem::factory()->create([
            'delivery_id' => $this->delivery->getKey(),
            'supplier_id' => $this->delivery->supplier_id,
        ]);
        $action = CorrectiveAction::factory()->create(['delivery_problem_id' => $problem->getKey()]);

        $this->actingAs($this->clerk)
            ->post(route('corrective-actions.complete', [$other->ulid, $action->id]))
            ->assertNotFound();
    }

    #[Test]
    public function a_viewer_may_read_problems_but_never_report_one(): void
    {
        $viewer = $this->userWithRole('VIEWER');

        $this->actingAs($viewer)->get(route('problems.index'))->assertOk();

        $this->actingAs($viewer)
            ->get(route('problems.create', $this->delivery->ulid))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('problems.store', $this->delivery->ulid), $this->payload())
            ->assertForbidden();
    }

    #[Test]
    public function a_user_without_the_view_permission_never_reaches_the_module(): void
    {
        $this->actingAs($this->userWithPermissions(['delivery.view']))
            ->get(route('problems.index'))
            ->assertForbidden();
    }

    #[Test]
    public function the_delivery_screen_offers_reporting_only_to_a_user_who_may_report(): void
    {
        $this->actingAs($this->clerk)
            ->get(route('deliveries.show', $this->delivery->ulid))
            ->assertInertia(fn ($page) => $page->where('can.reportProblem', true));

        $this->actingAs($this->userWithRole('VIEWER'))
            ->get(route('deliveries.show', $this->delivery->ulid))
            ->assertInertia(fn ($page) => $page->where('can.reportProblem', false));
    }

    #[Test]
    public function a_super_administrator_is_not_offered_actions_a_settled_problem_refuses(): void
    {
        $problem = DeliveryProblem::factory()->create([
            'delivery_id' => $this->delivery->getKey(),
            'supplier_id' => $this->delivery->supplier_id,
            'status' => ProblemStatus::CLOSED,
        ]);

        /*
         * The super-admin gate bypass passes every permission check, but the
         * closing rule is not a permission - it is the record's own state - so
         * the bypass must defer here rather than putting a button on screen
         * that the service would then refuse.
         */
        $this->actingAs($this->userWithRole('SUPER_ADMIN'))
            ->get(route('problems.show', $problem->ulid))
            ->assertInertia(fn ($page) => $page
                ->where('can.update', false)
                ->where('can.close', false)
                ->where('can.cancel', false)
                ->where('can.addAction', false));

        $this->actingAs($this->userWithRole('SUPER_ADMIN'))
            ->post(route('problems.close', $problem->ulid))
            ->assertForbidden();
    }

    #[Test]
    public function a_super_administrator_may_still_close_an_open_problem(): void
    {
        $problem = DeliveryProblem::factory()->create([
            'delivery_id' => $this->delivery->getKey(),
            'supplier_id' => $this->delivery->supplier_id,
            'status' => ProblemStatus::OPEN,
        ]);
        CorrectiveAction::factory()->done()->create(['delivery_problem_id' => $problem->getKey()]);

        $this->actingAs($this->userWithRole('SUPER_ADMIN'))
            ->post(route('problems.close', $problem->ulid))
            ->assertRedirect();

        $this->assertSame(ProblemStatus::CLOSED, $problem->refresh()->status);
    }

    #[Test]
    public function a_settled_problem_is_no_longer_offered_for_editing(): void
    {
        $problem = DeliveryProblem::factory()->create([
            'delivery_id' => $this->delivery->getKey(),
            'supplier_id' => $this->delivery->supplier_id,
            'status' => ProblemStatus::CLOSED,
        ]);

        $this->actingAs($this->userWithRole('LOGISTIC'))
            ->get(route('problems.show', $problem->ulid))
            ->assertInertia(fn ($page) => $page
                ->where('can.update', false)
                ->where('can.close', false)
                ->where('can.cancel', false));

        $this->actingAs($this->userWithRole('LOGISTIC'))
            ->get(route('problems.edit', $problem->ulid))
            ->assertForbidden();
    }
}
