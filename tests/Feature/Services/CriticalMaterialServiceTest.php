<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\RiskLevel;
use App\Services\Dashboard\CriticalMaterialService;
use App\Services\Setting\SystemSettingService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Support\DemoBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The four configurable critical-material rules of docs/03 section 8.
 */
final class CriticalMaterialServiceTest extends TestCase
{
    use RefreshDatabase;

    private CriticalMaterialService $service;

    private SystemSettingService $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->service = app(CriticalMaterialService::class);
        $this->settings = app(SystemSettingService::class);
    }

    #[Test]
    public function all_rules_enabled_yields_the_reference_count(): void
    {
        $this->assertSame(
            DemoBlueprint::EXPECTED_CRITICAL_MATERIALS,
            $this->service->countCriticalMaterials(DashboardFilter::currentMonth()),
        );
    }

    #[Test]
    public function the_list_and_the_count_always_agree(): void
    {
        $filter = DashboardFilter::currentMonth();

        $this->assertSame(
            $this->service->countCriticalMaterials($filter),
            $this->service->getCriticalMaterials($filter)->count(),
        );
    }

    #[Test]
    public function disabling_a_rule_can_only_narrow_the_list_never_widen_it(): void
    {
        $filter = DashboardFilter::currentMonth();
        $before = $this->service->countCriticalMaterials($filter);

        $this->settings->set(SystemSettingService::CRITICAL_FLAG_LATE, false);

        // The count holds at 7 here, and that is correct: the demo materials
        // that arrive late also run short or carry a critical problem, so no
        // material depends on the late rule alone.
        $this->assertLessThanOrEqual($before, $this->service->countCriticalMaterials($filter));
    }

    #[Test]
    public function a_material_drops_out_once_every_rule_it_trips_is_disabled(): void
    {
        $filter = DashboardFilter::currentMonth();

        // MAT-0004 is not flagged and has no critical problem: it is listed
        // only because it arrives late and runs short.
        $this->assertTrue(
            $this->service->getCriticalMaterials($filter)->contains('material_code', 'MAT-0004'),
        );

        $this->settings->set(SystemSettingService::CRITICAL_FLAG_LATE, false);
        $this->settings->set(SystemSettingService::CRITICAL_FLAG_SHORT, false);

        $remaining = $this->service->getCriticalMaterials($filter);

        $this->assertFalse($remaining->contains('material_code', 'MAT-0004'));
        $this->assertSame(6, $remaining->count(), 'The four flagged plus the critical-problem materials remain.');
    }

    #[Test]
    public function disabling_every_rule_empties_the_list(): void
    {
        foreach ([
            SystemSettingService::CRITICAL_FLAG_IS_CRITICAL,
            SystemSettingService::CRITICAL_FLAG_LATE,
            SystemSettingService::CRITICAL_FLAG_SHORT,
            SystemSettingService::CRITICAL_FLAG_CRITICAL_PROBLEM,
        ] as $rule) {
            $this->settings->set($rule, false);
        }

        $this->assertSame(0, $this->service->countCriticalMaterials(DashboardFilter::currentMonth()));
        $this->assertTrue($this->service->getCriticalMaterials(DashboardFilter::currentMonth())->isEmpty());
    }

    #[Test]
    public function only_the_flag_rule_returns_exactly_the_flagged_materials(): void
    {
        foreach ([
            SystemSettingService::CRITICAL_FLAG_LATE,
            SystemSettingService::CRITICAL_FLAG_SHORT,
            SystemSettingService::CRITICAL_FLAG_CRITICAL_PROBLEM,
        ] as $rule) {
            $this->settings->set($rule, false);
        }

        $codes = $this->service->getCriticalMaterials(DashboardFilter::currentMonth())
            ->pluck('material_code')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(DemoBlueprint::CRITICAL_FLAGGED_MATERIALS, $codes);
    }

    #[Test]
    public function every_listed_material_states_why_it_is_listed(): void
    {
        foreach ($this->service->getCriticalMaterials(DashboardFilter::currentMonth()) as $material) {
            $this->assertNotEmpty(
                $material['reasons'],
                "{$material['material_code']} is listed with no reason given.",
            );
        }
    }

    #[Test]
    public function the_list_is_ordered_worst_risk_first(): void
    {
        $scores = $this->service->getCriticalMaterials(DashboardFilter::currentMonth())
            ->pluck('risk_score')
            ->all();

        $sorted = $scores;
        rsort($sorted);

        $this->assertSame($sorted, $scores);
    }

    #[Test]
    public function a_critical_problem_outranks_a_shortfall_which_outranks_lateness(): void
    {
        $base = ['late_count' => 0, 'short_count' => 0, 'critical_problem_count' => 0, 'shortage_quantity' => 0.0, 'is_flagged' => false];

        $lateOnly = $this->service->calculateRiskLevel([...$base, 'late_count' => 5]);
        $shortOnly = $this->service->calculateRiskLevel([...$base, 'short_count' => 1]);
        $criticalProblem = $this->service->calculateRiskLevel([...$base, 'critical_problem_count' => 1]);

        $this->assertSame(RiskLevel::LOW, $lateOnly);
        $this->assertSame(RiskLevel::MEDIUM, $shortOnly);
        $this->assertSame(RiskLevel::HIGH, $criticalProblem);

        $this->assertSame(RiskLevel::CRITICAL, $this->service->calculateRiskLevel([
            ...$base, 'critical_problem_count' => 1, 'short_count' => 1,
        ]));
    }

    #[Test]
    public function a_period_with_no_activity_lists_nothing(): void
    {
        $filter = DashboardFilter::fromArray([
            'date_from' => '2000-01-01',
            'date_to' => '2000-01-31',
        ]);

        $this->assertSame(0, $this->service->countCriticalMaterials($filter));
    }
}
