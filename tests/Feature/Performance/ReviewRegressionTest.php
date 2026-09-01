<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\EvaluationStatus;
use App\Enums\ProblemSeverity;
use App\Enums\ProblemStatus;
use App\Enums\RiskLevel;
use App\Models\Delivery;
use App\Models\DeliveryProblem;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use App\Notifications\Problem\OverdueProblemsDigest;
use App\Services\Dashboard\DashboardService;
use App\Services\Performance\SupplierPerformanceService;
use App\Services\Setting\KpiSettingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regressions for the defects a review of Phases 6 to 8 turned up.
 *
 * Each of these passed its own module's tests: they are the failures that live
 * between a screen and the service behind it, or between a comment and the code
 * under it. Pinned here so they cannot come back quietly.
 */
final class ReviewRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    /**
     * The seeded problems, and the corrective actions that hold them in place.
     *
     * corrective_actions RESTRICTs on its problem by design - the evidence
     * outlives any cleanup of its parent - so the children go first.
     */
    private function clearSeededProblems(): void
    {
        DB::table('corrective_actions')->delete();
        DB::table('problem_attachments')->delete();
        DeliveryProblem::query()->delete();
    }

    /**
     * A problem against a receipt that already exists.
     *
     * DeliveryProblemFactory mints its own delivery otherwise, and its random
     * delivery_number collides with the seeded run.
     */
    private function problemOn(Delivery $delivery, ProblemSeverity $severity, string $dueDate, string $problemDate): DeliveryProblem
    {
        return DeliveryProblem::factory()->create([
            'delivery_id' => $delivery->getKey(),
            'supplier_id' => $delivery->supplier_id,
            'material_id' => null,
            'status' => ProblemStatus::OPEN,
            'severity' => $severity,
            'problem_date' => $problemDate,
            'due_date' => $dueDate,
        ]);
    }

    #[Test]
    public function the_elevated_risk_tile_counts_critical_as_well_as_high(): void
    {
        $response = $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('critical-materials.index'));

        $props = $response->viewData('page')['props'];

        $expected = collect($props['materials'])
            ->filter(fn (array $row): bool => RiskLevel::from($row['risk_level'])->isElevated())
            ->count();

        // Counting the string 'HIGH' alone left the tile reading zero while the
        // table below was all Critical badges.
        $this->assertSame($expected, $props['summary']['high_risk']);
        $this->assertGreaterThan(0, $expected, 'the seeded period should carry elevated risk');
    }

    #[Test]
    public function critical_is_elevated_and_medium_is_not(): void
    {
        $this->assertTrue(RiskLevel::CRITICAL->isElevated());
        $this->assertTrue(RiskLevel::HIGH->isElevated());
        $this->assertFalse(RiskLevel::MEDIUM->isElevated());
        $this->assertFalse(RiskLevel::LOW->isElevated());
    }

    #[Test]
    public function the_grade_legend_and_the_grader_agree_when_a_threshold_is_missing(): void
    {
        $performance = app(SupplierPerformanceService::class);
        $kpi = app(KpiSettingService::class);

        // Deactivate the Excellent band, as an operator retuning KPIs might.
        DB::table('kpi_settings')->where('code', 'GRADE_EXCELLENT')->delete();
        $kpi->flush();

        $legendFloor = collect($performance->gradeBands())->firstWhere('grade', 'EXCELLENT')['floor'];

        // The legend said "Excellent >= 0%" while grading still required 98.
        $this->assertSame(98.0, $legendFloor);
        $this->assertSame('GOOD', $kpi->gradeFor(97.0)->value);
        $this->assertSame('EXCELLENT', $kpi->gradeFor(98.0)->value);
    }

    #[Test]
    public function the_legend_follows_a_retuned_band_rather_than_its_fallback(): void
    {
        DB::table('kpi_settings')->where('code', 'GRADE_GOOD')->update(['target_value' => 96]);
        app(KpiSettingService::class)->flush();

        $bands = collect(app(SupplierPerformanceService::class)->gradeBands());

        $this->assertSame(96.0, $bands->firstWhere('grade', 'GOOD')['floor']);
        $this->assertSame(96.0, $bands->firstWhere('grade', 'AVERAGE')['ceiling']);
    }

    #[Test]
    public function the_scorecard_payload_withholds_evaluations_from_a_user_who_may_not_read_them(): void
    {
        $supplier = Supplier::query()->where('code', 'SUP-001')->firstOrFail();
        SupplierEvaluation::factory()->approved()->create([
            'supplier_id' => $supplier->getKey(),
            'period_year' => 2026,
            'period_month' => 7,
        ]);

        // WAREHOUSE holds report.view but not evaluation.view.
        $response = $this->actingAs($this->userWithRole('WAREHOUSE'))
            ->get(route('supplier-performance.show', $supplier->ulid));

        $props = $response->viewData('page')['props'];

        // Hiding the panel client-side still shipped every score, grade and
        // approver name down the wire.
        $this->assertFalse($props['can']['viewEvaluations']);
        $this->assertSame([], $props['evaluations']);
    }

    #[Test]
    public function a_user_who_may_read_evaluations_still_receives_them(): void
    {
        $supplier = Supplier::query()->where('code', 'SUP-001')->firstOrFail();
        SupplierEvaluation::factory()->create([
            'supplier_id' => $supplier->getKey(),
            'period_year' => 2026,
            'period_month' => 7,
        ]);

        $response = $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('supplier-performance.show', $supplier->ulid));

        $props = $response->viewData('page')['props'];

        $this->assertTrue($props['can']['viewEvaluations']);
        $this->assertCount(1, $props['evaluations']);
    }

    #[Test]
    public function the_evaluation_detail_carries_the_supplier_its_regenerate_button_needs(): void
    {
        $evaluation = SupplierEvaluation::factory()->create([
            'supplier_id' => Supplier::query()->value('id'),
        ]);

        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('supplier-evaluations.show', $evaluation->getKey()))
            ->assertInertia(fn ($page) => $page
                ->where('record.supplier_id', $evaluation->supplier_id));
    }

    #[Test]
    public function regenerating_one_scorecard_leaves_the_others_alone(): void
    {
        $period = Carbon::now();
        $manager = $this->userWithRole('MANAGEMENT');

        $this->actingAs($manager)
            ->post(route('supplier-evaluations.store'), ['period' => $period->format('Y-m')])
            ->assertRedirect();

        $all = SupplierEvaluation::query()->forPeriod((int) $period->format('Y'), (int) $period->format('n'))->get();
        $this->assertGreaterThan(1, $all->count());

        $target = $all->first();
        $others = $all->skip(1)->pluck('updated_at', 'id');

        Carbon::setTestNow(Carbon::now()->addMinutes(5));

        $this->actingAs($manager)
            ->post(route('supplier-evaluations.store'), [
                'period' => $period->format('Y-m'),
                'supplier_id' => $target->supplier_id,
            ])
            ->assertRedirect();

        // Posting without a supplier took the batch branch and recomputed the
        // whole month from a button labelled "recompute this one".
        foreach ($others as $id => $updatedAt) {
            $this->assertEquals(
                $updatedAt,
                SupplierEvaluation::query()->findOrFail($id)->updated_at,
                "evaluation {$id} should not have been touched",
            );
        }

        Carbon::setTestNow();
    }

    #[Test]
    public function the_overdue_digest_never_drops_a_critical_problem_for_a_lesser_one(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-09-01 07:00:00');

        $this->clearSeededProblems();
        $delivery = Delivery::query()->firstOrFail();

        // Eleven problems sharing one due date, so only the ordering decides
        // which ten the digest names.
        foreach (range(1, 10) as $i) {
            $this->problemOn($delivery, ProblemSeverity::LOW, '2026-08-10', '2026-08-01');
        }

        $critical = $this->problemOn($delivery, ProblemSeverity::CRITICAL, '2026-08-10', '2026-08-01');

        $supervisor = $this->userWithRole('LOGISTIC');

        $this->artisan('problems:notify-overdue')->assertSuccessful();

        Notification::assertSentTo($supervisor, function (OverdueProblemsDigest $digest) use ($critical): bool {
            $numbers = array_column($digest->worst, 'problem_number');

            return in_array($critical->problem_number, $numbers, true)
                && $numbers[0] === $critical->problem_number;
        });

        Carbon::setTestNow();
    }

    #[Test]
    public function the_digest_still_puts_the_most_overdue_first(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-09-01 07:00:00');

        $this->clearSeededProblems();
        $delivery = Delivery::query()->firstOrFail();

        $oldest = $this->problemOn($delivery, ProblemSeverity::LOW, '2026-07-05', '2026-07-01');
        $this->problemOn($delivery, ProblemSeverity::CRITICAL, '2026-08-20', '2026-08-01');

        $supervisor = $this->userWithRole('LOGISTIC');
        $this->artisan('problems:notify-overdue')->assertSuccessful();

        // Severity is the tiebreak, not the primary sort: lateness still leads.
        Notification::assertSentTo(
            $supervisor,
            fn (OverdueProblemsDigest $digest): bool => $digest->worst[0]['problem_number'] === $oldest->problem_number,
        );

        Carbon::setTestNow();
    }

    #[Test]
    public function the_dashboard_trend_ends_at_the_month_the_seed_is_anchored_to(): void
    {
        // The assertion that broke on the 1st of September because it named a
        // month rather than deriving one.
        $trend = app(DashboardService::class)
            ->getServiceRateTrend(DashboardFilter::currentMonth());

        $this->assertSame(now()->format('Y-m'), $trend[5]['period']);
    }

    #[Test]
    public function an_approved_scorecard_is_still_refused_a_recompute_after_these_changes(): void
    {
        $evaluation = SupplierEvaluation::factory()->approved()->create([
            'supplier_id' => Supplier::query()->value('id'),
            'period_year' => 2026,
            'period_month' => 7,
        ]);

        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->post(route('supplier-evaluations.store'), [
                'period' => '2026-07',
                'supplier_id' => $evaluation->supplier_id,
            ])
            ->assertForbidden();

        $this->assertSame(EvaluationStatus::APPROVED, $evaluation->refresh()->status);
    }
}
