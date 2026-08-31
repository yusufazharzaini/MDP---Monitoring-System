<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DataTransferObjects\DeliveryMetrics;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The rate derivations of requirements 14 and 15.
 */
final class DeliveryMetricsTest extends TestCase
{
    private function metrics(array $overrides = []): DeliveryMetrics
    {
        $values = [
            'totalDelivery' => 1250, 'onTimeDelivery' => 1210, 'lateDelivery' => 40,
            'shortDelivery' => 18, 'overDelivery' => 4,
            'quantityOrdered' => 10000.0, 'quantityReceived' => 9500.0,
            'quantityShortage' => 500.0, 'quantityExcess' => 0.0, 'pendingOrderLines' => 25,
            ...$overrides,
        ];

        return new DeliveryMetrics(...$values);
    }

    #[Test]
    public function on_time_rate_is_on_time_over_total(): void
    {
        $this->assertSame(96.8, $this->metrics()->onTimeRate());
    }

    #[Test]
    public function late_and_short_rates_use_the_same_denominator(): void
    {
        $metrics = $this->metrics();

        $this->assertSame(3.2, $metrics->lateRate());
        $this->assertSame(1.44, $metrics->shortRate());
    }

    #[Test]
    public function quantity_fulfilment_is_received_over_ordered(): void
    {
        $this->assertSame(95.0, $this->metrics()->quantityFulfillment());
    }

    #[Test]
    public function an_over_delivery_cannot_push_fulfilment_past_one_hundred(): void
    {
        $metrics = $this->metrics(['quantityOrdered' => 1000.0, 'quantityReceived' => 1200.0]);

        $this->assertSame(100.0, $metrics->quantityFulfillment());
    }

    #[Test]
    public function an_empty_period_yields_zero_rather_than_dividing_by_zero(): void
    {
        $empty = DeliveryMetrics::empty();

        $this->assertSame(0.0, $empty->onTimeRate());
        $this->assertSame(0.0, $empty->lateRate());
        $this->assertSame(0.0, $empty->quantityFulfillment());
        $this->assertFalse($empty->hasActivity());
    }

    #[Test]
    public function the_array_form_carries_every_figure_the_dashboard_renders(): void
    {
        $array = $this->metrics()->toArray();

        foreach ([
            'total_delivery', 'on_time_delivery', 'late_delivery', 'short_delivery',
            'over_delivery', 'pending_order_lines', 'quantity_ordered', 'quantity_received',
            'quantity_shortage', 'quantity_excess', 'on_time_rate', 'late_rate', 'quantity_fulfillment',
        ] as $key) {
            $this->assertArrayHasKey($key, $array);
        }
    }
}
