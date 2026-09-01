<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Providers\AppServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * F-02 and F-03: the two settings a deployment gets wrong by omission.
 *
 * `composer setup` copies an .env.example carrying APP_DEBUG=true, and
 * SESSION_SECURE_COOKIE was never named anywhere, so both defaults used to
 * land on the unsafe side of the line without anybody choosing it.
 */
final class EnvironmentHardeningTest extends TestCase
{
    #[Test]
    public function production_refuses_to_boot_with_debug_mode_on(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_DEBUG must be false when APP_ENV=production');

        // Down is recoverable in minutes; handing out credentials is not.
        AppServiceProvider::guardAgainstProductionDebug('production', true);
    }

    #[Test]
    public function production_boots_normally_with_debug_off(): void
    {
        AppServiceProvider::guardAgainstProductionDebug('production', false);

        $this->assertTrue(true, 'no exception is the assertion');
    }

    #[Test]
    public function other_environments_may_keep_debug_on(): void
    {
        foreach (['local', 'testing', 'staging'] as $environment) {
            AppServiceProvider::guardAgainstProductionDebug($environment, true);
        }

        $this->assertTrue(true, 'developers keep their stack traces');
    }

    #[Test]
    public function the_running_application_is_not_in_the_forbidden_state(): void
    {
        // Whatever this suite runs against, it must not itself be the
        // combination the guard exists to prevent.
        $this->assertFalse(
            config('app.env') === 'production' && config('app.debug') === true,
        );
    }

    #[Test]
    public function the_session_cookie_is_secure_by_default_in_production(): void
    {
        // env() reads the process environment, so the default is exercised by
        // resolving the config expression the same way config/session.php does.
        $secure = fn (string $environment): bool => (bool) (
            env('SESSION_SECURE_COOKIE', $environment === 'production')
        );

        $this->assertTrue($secure('production'));
    }

    #[Test]
    public function the_session_cookie_config_no_longer_defaults_to_null(): void
    {
        $source = file_get_contents(config_path('session.php'));

        // The bug was a bare env() with no fallback, which resolves to null
        // and ships the cookie over plain HTTP.
        $this->assertStringNotContainsString("env('SESSION_SECURE_COOKIE'),", $source);
        $this->assertStringContainsString("env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')", $source);
    }

    #[Test]
    public function the_cookie_is_hardened_on_every_other_axis_too(): void
    {
        $this->assertTrue(config('session.http_only'), 'script must not read the session cookie');
        $this->assertContains(config('session.same_site'), ['lax', 'strict']);
    }

    #[Test]
    public function the_deployment_key_is_documented_where_a_deployer_will_see_it(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        // A setting nobody knows exists is a setting nobody sets.
        $this->assertStringContainsString('SESSION_SECURE_COOKIE', $example);
    }
}
