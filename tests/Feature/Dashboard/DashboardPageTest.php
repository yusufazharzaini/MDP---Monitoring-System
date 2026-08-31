<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The dashboard screen and its JSON refresh endpoint.
 */
final class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->user = $this->userWithRole('MANAGEMENT');
    }

    #[Test]
    public function guests_cannot_reach_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->getJson(route('dashboard.data'))->assertUnauthorized();
    }

    #[Test]
    public function a_user_without_the_permission_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    #[Test]
    public function the_page_renders_with_its_payload_and_filter_options(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Index')
                ->has('dashboard.summary')
                ->has('dashboard.trend', 6)
                ->has('dashboard.supplier_performance', 5)
                ->has('dashboard.pareto.categories', 5)
                ->has('dashboard.recent_deliveries')
                ->has('dashboard.critical_materials', 7)
                ->has('dashboard.definitions', 3)
                ->has('options.plants')
                ->has('options.suppliers')
                ->has('options.materials')
                ->has('options.materialCategories')
                ->has('generatedAt')
            );
    }

    #[Test]
    public function the_rendered_payload_carries_the_reference_figures(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('dashboard.summary.total_delivery', 1250)
                ->where('dashboard.summary.on_time_delivery', 1210)
                ->where('dashboard.summary.late_delivery', 40)
                ->where('dashboard.summary.short_delivery', 18)
                ->where('dashboard.summary.critical_material', 7)
                ->where('dashboard.summary.service_rate', fn (mixed $v): bool => (float) $v === 96.8)
                ->where('dashboard.summary.target_met', true)
            );
    }

    #[Test]
    public function the_data_endpoint_returns_json_for_a_filter_change(): void
    {
        $supplier = Supplier::query()->where('code', 'SUP-001')->firstOrFail();

        $response = $this->actingAs($this->user)
            ->getJson(route('dashboard.data', ['supplier_id' => $supplier->getKey()]))
            ->assertOk()
            ->assertJsonStructure([
                'dashboard' => [
                    'filters', 'summary', 'trend', 'supplier_performance',
                    'pareto', 'recent_deliveries', 'critical_materials', 'definitions',
                ],
                'generatedAt',
            ]);

        // Supplier A's published figures.
        $this->assertSame(250, $response->json('dashboard.summary.total_delivery'));
        $this->assertSame(246, $response->json('dashboard.summary.on_time_delivery'));
        $this->assertCount(1, $response->json('dashboard.supplier_performance'));
    }

    #[Test]
    public function a_period_filter_narrows_the_payload(): void
    {
        $lastMonth = now()->startOfMonth()->subMonth()->format('Y-m');

        $response = $this->actingAs($this->user)
            ->getJson(route('dashboard.data', ['period' => $lastMonth]))
            ->assertOk();

        $this->assertSame($lastMonth, $response->json('dashboard.filters.period'));
        $this->assertSame(300, $response->json('dashboard.summary.total_delivery'));
    }

    #[Test]
    public function an_unknown_filter_id_is_rejected_rather_than_silently_ignored(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('dashboard.data', ['supplier_id' => 999_999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('supplier_id');
    }

    #[Test]
    public function a_malformed_period_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('dashboard.data', ['period' => 'agustus-2026']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('period');
    }

    #[Test]
    public function a_reversed_date_range_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('dashboard.data', [
                'date_from' => '2026-08-31',
                'date_to' => '2026-08-01',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('date_to');
    }

    #[Test]
    public function every_figure_the_page_renders_is_already_computed_by_the_backend(): void
    {
        $payload = $this->actingAs($this->user)
            ->getJson(route('dashboard.data'))
            ->json('dashboard');

        // Rates, grades, cumulative percentages and status labels all arrive
        // ready to render, so Vue never has to calculate them.
        $this->assertArrayHasKey('service_rate', $payload['summary']);
        $this->assertArrayHasKey('on_time_rate', $payload['summary']);
        $this->assertArrayHasKey('grade_label', $payload['supplier_performance'][0]);
        $this->assertArrayHasKey('grade_variant', $payload['supplier_performance'][0]);
        $this->assertArrayHasKey('cumulative_percentage', $payload['pareto']['categories'][0]);
        $this->assertArrayHasKey('status_label', $payload['recent_deliveries'][0]);
        $this->assertArrayHasKey('status_variant', $payload['recent_deliveries'][0]);
        $this->assertArrayHasKey('risk_label', $payload['critical_materials'][0]);
    }
}
