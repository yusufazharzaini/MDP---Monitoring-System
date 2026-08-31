<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\SupplierGrade;
use App\Exceptions\BusinessRuleException;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use App\Services\Supplier\SupplierEvaluationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SupplierEvaluationServiceTest extends TestCase
{
    use RefreshDatabase;

    private SupplierEvaluationService $service;

    private int $year;

    private int $month;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->service = app(SupplierEvaluationService::class);
        $this->year = (int) now()->format('Y');
        $this->month = (int) now()->format('n');
    }

    private function supplier(string $code): Supplier
    {
        return Supplier::query()->where('code', $code)->firstOrFail();
    }

    #[Test]
    public function the_delivery_score_is_the_suppliers_on_time_rate(): void
    {
        $scores = $this->service->calculateScores($this->supplier('SUP-001'), $this->year, $this->month);

        $this->assertSame(98.4, round($scores['delivery_score'], 1));
    }

    #[Test]
    public function every_component_score_stays_inside_zero_to_one_hundred(): void
    {
        foreach (['SUP-001', 'SUP-003', 'SUP-004'] as $code) {
            $scores = $this->service->calculateScores($this->supplier($code), $this->year, $this->month);

            foreach ($scores as $name => $value) {
                $this->assertGreaterThanOrEqual(0.0, $value, "{$code} {$name}");
                $this->assertLessThanOrEqual(100.0, $value, "{$code} {$name}");
            }
        }
    }

    #[Test]
    public function the_total_score_is_the_weighted_mean_of_the_components(): void
    {
        $total = $this->service->calculateTotalScore([
            'delivery_score' => 100.0,
            'quality_score' => 100.0,
            'quantity_score' => 100.0,
            'responsiveness_score' => 100.0,
        ]);

        $this->assertSame(100.0, $total);

        // Delivery carries 40 of the 100 weight.
        $deliveryOnly = $this->service->calculateTotalScore([
            'delivery_score' => 100.0,
            'quality_score' => 0.0,
            'quantity_score' => 0.0,
            'responsiveness_score' => 0.0,
        ]);

        $this->assertSame(40.0, $deliveryOnly);
    }

    #[Test]
    public function the_grade_follows_the_configured_bands(): void
    {
        $this->assertSame(SupplierGrade::EXCELLENT, $this->service->gradeFor(98.5));
        $this->assertSame(SupplierGrade::GOOD, $this->service->gradeFor(96.0));
        $this->assertSame(SupplierGrade::AVERAGE, $this->service->gradeFor(91.0));
        $this->assertSame(SupplierGrade::POOR, $this->service->gradeFor(80.0));
    }

    #[Test]
    public function generating_an_evaluation_persists_its_criteria_breakdown(): void
    {
        $supplier = $this->supplier('SUP-001');

        $evaluation = $this->service->generateForPeriod($supplier, $this->year, $this->month);

        $this->assertSame($supplier->getKey(), $evaluation->supplier_id);
        $this->assertSame($this->year, $evaluation->period_year);
        $this->assertCount(4, $evaluation->items);
        $this->assertSame(100.0, $evaluation->items->sum('weight'), 'Criteria weights must total 100.');
        $this->assertSame(
            $this->service->calculateTotalScore([
                'delivery_score' => $evaluation->delivery_score,
                'quality_score' => $evaluation->quality_score,
                'quantity_score' => $evaluation->quantity_score,
                'responsiveness_score' => $evaluation->responsiveness_score,
            ]),
            $evaluation->total_score,
        );
    }

    #[Test]
    public function regenerating_updates_in_place_rather_than_duplicating(): void
    {
        $supplier = $this->supplier('SUP-002');

        $first = $this->service->generateForPeriod($supplier, $this->year, $this->month);
        $second = $this->service->generateForPeriod($supplier, $this->year, $this->month, 'Reviewed again');

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame('Reviewed again', $second->remarks);
        $this->assertSame(1, SupplierEvaluation::query()->where('supplier_id', $supplier->getKey())->count());
        $this->assertCount(4, $second->items, 'Regenerating must not leave orphaned criteria rows.');
    }

    #[Test]
    public function a_poor_supplier_scores_below_a_strong_one(): void
    {
        $strong = $this->service->generateForPeriod($this->supplier('SUP-001'), $this->year, $this->month);
        $weak = $this->service->generateForPeriod($this->supplier('SUP-004'), $this->year, $this->month);

        $this->assertGreaterThan($weak->total_score, $strong->total_score);
    }

    #[Test]
    public function an_impossible_period_is_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->service->generateForPeriod($this->supplier('SUP-001'), $this->year, 13);
    }

    #[Test]
    public function a_supplier_with_no_problems_scores_full_responsiveness(): void
    {
        $supplier = Supplier::factory()->create();

        $scores = $this->service->calculateScores($supplier, $this->year, $this->month);

        $this->assertSame(100.0, $scores['responsiveness_score'], 'Nothing to respond to is the best outcome.');
    }

    #[Test]
    public function generating_for_all_suppliers_covers_only_those_active_in_the_month(): void
    {
        Supplier::factory()->create();

        $evaluations = $this->service->generateForAllSuppliers($this->year, $this->month);

        $this->assertCount(8, $evaluations, 'The eight demo suppliers delivered; the new one did not.');
    }
}
