<?php

declare(strict_types=1);

namespace Tests\Feature\Setting;

use App\Services\Setting\KpiSettingService;
use App\Services\Setting\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Settings caching, against a store that actually persists.
 *
 * Every KPI threshold, the service-rate formula, the over-delivery tolerance
 * and the four critical-material rules are read through these two services,
 * which cache for an hour and flush on write. A missed flush is the worst
 * failure this system has: the dashboard keeps publishing confident numbers
 * computed from the old rules and nothing looks broken.
 *
 * phpunit.xml sets CACHE_STORE=array, so the cache dies with each process and a
 * stale read cannot surface. These tests switch to the database store, where a
 * value written once really does stay written, so the invalidation is proved
 * rather than assumed.
 */
final class SettingsCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();

        // A real store, not the per-process array driver.
        config(['cache.default' => 'database']);
        Cache::store('database')->clear();

        app(SystemSettingService::class)->flush();
        app(KpiSettingService::class)->flush();
    }

    private function settings(): SystemSettingService
    {
        return app(SystemSettingService::class);
    }

    private function kpi(): KpiSettingService
    {
        return app(KpiSettingService::class);
    }

    #[Test]
    public function a_written_setting_is_visible_to_the_very_next_read(): void
    {
        $this->settings()->get(SystemSettingService::DELIVERY_OVER_TOLERANCE_PERCENT);

        $this->settings()->set(SystemSettingService::DELIVERY_OVER_TOLERANCE_PERCENT, 5.5);

        $this->assertSame(5.5, $this->settings()->float(SystemSettingService::DELIVERY_OVER_TOLERANCE_PERCENT));
    }

    #[Test]
    public function a_write_invalidates_the_cache_rather_than_waiting_for_the_ttl(): void
    {
        // Warm it, so a missing flush would leave the old value in place.
        $this->settings()->all();

        $this->settings()->set(SystemSettingService::SERVICE_RATE_FORMULA, 'weighted');

        $this->assertSame('weighted', $this->settings()->string(SystemSettingService::SERVICE_RATE_FORMULA));
    }

    #[Test]
    public function a_change_made_outside_the_service_is_not_seen_until_a_flush(): void
    {
        $this->settings()->all();

        // A migration or a manual fix that writes the table directly.
        DB::table('system_settings')
            ->where('setting_key', SystemSettingService::DELIVERY_OVER_TOLERANCE_PERCENT)
            ->update(['setting_value' => '9.9']);

        // Documented, not lamented: the cache is authoritative for an hour, so
        // anything bypassing set() must flush. This is the contract callers
        // need to know about.
        $this->assertNotSame(9.9, $this->settings()->float(SystemSettingService::DELIVERY_OVER_TOLERANCE_PERCENT));

        $this->settings()->flush();

        $this->assertSame(9.9, $this->settings()->float(SystemSettingService::DELIVERY_OVER_TOLERANCE_PERCENT));
    }

    #[Test]
    public function setting_many_at_once_leaves_every_one_readable(): void
    {
        $this->settings()->all();

        $this->settings()->setMany([
            SystemSettingService::SERVICE_RATE_FORMULA => 'weighted',
            SystemSettingService::SERVICE_RATE_WEIGHT_ON_TIME => 0.7,
            SystemSettingService::SERVICE_RATE_WEIGHT_QUANTITY => 0.3,
        ]);

        $this->assertSame('weighted', $this->settings()->string(SystemSettingService::SERVICE_RATE_FORMULA));
        $this->assertSame(0.7, $this->settings()->float(SystemSettingService::SERVICE_RATE_WEIGHT_ON_TIME));
        $this->assertSame(0.3, $this->settings()->float(SystemSettingService::SERVICE_RATE_WEIGHT_QUANTITY));
    }

    #[Test]
    public function typed_access_coerces_and_falls_back_to_the_documented_default(): void
    {
        $this->settings()->set('test.flag', true);
        $this->settings()->set('test.number', 12.5);
        $this->settings()->set('test.text', 'halo');

        $this->assertTrue($this->settings()->bool('test.flag'));
        $this->assertSame(12.5, $this->settings()->float('test.number'));
        $this->assertSame('halo', $this->settings()->string('test.text'));

        // A key nobody has ever written returns what the caller asked for.
        $this->assertTrue($this->settings()->bool('test.missing', true));
        $this->assertSame(4.2, $this->settings()->float('test.missing', 4.2));
        $this->assertSame('fallback', $this->settings()->string('test.missing', 'fallback'));
    }

    #[Test]
    public function a_kpi_threshold_change_is_visible_after_its_flush(): void
    {
        $this->assertSame(95.0, $this->kpi()->serviceRateTarget());

        DB::table('kpi_settings')->where('code', 'SERVICE_RATE')->update(['target_value' => 97]);
        $this->kpi()->flush();

        $this->assertSame(97.0, $this->kpi()->serviceRateTarget());
    }

    #[Test]
    public function the_cached_kpi_payload_survives_a_round_trip_as_plain_data(): void
    {
        $this->kpi()->all();

        // Caching Eloquent models here once produced __PHP_Incomplete_Class on
        // the way back out; the payload must be plain arrays and DTOs.
        $again = $this->kpi()->all();

        $this->assertNotEmpty($again);
        foreach ($again as $threshold) {
            $this->assertIsFloat($threshold->target);
        }
    }

    #[Test]
    public function the_frontend_payload_carries_every_threshold_as_data(): void
    {
        $payload = $this->kpi()->forFrontend();

        // Requirement 31: Vue never hard-codes a KPI number, so every one it
        // could need has to be in here.
        $this->assertArrayHasKey('SERVICE_RATE', $payload);
        $this->assertArrayHasKey('GRADE_EXCELLENT', $payload);
        $this->assertIsArray($payload['SERVICE_RATE']);
        $this->assertArrayHasKey('target', $payload['SERVICE_RATE']);
    }

    #[Test]
    public function retuning_a_grade_band_regrades_the_next_read(): void
    {
        $this->assertSame('EXCELLENT', $this->kpi()->gradeFor(98.5)->value);

        DB::table('kpi_settings')->where('code', 'GRADE_EXCELLENT')->update(['target_value' => 99]);
        $this->kpi()->flush();

        $this->assertSame('GOOD', $this->kpi()->gradeFor(98.5)->value);
    }

    #[Test]
    public function the_two_caches_are_independent(): void
    {
        $this->settings()->all();
        $this->kpi()->all();

        // Flushing one must not silently drop the other, or a settings save
        // would quietly cost every KPI lookup a rebuild.
        $this->settings()->set(SystemSettingService::SERVICE_RATE_FORMULA, 'weighted');

        $this->assertTrue(Cache::has('kpi_settings.active'));
    }
}
