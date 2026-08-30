<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        RateLimiter::clear('test@torica.test|127.0.0.1');
    }

    #[Test]
    public function the_login_screen_renders_for_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    #[Test]
    public function a_user_can_sign_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@torica.test',
            'password' => 'secret-password',
        ]);

        $this->post(route('login'), [
            'email' => 'test@torica.test',
            'password' => 'secret-password',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function signing_in_is_recorded_in_the_audit_trail(): void
    {
        User::factory()->create([
            'email' => 'test@torica.test',
            'password' => 'secret-password',
        ]);

        $this->post(route('login'), [
            'email' => 'test@torica.test',
            'password' => 'secret-password',
        ]);

        $this->assertDatabaseHas(AuditLog::class, [
            'action' => AuditAction::LOGIN->value,
            'module' => 'Authentication',
        ]);
    }

    #[Test]
    public function a_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'test@torica.test', 'password' => 'secret-password']);

        $this->post(route('login'), [
            'email' => 'test@torica.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function a_deactivated_account_cannot_sign_in(): void
    {
        User::factory()->create([
            'email' => 'test@torica.test',
            'password' => 'secret-password',
            'status' => RecordStatus::INACTIVE,
        ]);

        $this->post(route('login'), [
            'email' => 'test@torica.test',
            'password' => 'secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function repeated_failures_are_throttled(): void
    {
        User::factory()->create(['email' => 'test@torica.test', 'password' => 'secret-password']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login'), ['email' => 'test@torica.test', 'password' => 'nope']);
        }

        $response = $this->post(route('login'), [
            'email' => 'test@torica.test',
            'password' => 'secret-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    #[Test]
    public function a_user_can_sign_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    #[Test]
    public function the_workspace_shares_the_signed_in_user_and_kpi_thresholds(): void
    {
        $user = $this->userWithRole('MANAGEMENT');

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->where('auth.user.email', $user->email)
                ->where('auth.user.roles', ['MANAGEMENT'])
                ->has('kpi.SERVICE_RATE')
                // json_encode drops the zero fraction, so compare numerically.
                ->where('kpi.SERVICE_RATE.target', fn (mixed $target): bool => (float) $target === 95.0)
            );
    }
}
