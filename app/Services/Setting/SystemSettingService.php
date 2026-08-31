<?php

declare(strict_types=1);

namespace App\Services\Setting;

use App\Enums\SettingType;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Typed, cached access to the system_settings table.
 *
 * Settings are read on nearly every dashboard request, so the whole table is
 * cached as one key and busted on write.
 */
class SystemSettingService
{
    private const CACHE_KEY = 'system_settings.all';

    private const CACHE_TTL = 3600;

    // Service rate
    public const SERVICE_RATE_FORMULA = 'service_rate.formula';

    public const SERVICE_RATE_WEIGHT_ON_TIME = 'service_rate.weight_on_time';

    public const SERVICE_RATE_WEIGHT_QUANTITY = 'service_rate.weight_quantity';

    // Delivery
    public const DELIVERY_OVER_TOLERANCE_PERCENT = 'delivery.over_tolerance_percent';

    // Critical material rules
    public const CRITICAL_FLAG_IS_CRITICAL = 'critical_material.flag_is_critical';

    public const CRITICAL_FLAG_LATE = 'critical_material.flag_late';

    public const CRITICAL_FLAG_SHORT = 'critical_material.flag_short';

    public const CRITICAL_FLAG_CRITICAL_PROBLEM = 'critical_material.flag_critical_problem';

    // Purchase order
    public const PO_REQUIRE_SEPARATE_APPROVER = 'purchase_order.require_separate_approver';

    // Import
    public const IMPORT_AUTO_CREATE_MASTER = 'import.auto_create_master';

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        return $value === null ? $default : (bool) $value;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->get($key);

        return $value === null ? $default : (float) $value;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key);

        return $value === null ? $default : (string) $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, static function (): array {
            return SystemSetting::query()
                ->get()
                ->mapWithKeys(static fn (SystemSetting $s): array => [$s->setting_key => $s->typedValue()])
                ->all();
        });
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function grouped(): array
    {
        return SystemSetting::query()
            ->orderBy('group')
            ->orderBy('setting_key')
            ->get()
            ->groupBy('group')
            ->map(static fn ($rows) => $rows->map(static fn (SystemSetting $s): array => [
                'key' => $s->setting_key,
                'value' => $s->typedValue(),
                'type' => $s->type->value,
                'description' => $s->description,
            ])->values()->all())
            ->all();
    }

    public function set(string $key, mixed $value): SystemSetting
    {
        $setting = SystemSetting::query()->firstOrNew(['setting_key' => $key]);
        $setting->type ??= SettingType::STRING;
        $setting->setting_value = $setting->type->serialize($value);
        $setting->save();

        $this->flush();

        return $setting;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
