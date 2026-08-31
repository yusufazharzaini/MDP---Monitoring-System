<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'code' => strtoupper($this->faker->unique()->bothify('SUP####')),
            'name' => $name,
            'short_name' => strtoupper(substr($name, 0, 20)),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'country' => 'Indonesia',
            'pic_name' => $this->faker->name(),
            'pic_email' => $this->faker->unique()->safeEmail(),
            'pic_phone' => $this->faker->numerify('08##########'),
            'lead_time_days' => $this->faker->numberBetween(3, 21),
            'payment_term' => $this->faker->randomElement(['NET 30', 'NET 45', 'NET 60', 'COD']),
            'supplier_type' => $this->faker->randomElement(SupplierType::cases()),
            'status' => SupplierStatus::ACTIVE,
        ];
    }

    public function blacklisted(): static
    {
        return $this->state(fn (): array => ['status' => SupplierStatus::BLACKLISTED]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => SupplierStatus::INACTIVE]);
    }
}
