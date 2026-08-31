<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'action' => AuditAction::UPDATED,
            'module' => 'PurchaseOrder',
            'record_id' => $this->faker->numberBetween(1, 1000),
            'old_values' => ['qty_ordered' => 1000],
            'new_values' => ['qty_ordered' => 1200],
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'created_at' => now(),
        ];
    }
}
