<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\DataTransferObjects\DashboardFilter;
use App\Repositories\DashboardRepository;
use Illuminate\Support\Collection;

/**
 * Pareto analysis of delivery problems: which few causes account for most of
 * the pain.
 *
 * The frequency count is a grouped SQL query; only the ranked categories - a
 * handful of rows - reach PHP, where the running total is applied.
 */
class ParetoAnalysisService
{
    /**
     * The classic 80% line: categories at or below it are the vital few.
     */
    public const VITAL_FEW_THRESHOLD = 80.0;

    public function __construct(
        private readonly DashboardRepository $repository,
    ) {}

    /**
     * Problem counts per category, most frequent first.
     *
     * @return Collection<int, object>
     */
    public function calculateProblemFrequency(DashboardFilter $filter): Collection
    {
        return $this->repository->problemFrequency($filter);
    }

    /**
     * One category's share of all problems in the period.
     */
    public function calculatePercentage(int $count, int $total): float
    {
        return $total <= 0 ? 0.0 : round($count / $total * 100, 2);
    }

    /**
     * Running share, in the order given.
     *
     * The caller is responsible for passing the counts already ranked - a
     * cumulative curve computed over an unsorted list is not a Pareto chart.
     *
     * @param  array<int, int>  $counts
     * @return array<int, float>
     */
    public function calculateCumulativePercentage(array $counts): array
    {
        $total = array_sum($counts);

        if ($total <= 0) {
            return array_map(static fn (): float => 0.0, $counts);
        }

        $running = 0;

        return array_map(function (int $count) use (&$running, $total): float {
            $running += $count;

            return round($running / $total * 100, 2);
        }, $counts);
    }

    /**
     * The full dataset the Pareto chart renders: bars, cumulative line, and the
     * 80% cut-off marker.
     *
     * @return array<string, mixed>
     */
    public function generateParetoDataset(DashboardFilter $filter): array
    {
        $rows = $this->calculateProblemFrequency($filter);
        $counts = $rows->map(static fn (object $row): int => (int) $row->problem_count)->all();
        $total = array_sum($counts);
        $cumulative = $this->calculateCumulativePercentage($counts);

        $categories = $rows->values()->map(function (object $row, int $index) use ($total, $cumulative): array {
            $count = (int) $row->problem_count;

            return [
                'rank' => $index + 1,
                'category_id' => (int) $row->category_id,
                'category_code' => $row->category_code,
                'category' => $row->category_name,
                'count' => $count,
                'percentage' => $this->calculatePercentage($count, $total),
                'cumulative_percentage' => $cumulative[$index],
                'is_vital_few' => $this->isVitalFew($cumulative, $index),
            ];
        })->all();

        return [
            'threshold' => self::VITAL_FEW_THRESHOLD,
            'total_problems' => $total,
            'vital_few_count' => count(array_filter($categories, static fn (array $c): bool => $c['is_vital_few'])),
            'categories' => $categories,
        ];
    }

    /**
     * A category is one of the vital few if the running total had not yet
     * reached the threshold *before* it - so the category that crosses the line
     * is included, which is what makes the 80/20 reading actionable.
     *
     * @param  array<int, float>  $cumulative
     */
    private function isVitalFew(array $cumulative, int $index): bool
    {
        return $index === 0 || $cumulative[$index - 1] < self::VITAL_FEW_THRESHOLD;
    }
}
