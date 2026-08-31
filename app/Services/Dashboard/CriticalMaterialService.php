<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\RiskLevel;
use App\Repositories\DashboardRepository;
use App\Services\Setting\SystemSettingService;
use Illuminate\Support\Collection;

/**
 * Which materials the business is exposed on this period.
 *
 * Four rules, each independently switchable in System Settings (docs/03 §8):
 *
 *   1. the material is flagged is_critical and saw activity
 *   2. it arrived late at least once
 *   3. an order line for it is still short
 *   4. it has a CRITICAL severity problem
 *
 * A material counts if it trips *any* enabled rule. Each rule is one grouped
 * query returning at most one row per material, so the cost is bounded by the
 * material catalogue, not by the number of deliveries.
 */
class CriticalMaterialService
{
    public function __construct(
        private readonly DashboardRepository $repository,
        private readonly SystemSettingService $settings,
    ) {}

    /**
     * The critical materials for the period, worst risk first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getCriticalMaterials(DashboardFilter $filter): Collection
    {
        $signals = $this->gatherSignals($filter);

        if ($signals === []) {
            return collect();
        }

        $details = $this->repository->materialDetails(array_keys($signals))->keyBy('id');

        return collect($signals)
            ->map(function (array $signal, int $materialId) use ($details): ?array {
                $material = $details->get($materialId);

                if ($material === null) {
                    return null;
                }

                $risk = $this->calculateRiskLevel($signal);

                return [
                    'material_id' => $materialId,
                    'material_ulid' => $material->ulid,
                    'material_code' => $material->code,
                    'material_name' => $material->name,
                    'category' => $material->category_name,
                    'uom' => $material->uom_code,
                    'is_flagged_critical' => (bool) $material->is_critical,
                    'late_count' => $signal['late_count'],
                    'short_count' => $signal['short_count'],
                    'shortage_quantity' => round($signal['shortage_quantity'], 4),
                    'critical_problem_count' => $signal['critical_problem_count'],
                    'reasons' => $this->reasonsFor($signal, (bool) $material->is_critical),
                    'risk_level' => $risk->value,
                    'risk_label' => $risk->label(),
                    'risk_variant' => $risk->badgeVariant(),
                    'risk_score' => $this->riskScore($signal),
                ];
            })
            ->filter()
            ->sortBy([
                fn (array $a, array $b): int => $b['risk_score'] <=> $a['risk_score'],
                fn (array $a, array $b): int => $a['material_code'] <=> $b['material_code'],
            ])
            ->values();
    }

    /**
     * How many distinct materials are critical - the KPI card.
     *
     * Counts the signals directly rather than building the full list, because
     * the card only needs the number.
     */
    public function countCriticalMaterials(DashboardFilter $filter): int
    {
        return count($this->gatherSignals($filter));
    }

    /**
     * Band a material's signals into a risk level.
     *
     * The weighting reflects what actually hurts production: a CRITICAL problem
     * outranks a shortfall, which outranks lateness, and being flagged critical
     * raises everything because there is no buffer stock to absorb it.
     *
     * @param  array{late_count: int, short_count: int, critical_problem_count: int, shortage_quantity: float, is_flagged: bool}  $signal
     */
    public function calculateRiskLevel(array $signal): RiskLevel
    {
        return RiskLevel::fromScore($this->riskScore($signal));
    }

    /**
     * Gather each enabled rule's hits, keyed by material id.
     *
     * @return array<int, array{late_count: int, short_count: int, critical_problem_count: int, shortage_quantity: float, is_flagged: bool}>
     */
    private function gatherSignals(DashboardFilter $filter): array
    {
        $signals = [];

        if ($this->settings->bool(SystemSettingService::CRITICAL_FLAG_IS_CRITICAL, true)) {
            foreach ($this->repository->flaggedCriticalMaterials($filter) as $row) {
                $signals = $this->record($signals, (int) $row->material_id, ['is_flagged' => true]);
            }
        }

        if ($this->settings->bool(SystemSettingService::CRITICAL_FLAG_LATE, true)) {
            foreach ($this->repository->materialsWithLateReceipts($filter) as $row) {
                $signals = $this->record($signals, (int) $row->material_id, [
                    'late_count' => (int) $row->late_count,
                ]);
            }
        }

        if ($this->settings->bool(SystemSettingService::CRITICAL_FLAG_SHORT, true)) {
            foreach ($this->repository->materialsWithShortfall($filter) as $row) {
                $signals = $this->record($signals, (int) $row->material_id, [
                    'short_count' => (int) $row->short_count,
                    'shortage_quantity' => (float) $row->shortage_quantity,
                ]);
            }
        }

        if ($this->settings->bool(SystemSettingService::CRITICAL_FLAG_CRITICAL_PROBLEM, true)) {
            foreach ($this->repository->materialsWithCriticalProblems($filter) as $row) {
                $signals = $this->record($signals, (int) $row->material_id, [
                    'critical_problem_count' => (int) $row->problem_count,
                ]);
            }
        }

        return $signals;
    }

    /**
     * Merge one rule's hit into the signal map, creating the entry if this is
     * the first rule that material has tripped.
     *
     * @param  array<int, array<string, mixed>>  $signals
     * @param  array<string, mixed>  $values
     * @return array<int, array<string, mixed>>
     */
    private function record(array $signals, int $materialId, array $values): array
    {
        $signals[$materialId] = [
            ...($signals[$materialId] ?? self::emptySignal()),
            ...$values,
        ];

        return $signals;
    }

    /**
     * @return array{late_count: int, short_count: int, critical_problem_count: int, shortage_quantity: float, is_flagged: bool}
     */
    private static function emptySignal(): array
    {
        return [
            'late_count' => 0,
            'short_count' => 0,
            'critical_problem_count' => 0,
            'shortage_quantity' => 0.0,
            'is_flagged' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $signal
     */
    private function riskScore(array $signal): int
    {
        $score = 0;
        $score += $signal['critical_problem_count'] > 0 ? 4 : 0;
        $score += $signal['short_count'] > 0 ? 2 : 0;
        $score += $signal['late_count'] > 0 ? 1 : 0;
        $score += $signal['is_flagged'] ? 1 : 0;

        return $score;
    }

    /**
     * @param  array<string, mixed>  $signal
     * @return array<int, string>
     */
    private function reasonsFor(array $signal, bool $isFlagged): array
    {
        $reasons = [];

        if ($isFlagged) {
            $reasons[] = 'Material ditandai critical';
        }

        if ($signal['late_count'] > 0) {
            $reasons[] = $signal['late_count'].'x delivery terlambat';
        }

        if ($signal['short_count'] > 0) {
            $reasons[] = $signal['short_count'].'x quantity kurang';
        }

        if ($signal['critical_problem_count'] > 0) {
            $reasons[] = $signal['critical_problem_count'].'x problem severity CRITICAL';
        }

        return $reasons;
    }
}
