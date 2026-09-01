<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * F-06: the application's unescaped-output surface, kept at zero.
 *
 * Pagination rendered its link labels with v-html so Laravel's `&laquo;`
 * entities became arrows. Framework data today, stored XSS the day anyone puts
 * a filter summary or a search term into a label. These tests assert the
 * absence of the sink rather than the safety of today's data.
 */
final class NoUnescapedOutputTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    private function filesUnder(string $directory, string $extension): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), $extension)) {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }

    #[Test]
    public function no_vue_component_uses_v_html(): void
    {
        $offenders = [];

        foreach ($this->filesUnder(resource_path('js'), '.vue') as $file) {
            // The attribute, not the word: the comments explaining its
            // absence mention it by name.
            if (preg_match('/\sv-html\s*=/', (string) file_get_contents($file)) === 1) {
                $offenders[] = str_replace(base_path().'/', '', $file);
            }
        }

        $this->assertSame([], $offenders, 'v-html renders its input as markup');
    }

    #[Test]
    public function no_blade_template_uses_raw_echo(): void
    {
        $offenders = [];

        foreach ($this->filesUnder(resource_path('views'), '.blade.php') as $file) {
            if (str_contains((string) file_get_contents($file), '{!!')) {
                $offenders[] = str_replace(base_path().'/', '', $file);
            }
        }

        // The PDF and print document renders report rows; every cell must be
        // escaped, because a supplier name is user input.
        $this->assertSame([], $offenders);
    }

    #[Test]
    public function a_hostile_supplier_name_is_escaped_in_the_printed_report(): void
    {
        $this->seedReferenceData();

        $supplier = Supplier::factory()->create([
            'name' => '<script>alert(1)</script>',
        ]);

        $response = $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('reports.export', [
                'type' => 'supplier-performance',
                'format' => 'print',
                'period' => now()->format('Y-m'),
            ]));

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $this->assertNotNull($supplier);
    }

    #[Test]
    public function pagination_still_renders_its_arrows(): void
    {
        $component = (string) file_get_contents(resource_path('js/Components/Pagination.vue'));

        // Decoded in the script rather than injected as markup.
        $this->assertStringContainsString('&laquo;', $component);
        $this->assertStringContainsString('label(link.label)', $component);
        $this->assertDoesNotMatchRegularExpression('/\sv-html\s*=/', $component);
    }
}
