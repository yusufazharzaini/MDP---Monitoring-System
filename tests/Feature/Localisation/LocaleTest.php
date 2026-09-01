<?php

declare(strict_types=1);

namespace Tests\Feature\Localisation;

use App\Enums\OverallDeliveryStatus;
use App\Enums\ProblemSeverity;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The interface in four languages.
 *
 * What is translated: interface labels and the enumerated vocabulary an
 * operator reads all day. What is not: anything a person typed. A supplier
 * name, a material code and a problem description are the record this system
 * is audited against, and two operators must never be looking at two different
 * versions of the same row.
 */
final class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function locales(): array
    {
        return [
            'Indonesian' => ['id'],
            'Japanese' => ['ja'],
            'Chinese' => ['zh'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function files(): array
    {
        return ['ui' => ['ui'], 'enums' => ['enums']];
    }

    /**
     * A half-finished translation is worse than none: it renders as raw keys or
     * silently falls back, and nobody notices until a user does.
     */
    #[Test]
    #[DataProvider('locales')]
    public function a_locale_defines_every_key_english_defines(string $locale): void
    {
        foreach (array_keys(self::files()) as $file) {
            $english = $this->flatten((array) require base_path("lang/en/{$file}.php"));
            $other = $this->flatten((array) require base_path("lang/{$locale}/{$file}.php"));

            $missing = array_diff(array_keys($english), array_keys($other));
            $extra = array_diff(array_keys($other), array_keys($english));

            $this->assertSame([], array_values($missing), "lang/{$locale}/{$file}.php is missing keys");
            $this->assertSame([], array_values($extra), "lang/{$locale}/{$file}.php defines keys English does not");
        }
    }

    #[Test]
    #[DataProvider('locales')]
    public function no_translated_string_is_left_empty(string $locale): void
    {
        foreach (array_keys(self::files()) as $file) {
            foreach ($this->flatten((array) require base_path("lang/{$locale}/{$file}.php")) as $key => $value) {
                $this->assertNotSame('', trim($value), "{$locale}.{$file}.{$key} is blank");
            }
        }
    }

    /**
     * Every case of every enum, not a sample: a new case added later must fail
     * here rather than reach a dashboard as title-cased English.
     */
    #[Test]
    #[DataProvider('locales')]
    public function every_enum_case_has_a_label_in_this_locale(string $locale): void
    {
        $translations = (array) require base_path("lang/{$locale}/enums.php");

        foreach (glob(app_path('Enums/*.php')) as $file) {
            $enum = 'App\\Enums\\'.basename($file, '.php');

            if (! enum_exists($enum)) {
                continue;
            }

            $name = class_basename($enum);

            foreach ($enum::cases() as $case) {
                $this->assertArrayHasKey($name, $translations, "{$locale}: {$name} has no translations");
                $this->assertArrayHasKey(
                    $case->value,
                    $translations[$name],
                    "{$locale}: {$name}::{$case->value} has no label",
                );
            }
        }
    }

    #[Test]
    public function an_enum_label_is_rendered_in_the_active_language(): void
    {
        App::setLocale('en');
        $this->assertSame('Late - Short', OverallDeliveryStatus::LATE_SHORT->label());

        App::setLocale('id');
        $this->assertSame('Terlambat - Kurang', OverallDeliveryStatus::LATE_SHORT->label());

        App::setLocale('ja');
        $this->assertSame('遅延 - 数量不足', OverallDeliveryStatus::LATE_SHORT->label());

        App::setLocale('zh');
        $this->assertSame('延迟 - 数量不足', OverallDeliveryStatus::LATE_SHORT->label());
    }

    #[Test]
    public function an_untranslated_case_falls_back_to_english_rather_than_showing_a_key(): void
    {
        App::setLocale('id');

        // A locale with no file at all is the worst case; the label must still
        // read as words, not as "enums.ProblemSeverity.HIGH".
        App::setLocale('xx');

        $label = ProblemSeverity::HIGH->label();

        $this->assertSame('High', $label);
        $this->assertStringNotContainsString('enums.', $label);
    }

    #[Test]
    public function the_options_payload_is_translated_too(): void
    {
        App::setLocale('ja');

        $labels = array_column(ProblemSeverity::options(), 'label', 'value');

        $this->assertSame('重大', $labels['CRITICAL']);
    }

    #[Test]
    public function a_signed_in_user_can_change_language_and_it_is_remembered(): void
    {
        $user = $this->userWithRole('ADMIN');

        $this->actingAs($user)
            ->from(route('suppliers.index'))
            ->post(route('locale.update'), ['locale' => 'ja'])
            ->assertRedirect(route('suppliers.index'));

        $this->assertSame('ja', $user->refresh()->locale);
    }

    #[Test]
    public function a_guest_can_change_the_language_before_signing_in(): void
    {
        // Somebody who cannot read the login screen has to be able to fix that
        // without signing in first.
        $this->from(route('login'))
            ->post(route('locale.update'), ['locale' => 'zh'])
            ->assertRedirect(route('login'));

        $this->assertSame('zh', session('locale'));
    }

    #[Test]
    public function an_unsupported_locale_is_refused(): void
    {
        $user = $this->userWithRole('ADMIN');

        $this->actingAs($user)
            ->from(route('suppliers.index'))
            ->post(route('locale.update'), ['locale' => 'xx'])
            ->assertSessionHasErrors('locale');

        $this->assertNull($user->refresh()->locale);
    }

    #[Test]
    public function the_saved_account_choice_follows_the_user_to_a_new_session(): void
    {
        $user = $this->userWithRole('ADMIN');
        $user->locale = 'ja';
        $user->save();

        $this->actingAs($user)
            ->get(route('suppliers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('locale.current', 'ja'));
    }

    #[Test]
    public function the_page_carries_the_strings_for_the_active_language_only(): void
    {
        $user = $this->userWithRole('ADMIN');
        $user->locale = 'id';
        $user->save();

        $this->actingAs($user)
            ->get(route('suppliers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('translations.nav.report', 'Laporan')
                ->where('locale.current', 'id')
                ->has('locale.supported', 4)
            );
    }

    /**
     * The design decision this whole feature rests on.
     */
    #[Test]
    public function business_data_is_never_translated(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'PT Maju Jaya Sentosa']);

        foreach (['en', 'id', 'ja', 'zh'] as $locale) {
            $user = User::factory()->create(['locale' => $locale]);
            $user->syncRoles(['ADMIN']);

            $this->actingAs($user)
                ->get(route('suppliers.index', ['search' => 'Maju Jaya']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('locale.current', $locale)
                    ->where('records.data.0.name', 'PT Maju Jaya Sentosa')
                );
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $flat += $this->flatten($value, $path);

                continue;
            }

            $flat[$path] = (string) $value;
        }

        return $flat;
    }
}
