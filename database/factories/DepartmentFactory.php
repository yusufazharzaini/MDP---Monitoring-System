<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('DEP##')),
            'name' => $this->faker->unique()->randomElement([
                'Purchasing', 'Warehouse', 'Logistic', 'Production', 'Quality Assurance', 'Management',
            ]).' '.$this->faker->unique()->numberBetween(1, 9999),
            'description' => $this->faker->sentence(),
            'status' => RecordStatus::ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => RecordStatus::INACTIVE]);
    }
}
