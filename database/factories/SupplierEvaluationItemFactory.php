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
    /**
     * The four scoring criteria of a supplier scorecard.
     *
     * @var array<int, string>
     */
    private const CRITERIA = ['Delivery', 'Quality', 'Quantity', 'Responsiveness'];

    /**
     * Rotates through the criteria so consecutive creates never collide with
     * the (supplier_evaluation_id, criteria_name) unique key.
     */
    private static int $rotation = 0;

    protected $model = SupplierEvaluationItem::class;

    public function definition(): array
    {
        $criteria = self::CRITERIA[self::$rotation++ % count(self::CRITERIA)];

        return [
            'supplier_evaluation_id' => SupplierEvaluation::factory(),
            'criteria_name' => $criteria,
            'weight' => 25,
            'score' => $this->faker->randomFloat(2, 70, 100),
            'remarks' => null,
        ];
    }

    public function forCriteria(string $criteria): static
    {
        return $this->state(fn (): array => ['criteria_name' => $criteria]);
    }
}
