<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\Admin\UserService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * F-07 and F-08: the attack surface this application does not use, and the
 * recovery action it depends on.
 *
 * There is no self-service password reset, so an administrator changing a
 * password *is* the compromise-recovery step. It only works if it ends the
 * sessions already signed in as that account - otherwise the attacker keeps
 * their cookie and the reset changes nothing for them.
 */
final class AccountRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private UserService $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->users = app(UserService::class);
    }

    #[Test]
    public function a_password_change_ends_the_accounts_existing_sessions(): void
    {
        $clerk = User::factory()->create(['password' => 'KataSandiLama123']);
        $clerk->assignRole('WAREHOUSE');
        $admin = $this->userWithRole('ADMIN');

        $this->actingAs($clerk);

        /*
         * Driven directly rather than over two HTTP calls: phpunit.xml sets
         * SESSION_DRIVER=array, which does not persist between requests, so
         * the stored hash would simply be written afresh and the guarantee
         * would never be exercised. This builds the state a real second
         * request arrives with - a session carrying the hash from login - and
         * runs the middleware against it.
         */
        $session = app('session.store');
        $session->put('password_hash_web', $clerk->getAuthPassword());

        $this->users->update($clerk, ['password' => 'KataSandiBaru123'], ['WAREHOUSE'], $admin);

        $request = Request::create('/deliveries', 'GET');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn (): ?User => auth()->user());

        $loggedOut = false;

        try {
            app(AuthenticateSession::class)->handle($request, fn () => new Response);
        } catch (AuthenticationException) {
            // What the middleware raises; the handler turns it into the
            // redirect to login a browser actually sees.
            $loggedOut = true;
        }

        // The attacker holding this session is logged out, which is what makes
        // the reset a recovery action rather than a second credential
        // alongside the first.
        $this->assertTrue($loggedOut, 'the stale session should have been rejected');
        $this->assertGuest();
    }

    #[Test]
    public function a_session_whose_password_has_not_changed_survives(): void
    {
        $clerk = User::factory()->create(['password' => 'KataSandiLama123']);
        $clerk->assignRole('WAREHOUSE');

        $this->actingAs($clerk);

        $session = app('session.store');
        $session->put('password_hash_web', $clerk->getAuthPassword());

        $request = Request::create('/deliveries', 'GET');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn (): ?User => auth()->user());

        app(AuthenticateSession::class)->handle($request, fn () => new Response);

        // Nobody is logged out for working normally.
        $this->assertAuthenticatedAs($clerk);
    }

    #[Test]
    public function the_session_guard_is_installed_on_the_web_group(): void
    {
        $kernel = app(Kernel::class);
        $property = (new \ReflectionClass($kernel))->getProperty('middlewareGroups');
        $property->setAccessible(true);

        // Without this a stolen session outlives the password reset meant to
        // revoke it.
        $this->assertContains(AuthenticateSession::class, $property->getValue($kernel)['web'] ?? []);
    }

    #[Test]
    public function retiring_an_account_stops_it_being_used(): void
    {
        $clerk = $this->userWithRole('WAREHOUSE');
        $admin = $this->userWithRole('ADMIN');

        $this->actingAs($clerk)->get(route('deliveries.index'))->assertOk();

        $this->users->retire($clerk, $admin);

        // Soft-deleted and INACTIVE: the provider will not retrieve them again.
        $this->assertSoftDeleted('users', ['id' => $clerk->getKey()]);
        $this->assertNull(User::query()->find($clerk->getKey()));
    }

    #[Test]
    public function a_retired_account_is_refused_at_the_login_screen_by_name(): void
    {
        $clerk = $this->userWithRole('WAREHOUSE');
        $this->users->retire($clerk, $this->userWithRole('ADMIN'));

        $this->post(route('login'), [
            'email' => $clerk->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function a_deactivated_account_cannot_sign_in_even_with_the_right_password(): void
    {
        $user = User::factory()->create(['password' => 'KataSandiBenar123']);
        $user->assignRole('WAREHOUSE');

        // Prove the credentials are right before deactivating, or this test
        // would pass on a wrong password just as happily.
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'KataSandiBenar123',
        ])->assertRedirect();
        $this->post(route('logout'));

        $user->forceFill(['status' => 'INACTIVE'])->save();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'KataSandiBenar123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function an_administrator_can_set_a_new_password_that_works(): void
    {
        $clerk = $this->userWithRole('WAREHOUSE');
        $admin = $this->userWithRole('ADMIN');

        $this->users->update($clerk, ['password' => 'KataSandiBaru123'], ['WAREHOUSE'], $admin);

        // The documented recovery procedure has to actually recover the account.
        $this->post(route('login'), [
            'email' => $clerk->email,
            'password' => 'KataSandiBaru123',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($clerk->fresh());
    }

    #[Test]
    public function the_api_token_surface_is_gone(): void
    {
        // F-07: HasApiTokens with no API routes was capability nobody defended.
        $this->assertFalse(
            trait_exists('Laravel\\Sanctum\\HasApiTokens'),
            'the sanctum package should no longer be installed',
        );

        $this->assertNotContains(
            'Laravel\\Sanctum\\HasApiTokens',
            class_uses_recursive(User::class),
        );
    }

    #[Test]
    public function no_token_route_or_table_remains(): void
    {
        $routes = collect(app('router')->getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'sanctum'))
            ->count();

        $this->assertSame(0, $routes);
        $this->assertFalse(
            Schema::hasTable('personal_access_tokens'),
        );
    }

    #[Test]
    public function the_recovery_procedure_is_written_down(): void
    {
        $rules = (string) file_get_contents(base_path('docs/03-BUSINESS-RULES.md'));

        // A procedure that lives only in somebody's head gets improvised over
        // the phone, which is where social engineering starts.
        $this->assertStringContainsString('Account recovery', $rules);
        $this->assertStringContainsString('Verify identity out of band', $rules);
    }

    #[Test]
    public function the_route_map_no_longer_claims_a_middleware_that_is_not_applied(): void
    {
        $map = (string) file_get_contents(base_path('docs/04-ROUTE-MAP.md'));

        // F-09: nobody should later rely on a guarantee the code does not make.
        $this->assertStringNotContainsString('behind `auth` + `verified`', $map);
        $this->assertStringContainsString('no', substr($map, 0, 600));
    }
}
