<?php

declare(strict_types=1);

namespace App\Services\Setting;

use App\DataTransferObjects\KpiThreshold;
use App\Enums\SupplierGrade;
use App\Models\KpiSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Cached lookup of KPI thresholds, plus the grade resolver that turns a service
 * rate into a supplier grade using those thresholds.
 *
 * The cache holds plain arrays, never Eloquent models: a serialised model in
 * the cache breaks as soon as the model or the autoloader shifts under it.
 */
class KpiSettingService
{
    private const CACHE_KEY = 'kpi_settings.active';

    private const CACHE_TTL = 3600;

    /**
     * Fallback bands, used only when kpi_settings has not been seeded yet.
     */
    private const GRADE_FALLBACKS = [
        SupplierGrade::EXCELLENT->value => 98.0,
        SupplierGrade::GOOD->value => 95.0,
        SupplierGrade::AVERAGE->value => 90.0,
    ];

    /**
     * @return array<string, KpiThreshold>
     */
    public function all(): array
    {
        return array_map(
            static fn (array $row): KpiThreshold => KpiThreshold::fromArray($row),
            $this->cachedRows(),
        );
    }

    public function find(string $code): ?KpiThreshold
    {
        $rows = $this->cachedRows();

        return isset($rows[$code]) ? KpiThreshold::fromArray($rows[$code]) : null;
    }

    public function target(string $code, float $default = 0.0): float
    {
        return $this->find($code)?->target ?? $default;
    }

    /**
     * The service-rate target rendered as the dashboard's target line.
     */
    public function serviceRateTarget(): float
    {
        return $this->target(KpiSetting::SERVICE_RATE, 95.0);
    }

    /**
     * Grade bands come from kpi_settings so the business can retune them
     * without a deploy (assumption A7).
     */
    public function gradeFor(float $serviceRate): SupplierGrade
    {
        foreach ([SupplierGrade::EXCELLENT, SupplierGrade::GOOD, SupplierGrade::AVERAGE] as $grade) {
            if ($serviceRate >= $this->gradeFloor($grade)) {
                return $grade;
            }
        }

        return SupplierGrade::POOR;
    }

    /**
     * The service rate at which a grade starts.
     *
     * The grader and the legend that explains it must read this same method:
     * defaulting a missing threshold to 0 in one place and to 98 in the other
     * put "Excellent >= 0%" on screen while grading still required 98.
     */
    public function gradeFloor(SupplierGrade $grade): float
    {
        return $this->target(
            (string) $grade->thresholdCode(),
            self::GRADE_FALLBACKS[$grade->value] ?? 0.0,
        );
    }

    /**
     * Threshold payload shared with the frontend so Vue never hard-codes a number.
     *
     * @return array<string, array<string, mixed>>
     */
    public function forFrontend(): array
    {
        return array_map(
            static fn (KpiThreshold $threshold): array => $threshold->toArray(),
            $this->all(),
        );
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function cachedRows(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, static function (): array {
            return KpiSetting::query()
                ->active()
                ->get()
                ->mapWithKeys(static fn (KpiSetting $s): array => [$s->code => [
                    'code' => $s->code,
                    'name' => $s->name,
                    'target' => (float) $s->target_value,
                    'warning' => $s->warning_value === null ? null : (float) $s->warning_value,
                    'critical' => $s->critical_value === null ? null : (float) $s->critical_value,
                    'unit' => $s->unit,
                    'description' => $s->description,
                ]])
                ->all();
        });
    }
}
