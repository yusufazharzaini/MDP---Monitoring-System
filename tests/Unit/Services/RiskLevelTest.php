<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\RiskLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RiskLevelTest extends TestCase
{
    /**
     * @return array<string, array{int, RiskLevel}>
     */
    public static function scores(): array
    {
        return [
            'no signal' => [0, RiskLevel::LOW],
            'flagged only' => [1, RiskLevel::LOW],
            'late only' => [1, RiskLevel::LOW],
            'shortfall' => [2, RiskLevel::MEDIUM],
            'shortfall and late' => [3, RiskLevel::MEDIUM],
            'critical problem' => [4, RiskLevel::HIGH],
            'critical problem and late' => [5, RiskLevel::HIGH],
            'critical problem and shortfall' => [6, RiskLevel::CRITICAL],
            'everything at once' => [8, RiskLevel::CRITICAL],
        ];
    }

    #[Test]
    #[DataProvider('scores')]
    public function a_risk_score_bands_into_a_level(int $score, RiskLevel $expected): void
    {
        $this->assertSame($expected, RiskLevel::fromScore($score));
    }

    #[Test]
    public function the_bands_are_monotonic(): void
    {
        $previous = 0;

        for ($score = 0; $score <= 10; $score++) {
            $weight = RiskLevel::fromScore($score)->weight();
            $this->assertGreaterThanOrEqual($previous, $weight, "Risk fell as the score rose at {$score}.");
            $previous = $weight;
        }
    }
}
