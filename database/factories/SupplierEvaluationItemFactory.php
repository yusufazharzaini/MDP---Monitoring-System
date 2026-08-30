<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SupplierEvaluation;
use App\Models\SupplierEvaluationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierEvaluationItem>
 */
class SupplierEvaluationItemFactory extends Factory
{
    protected $model = SupplierEvaluationItem::class;

    public function definition(): array
    {
        return [
            'supplier_evaluation_id' => SupplierEvaluation::factory(),
            'criteria_name' => $this->faker->randomElement(['Delivery', 'Quality', 'Quantity', 'Responsiveness']),
            'weight' => 25,
            'score' => $this->faker->randomFloat(2, 70, 100),
            'remarks' => null,
        ];
    }
}
