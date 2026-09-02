<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Requests\Report\ReportRequest;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * F-04: exports and downloads as a resource-exhaustion vector.
 *
 * Login was the only throttled route in the application. Every export opens a
 * cursor over the period and streams a spreadsheet with no row cap, and the
 * filter validated date format but never span - so one ordinary user could ask
 * for a century of receipts in a loop.
 */
final class ExportAbuseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        RateLimiter::clear('');
    }

    #[Test]
    public function a_century_wide_export_is_refused(): void
    {
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.export', [
                'type' => 'delivery',
                'format' => 'xlsx',
                'date_from' => '1900-01-01',
                'date_to' => '2100-12-31',
            ]))
            ->assertSessionHasErrors('date_to');
    }

    #[Test]
    public function the_message_tells_the_reader_what_to_do_instead(): void
    {
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.export', [
                'type' => 'delivery',
                'format' => 'xlsx',
                'date_from' => '2020-01-01',
                'date_to' => '2030-01-01',
            ]))
            ->assertSessionHasErrorsIn('default', ['date_to']);

        $errors = session('errors')->get('date_to');

        // The bound and the way out of it, in the application's own language.
        $this->assertStringContainsString('at most', $errors[0]);
        $this->assertStringContainsString('a year at a time', $errors[0]);
    }

    /**
     * The refusal has to be readable, or the reader cannot act on it.
     */
    #[Test]
    public function the_message_reaches_the_reader_in_their_own_language(): void
    {
        $reader = $this->userWithRole('MANAGEMENT');
        $reader->locale = 'id';
        $reader->save();

        $this->actingAs($reader)
            ->get(route('reports.export', [
                'type' => 'delivery',
                'format' => 'xlsx',
                'date_from' => '2020-01-01',
                'date_to' => '2030-01-01',
            ]))
            ->assertSessionHasErrorsIn('default', ['date_to']);

        $this->assertStringContainsString('maksimal', session('errors')->get('date_to')[0]);
    }

    #[Test]
    public function a_two_year_comparison_still_works(): void
    {
        Excel::fake();

        // The width the business actually uses: this year against last.
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.export', [
                'type' => 'delivery',
                'format' => 'xlsx',
                'date_from' => '2025-01-01',
                'date_to' => '2026-12-31',
            ]))
            ->assertOk();
    }

    #[Test]
    public function a_single_month_is_unaffected(): void
    {
        Excel::fake();

        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.export', ['type' => 'problem', 'format' => 'xlsx', 'period' => '2026-08']))
            ->assertOk();
    }

    #[Test]
    public function the_span_limit_holds_on_the_catalogue_screen_too(): void
    {
        // Same request object, so the preview cannot be used to run the query
        // the export refuses.
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.index', ['date_from' => '1900-01-01', 'date_to' => '2100-12-31']))
            ->assertSessionHasErrors('date_to');
    }

    #[Test]
    public function exports_are_rate_limited(): void
    {
        Excel::fake();

        $user = $this->userWithRole('MANAGEMENT');
        $url = route('reports.export', ['type' => 'delivery', 'format' => 'xlsx', 'period' => '2026-08']);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->get($url)->assertOk();
        }

        // The eleventh in a minute is refused rather than served.
        $this->actingAs($user)->get($url)->assertStatus(429);
    }

    #[Test]
    public function attachment_downloads_are_rate_limited(): void
    {
        $limits = collect(app('router')->getRoutes())
            ->first(fn ($route): bool => $route->getName() === 'problem-attachments.download')
            ->gatherMiddleware();

        // 60 a minute is generous for a person and useless for a scraper.
        $this->assertContains('throttle:60,1', $limits);
    }

    #[Test]
    public function the_documented_span_matches_what_the_rule_enforces(): void
    {
        $this->assertSame(731, ReportRequest::MAX_SPAN_DAYS);

        // 731 days is two years including a leap day - the boundary is
        // inclusive, so a full two-year range passes.
        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.index', ['date_from' => '2024-01-01', 'date_to' => '2026-01-01']))
            ->assertOk();
    }
}
