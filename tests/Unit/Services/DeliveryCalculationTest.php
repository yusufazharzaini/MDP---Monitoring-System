<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\OverallDeliveryStatus;
use App\Enums\QuantityStatus;
use App\Enums\TimelinessStatus;
use App\Services\Delivery\DeliveryStatusCalculator;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The delivery performance rules from docs/03, including the four worked cases
 * the specification calls out by name.
 */
final class DeliveryCalculationTest extends TestCase
{
    private DeliveryStatusCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new DeliveryStatusCalculator;
    }

    /**
     * The four business-rule cases from the specification.
     *
     * @return array<string, array{float, string, string, float, OverallDeliveryStatus}>
     */
    public static function businessRuleCases(): array
    {
        return [
            'on time and full' => [1000, '2026-08-26', '2026-08-26', 1000, OverallDeliveryStatus::ON_TIME_FULL],
            'late but full' => [1000, '2026-08-26', '2026-08-28', 1000, OverallDeliveryStatus::LATE_FULL],
            'on time but short' => [1000, '2026-08-26', '2026-08-26', 950, OverallDeliveryStatus::ON_TIME_SHORT],
            'late and short' => [1000, '2026-08-26', '2026-08-28', 950, OverallDeliveryStatus::LATE_SHORT],
        ];
    }

    #[Test]
    #[DataProvider('businessRuleCases')]
    public function it_resolves_the_specified_business_rule_cases(
        float $ordered,
        string $schedule,
        string $actual,
        float $received,
        OverallDeliveryStatus $expected,
    ): void {
        $verdict = $this->calculator->evaluate(
            $ordered,
            $received,
            Carbon::parse($actual),
            Carbon::parse($schedule),
        );

        $this->assertSame($expected, $verdict['overall']);
    }

    #[Test]
    public function it_reports_pending_when_nothing_has_been_received(): void
    {
        $verdict = $this->calculator->evaluate(1000, 0, null, Carbon::parse('2026-08-26'));

        $this->assertSame(QuantityStatus::PENDING, $verdict['quantity']);
        $this->assertSame(TimelinessStatus::PENDING, $verdict['timeliness']);
        $this->assertSame(OverallDeliveryStatus::PENDING, $verdict['overall']);
        $this->assertSame(0, $verdict['days_late']);
    }

    #[Test]
    public function it_flags_over_delivery_regardless_of_punctuality(): void
    {
        $onTime = $this->calculator->evaluate(1000, 1100, Carbon::parse('2026-08-26'), Carbon::parse('2026-08-26'));
        $late = $this->calculator->evaluate(1000, 1100, Carbon::parse('2026-08-30'), Carbon::parse('2026-08-26'));

        $this->assertSame(OverallDeliveryStatus::OVER_DELIVERY, $onTime['overall']);
        $this->assertSame(OverallDeliveryStatus::OVER_DELIVERY, $late['overall']);
        $this->assertSame(QuantityStatus::OVER, $late['quantity']);
    }

    #[Test]
    public function an_over_receipt_inside_the_configured_tolerance_counts_as_full(): void
    {
        $tolerant = new DeliveryStatusCalculator(overTolerancePercent: 5.0);

        $this->assertSame(QuantityStatus::FULL, $tolerant->quantityStatus(1000, 1050));
        $this->assertSame(QuantityStatus::OVER, $tolerant->quantityStatus(1000, 1051));
    }

    #[Test]
    public function arriving_on_the_scheduled_day_is_on_time_whatever_the_clock_says(): void
    {
        $status = $this->calculator->timelinessStatus(
            Carbon::parse('2026-08-26 23:59:00'),
            Carbon::parse('2026-08-26 00:00:00'),
        );

        $this->assertSame(TimelinessStatus::ON_TIME, $status);
    }

    #[Test]
    public function it_counts_whole_days_late_and_never_goes_negative(): void
    {
        $schedule = Carbon::parse('2026-08-26');

        $this->assertSame(2, $this->calculator->daysLate(Carbon::parse('2026-08-28'), $schedule));
        $this->assertSame(0, $this->calculator->daysLate(Carbon::parse('2026-08-20'), $schedule));
        $this->assertSame(0, $this->calculator->daysLate(null, $schedule));
    }

    #[Test]
    public function comparing_dates_does_not_mutate_the_arguments(): void
    {
        $actual = Carbon::parse('2026-08-28 14:30:00');
        $schedule = Carbon::parse('2026-08-26 09:00:00');

        $this->calculator->evaluate(1000, 1000, $actual, $schedule);

        $this->assertSame('2026-08-28 14:30:00', $actual->toDateTimeString());
        $this->assertSame('2026-08-26 09:00:00', $schedule->toDateTimeString());
    }

    #[Test]
    public function decimal_quantities_that_match_exactly_are_full_not_short(): void
    {
        $this->assertSame(QuantityStatus::FULL, $this->calculator->quantityStatus(0.3, 0.1 + 0.2));
    }
}
