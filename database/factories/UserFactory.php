<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Department;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'department_id' => null,
            'plant_id' => null,
            'employee_code' => strtoupper($this->faker->unique()->bothify('EMP####')),
            'position' => $this->faker->jobTitle(),
            'phone' => $this->faker->numerify('08##########'),
            'status' => RecordStatus::ACTIVE,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => RecordStatus::INACTIVE]);
    }

    public function inDepartment(Department $department): static
    {
        return $this->state(fn (): array => ['department_id' => $department->getKey()]);
    }

    public function atPlant(Plant $plant): static
    {
        return $this->state(fn (): array => ['plant_id' => $plant->getKey()]);
    }
}
