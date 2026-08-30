<?php

declare(strict_types=1);

namespace App\Services\Setting;

use App\Enums\SupplierGrade;
use App\Models\KpiSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Cached lookup of KPI thresholds, plus the grade resolver that turns a service
 * rate into a supplier grade using those thresholds.
 */
class KpiSettingService
{
    private const CACHE_KEY = 'kpi_settings.active';

    private const CACHE_TTL = 3600;

    /**
     * @return array<string, KpiSetting>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, static function (): array {
            return KpiSetting::query()
                ->active()
                ->get()
                ->keyBy('code')
                ->all();
        });
    }

    public function find(string $code): ?KpiSetting
    {
        return $this->all()[$code] ?? null;
    }

    public function target(string $code, float $default = 0.0): float
    {
        return $this->find($code)?->target_value ?? $default;
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
            $threshold = $this->target((string) $grade->thresholdCode(), match ($grade) {
                SupplierGrade::EXCELLENT => 98.0,
                SupplierGrade::GOOD => 95.0,
                default => 90.0,
            });

            if ($serviceRate >= $threshold) {
                return $grade;
            }
        }

        return SupplierGrade::POOR;
    }

    /**
     * Threshold payload shared with the frontend so Vue never hard-codes a number.
     *
     * @return array<string, array<string, float|string|null>>
     */
    public function forFrontend(): array
    {
        return array_map(static fn (KpiSetting $s): array => [
            'name' => $s->name,
            'target' => $s->target_value,
            'warning' => $s->warning_value,
            'critical' => $s->critical_value,
            'unit' => $s->unit,
        ], $this->all());
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
