<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\PurchaseOrderItem;
use App\Services\Delivery\DeliveryStatusService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The demo seeders precompute the derived status columns for speed. This test
 * proves that shortcut is honest: running the real DeliveryStatusService over
 * seeded rows must not change a single value.
 *
 * If the status rules and the seeder ever diverge, this fails.
 */
final class SeedConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sampled rather than exhaustive: a systematic divergence shows up in the
     * first handful of rows, and the full 2,500-line replay is too slow to run
     * on every commit.
     */
    private const SAMPLE_SIZE = 150;

    #[Test]
    public function recalculating_seeded_lines_is_a_no_op(): void
    {
        $this->seed(DatabaseSeeder::class);

        $service = app(DeliveryStatusService::class);

        $items = PurchaseOrderItem::query()
            ->inRandomOrder()
            ->limit(self::SAMPLE_SIZE)
            ->get();

        $this->assertCount(self::SAMPLE_SIZE, $items);

        $tracked = ['qty_received', 'fulfillment_status', 'timeliness_status', 'overall_status',
            'first_receipt_date', 'last_receipt_date'];

        foreach ($items as $item) {
            $before = $item->only($tracked);

            $service->recalculateForPurchaseOrderItem($item);

            $this->assertEquals(
                $before,
                $item->fresh()?->only($tracked),
                "Seeded rollup for purchase_order_item {$item->getKey()} disagrees with DeliveryStatusService.",
            );
        }
    }
}
