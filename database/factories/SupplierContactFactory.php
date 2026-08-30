<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Supplier;
use App\Models\SupplierContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierContact>
 */
class SupplierContactFactory extends Factory
{
    protected $model = SupplierContact::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'name' => $this->faker->name(),
            'position' => $this->faker->randomElement(['Sales', 'Sales Manager', 'Logistic', 'Account Manager']),
            'phone' => $this->faker->numerify('08##########'),
            'email' => $this->faker->unique()->safeEmail(),
            'is_primary' => false,
            'status' => RecordStatus::ACTIVE,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (): array => ['is_primary' => true]);
    }
}
