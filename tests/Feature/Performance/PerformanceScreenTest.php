<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Enums\EvaluationStatus;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Phase 7 screens and the permissions that gate them.
 *
 * Supplier performance is reporting, so it sits behind `report.view`. Signing
 * off a scorecard is a management judgement, so it needs `evaluation.approve` -
 * which a VIEWER holding `report.view` does not have.
 */
final class PerformanceScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function the_ranking_screen_lists_every_supplier_with_the_grade_bands(): void
    {
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('supplier-performance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SupplierPerformance/Index')
                ->has('ranking')
                ->has('thresholds', 4)
                ->where('thresholds.0.grade', 'EXCELLENT')
                ->has('options.plants'));
    }

    #[Test]
    public function the_ranking_is_ordered_and_ranked_in_the_payload(): void
    {
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('supplier-performance.index'))
            ->assertInertia(function ($page) {
                $ranking = $page->toArray()['props']['ranking'];

                $this->assertNotEmpty($ranking);
                $this->assertSame(1, $ranking[0]['rank']);

                $rates = array_column($ranking, 'service_rate');
                $sorted = $rates;
                rsort($sorted);

                $this->assertSame($sorted, $rates, 'the payload arrives ranked, not sorted in the page');
            });
    }

    #[Test]
    public function the_scorecard_screen_carries_metrics_trend_and_problems(): void
    {
        $supplier = Supplier::query()->where('name', 'Supplier A')->firstOrFail();

        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('supplier-performance.show', $supplier->ulid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SupplierPerformance/Show')
                ->where('scorecard.supplier.code', $supplier->code)
                ->has('scorecard.metrics')
                ->has('scorecard.trend', 6)
                ->has('scorecard.problem_breakdown')
                ->where('can.viewEvaluations', true));
    }

    #[Test]
    public function a_user_without_report_view_cannot_reach_supplier_performance(): void
    {
        $this->actingAs($this->userWithPermissions(['dashboard.view']))
            ->get(route('supplier-performance.index'))
            ->assertForbidden();
    }

    #[Test]
    public function the_critical_material_screen_counts_from_the_same_set_it_lists(): void
    {
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('critical-materials.index'))
            ->assertOk()
            ->assertInertia(function ($page) {
                $props = $page->toArray()['props'];

                $this->assertSame(
                    count($props['materials']),
                    $props['summary']['total'],
                    'the headline and the table must not disagree',
                );
            });
    }

    #[Test]
    public function the_evaluation_register_is_gated_by_its_own_permission(): void
    {
        // WAREHOUSE handles goods; it has no business grading suppliers.
        $this->actingAs($this->userWithRole('WAREHOUSE'))
            ->get(route('supplier-evaluations.index'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('supplier-evaluations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SupplierEvaluations/Index')
                ->where('can.create', true));
    }

    #[Test]
    public function a_viewer_may_read_evaluations_but_never_generate_or_approve_one(): void
    {
        $evaluation = SupplierEvaluation::factory()->create([
            'supplier_id' => Supplier::query()->value('id'),
        ]);
        $viewer = $this->userWithRole('VIEWER');

        $this->actingAs($viewer)
            ->get(route('supplier-evaluations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.create', false));

        $this->actingAs($viewer)
            ->get(route('supplier-evaluations.show', $evaluation->getKey()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.regenerate', false)
                ->where('can.approve', false));

        $this->actingAs($viewer)
            ->post(route('supplier-evaluations.approve', $evaluation->getKey()))
            ->assertForbidden();
    }

    #[Test]
    public function generating_a_period_creates_drafts_through_the_screen(): void
    {
        $period = Carbon::now()->format('Y-m');

        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->post(route('supplier-evaluations.store'), ['period' => $period])
            ->assertRedirect();

        $this->assertGreaterThan(0, SupplierEvaluation::query()->count());
        $this->assertSame(
            0,
            SupplierEvaluation::query()->where('status', '!=', EvaluationStatus::DRAFT->value)->count(),
        );
    }

    #[Test]
    public function a_future_period_is_refused_by_the_form(): void
    {
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->post(route('supplier-evaluations.store'), [
                'period' => Carbon::now()->addMonth()->format('Y-m'),
            ])
            ->assertSessionHasErrors('period');

        $this->assertDatabaseCount('supplier_evaluations', 0);
    }

    #[Test]
    public function approving_through_the_screen_freezes_the_scorecard(): void
    {
        $evaluation = SupplierEvaluation::factory()->create([
            'supplier_id' => Supplier::query()->value('id'),
        ]);

        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->post(route('supplier-evaluations.approve', $evaluation->getKey()))
            ->assertRedirect();

        $this->assertSame(EvaluationStatus::APPROVED, $evaluation->refresh()->status);

        // And the buttons that would change it are gone.
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('supplier-evaluations.show', $evaluation->getKey()))
            ->assertInertia(fn ($page) => $page
                ->where('can.approve', false)
                ->where('can.regenerate', false)
                ->where('can.reopen', true));
    }

    #[Test]
    public function reopening_requires_a_reason(): void
    {
        $evaluation = SupplierEvaluation::factory()->approved()->create([
            'supplier_id' => Supplier::query()->value('id'),
        ]);

        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->post(route('supplier-evaluations.reopen', $evaluation->getKey()))
            ->assertSessionHasErrors('reason');

        $this->assertSame(EvaluationStatus::APPROVED, $evaluation->refresh()->status);
    }

    #[Test]
    public function a_super_administrator_is_not_offered_actions_an_approved_scorecard_refuses(): void
    {
        $evaluation = SupplierEvaluation::factory()->approved()->create([
            'supplier_id' => Supplier::query()->value('id'),
        ]);

        // The gate bypass grants every permission, but "already approved" is
        // the record's state rather than a permission question.
        $this->actingAs($this->userWithRole('SUPER_ADMIN'))
            ->get(route('supplier-evaluations.show', $evaluation->getKey()))
            ->assertInertia(fn ($page) => $page
                ->where('can.approve', false)
                ->where('can.regenerate', false));

        $this->actingAs($this->userWithRole('SUPER_ADMIN'))
            ->post(route('supplier-evaluations.approve', $evaluation->getKey()))
            ->assertForbidden();
    }
}
