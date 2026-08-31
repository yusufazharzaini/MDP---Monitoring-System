<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SupplierGrade;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierEvaluation>
 */
class SupplierEvaluationFactory extends Factory
{
    protected $model = SupplierEvaluation::class;

    public function definition(): array
    {
        $scores = [
            'delivery_score' => $this->faker->randomFloat(2, 70, 100),
            'quality_score' => $this->faker->randomFloat(2, 70, 100),
            'quantity_score' => $this->faker->randomFloat(2, 70, 100),
            'responsiveness_score' => $this->faker->randomFloat(2, 70, 100),
        ];

        $total = round(array_sum($scores) / count($scores), 4);

        return [
            'supplier_id' => Supplier::factory(),
            'period_year' => (int) now()->format('Y'),
            'period_month' => (int) now()->format('n'),
            ...$scores,
            'total_score' => $total,
            'grade' => match (true) {
                $total >= 98 => SupplierGrade::EXCELLENT,
                $total >= 95 => SupplierGrade::GOOD,
                $total >= 90 => SupplierGrade::AVERAGE,
                default => SupplierGrade::POOR,
            },
        ];
    }
}
