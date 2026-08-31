<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\DataTransferObjects\DashboardFilter;
use App\Models\Supplier;
use App\Services\Dashboard\ParetoAnalysisService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Support\DemoBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ParetoAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private ParetoAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->service = app(ParetoAnalysisService::class);
    }

    #[Test]
    public function the_frequency_count_reproduces_the_seeded_distribution(): void
    {
        $counts = $this->service->calculateProblemFrequency(DashboardFilter::currentMonth())
            ->mapWithKeys(static fn (object $row): array => [$row->category_code => (int) $row->problem_count])
            ->all();

        $this->assertSame(DemoBlueprint::PROBLEM_DISTRIBUTION, $counts);
    }

    #[Test]
    public function the_dataset_is_ranked_most_frequent_first(): void
    {
        $dataset = $this->service->generateParetoDataset(DashboardFilter::currentMonth());
        $counts = array_column($dataset['categories'], 'count');

        $sorted = $counts;
        rsort($sorted);

        $this->assertSame($sorted, $counts);
        $this->assertSame(range(1, count($counts)), array_column($dataset['categories'], 'rank'));
    }

    #[Test]
    public function the_dataset_reproduces_the_reference_cumulative_curve(): void
    {
        $dataset = $this->service->generateParetoDataset(DashboardFilter::currentMonth());

        $this->assertSame(83, $dataset['total_problems']);
        $this->assertSame(
            [46.0, 75.0, 89.0, 96.0, 100.0],
            array_map(
                static fn (array $c): float => round($c['cumulative_percentage']),
                $dataset['categories'],
            ),
        );
    }

    #[Test]
    public function the_vital_few_are_the_categories_up_to_the_eighty_percent_line(): void
    {
        $dataset = $this->service->generateParetoDataset(DashboardFilter::currentMonth());

        // 45.8 / 74.7 / 89.2 - the third category is the one that crosses 80%,
        // so it belongs to the vital few that must be addressed.
        $this->assertSame(3, $dataset['vital_few_count']);
        $this->assertTrue($dataset['categories'][2]['is_vital_few']);
        $this->assertFalse($dataset['categories'][3]['is_vital_few']);
    }

    #[Test]
    public function the_percentages_of_every_category_sum_to_one_hundred(): void
    {
        $dataset = $this->service->generateParetoDataset(DashboardFilter::currentMonth());

        $this->assertEqualsWithDelta(
            100.0,
            array_sum(array_column($dataset['categories'], 'percentage')),
            0.05,
        );
    }

    #[Test]
    public function the_dataset_respects_the_supplier_filter(): void
    {
        $supplier = Supplier::query()->where('code', 'SUP-001')->firstOrFail();

        $all = $this->service->generateParetoDataset(DashboardFilter::currentMonth());
        $scoped = $this->service->generateParetoDataset(DashboardFilter::fromArray([
            'period' => now()->format('Y-m'),
            'supplier_id' => $supplier->getKey(),
        ]));

        $this->assertLessThan($all['total_problems'], $scoped['total_problems']);
        $this->assertSame(
            100.0,
            end($scoped['categories'])['cumulative_percentage'],
            'A filtered curve must still close at 100%.',
        );
    }

    #[Test]
    public function a_period_with_no_problems_yields_an_empty_dataset_not_an_error(): void
    {
        $dataset = $this->service->generateParetoDataset(DashboardFilter::fromArray([
            'date_from' => '2000-01-01',
            'date_to' => '2000-01-31',
        ]));

        $this->assertSame(0, $dataset['total_problems']);
        $this->assertSame([], $dataset['categories']);
        $this->assertSame(0, $dataset['vital_few_count']);
    }

    #[Test]
    public function the_whole_dataset_is_one_grouped_query(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service->generateParetoDataset(DashboardFilter::currentMonth());
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $queries, 'Pareto must not query per category.');
    }
}
