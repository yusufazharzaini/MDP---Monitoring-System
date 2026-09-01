<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Services\Admin\UserService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * F-01: an administrator granting themselves SUPER_ADMIN.
 *
 * The role is not simply "every permission". It passes Gate::before
 * unconditionally, so it also grants permissions that do not exist yet and
 * survives any later trimming of a role in the permission matrix; its own
 * permissions cannot be edited and its last holder cannot be demoted. Anyone
 * holding `user.update` could previously turn their revocable account into an
 * irrevocable superuser by ticking a box on their own profile.
 */
final class PrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    private const SUPER = RolesAndPermissionsSeeder::SUPER_ADMIN;

    private UserService $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->users = app(UserService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Budi Santoso',
            'email' => 'budi@torica.test',
            'password' => 'RahasiaSekali123',
            'password_confirmation' => 'RahasiaSekali123',
            'status' => 'ACTIVE',
            'roles' => ['PURCHASING'],
            ...$overrides,
        ];
    }

    #[Test]
    public function an_administrator_cannot_grant_themselves_super_admin(): void
    {
        $admin = $this->userWithRole('ADMIN');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Hanya super administrator');

        $this->users->update($admin, ['name' => $admin->name], ['ADMIN', self::SUPER], $admin);
    }

    #[Test]
    public function the_escalation_is_refused_through_the_screen_too(): void
    {
        $admin = $this->userWithRole('ADMIN');

        $this->from(route('users.edit', $admin->ulid))
            ->actingAs($admin)
            ->put(route('users.update', $admin->ulid), [
                'name' => $admin->name,
                'email' => $admin->email,
                'status' => 'ACTIVE',
                'roles' => ['ADMIN', self::SUPER],
            ])
            ->assertRedirect(route('users.edit', $admin->ulid))
            ->assertSessionHas('error');

        $this->assertFalse($admin->fresh()->hasRole(self::SUPER));
    }

    #[Test]
    public function an_administrator_cannot_grant_super_admin_to_anybody_else_either(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $accomplice = $this->userWithRole('VIEWER');

        $this->expectException(BusinessRuleException::class);

        // Escalating a second account and signing into it is the same attack
        // with one extra step.
        $this->users->update($accomplice, ['name' => $accomplice->name], [self::SUPER], $admin);
    }

    #[Test]
    public function a_new_account_cannot_be_created_as_super_admin_by_an_administrator(): void
    {
        $admin = $this->userWithRole('ADMIN');

        $this->expectException(BusinessRuleException::class);

        $this->users->create(
            collect($this->payload())->except(['roles', 'password_confirmation'])->all(),
            [self::SUPER],
            $admin,
        );
    }

    #[Test]
    public function a_super_administrator_may_still_appoint_another_one(): void
    {
        $root = $this->userWithRole(self::SUPER);
        $successor = $this->userWithRole('ADMIN');

        $this->users->update($successor, ['name' => $successor->name], ['ADMIN', self::SUPER], $root);

        $this->assertTrue($successor->fresh()->hasRole(self::SUPER));
    }

    #[Test]
    public function a_super_administrator_may_create_one(): void
    {
        $root = $this->userWithRole(self::SUPER);

        $created = $this->users->create(
            collect($this->payload())->except(['roles', 'password_confirmation'])->all(),
            [self::SUPER],
            $root,
        );

        $this->assertTrue($created->hasRole(self::SUPER));
    }

    #[Test]
    public function a_super_administrator_editing_their_own_profile_keeps_the_role(): void
    {
        $root = $this->userWithRole(self::SUPER);

        // Only a *new* grant is guarded; keeping what you already hold is not
        // an escalation, and blocking it would make the profile uneditable.
        $this->users->update($root, ['name' => 'Nama Baru'], [self::SUPER], $root);

        $this->assertTrue($root->fresh()->hasRole(self::SUPER));
        $this->assertSame('Nama Baru', $root->fresh()->name);
    }

    #[Test]
    public function a_service_caller_with_no_actor_cannot_grant_it(): void
    {
        $target = $this->userWithRole('VIEWER');

        // A console command or a queued job is not an authorised grantor.
        $this->expectException(BusinessRuleException::class);

        $this->users->update($target, ['name' => $target->name], [self::SUPER], null);
    }

    #[Test]
    public function the_role_is_not_even_offered_on_screen_to_an_administrator(): void
    {
        $admin = $this->userWithRole('ADMIN');

        $this->actingAs($admin)
            ->get(route('users.edit', $admin->ulid))
            ->assertInertia(function ($page) {
                $roles = array_column($page->toArray()['props']['options']['roles'], 'value');

                $this->assertNotContains(self::SUPER, $roles);
                $this->assertContains('ADMIN', $roles);
            });
    }

    #[Test]
    public function it_is_offered_to_a_super_administrator_editing_somebody_else(): void
    {
        $root = $this->userWithRole(self::SUPER);
        $other = $this->userWithRole('VIEWER');

        $this->actingAs($root)
            ->get(route('users.edit', $other->ulid))
            ->assertInertia(function ($page) {
                $roles = array_column($page->toArray()['props']['options']['roles'], 'value');

                $this->assertContains(self::SUPER, $roles);
            });
    }

    #[Test]
    public function a_super_administrator_is_not_offered_it_on_their_own_profile(): void
    {
        $root = $this->userWithRole(self::SUPER);

        // They already hold it; the control would only ever be a way to grant
        // it to yourself, which the policy refuses.
        $this->actingAs($root)
            ->get(route('users.edit', $root->ulid))
            ->assertInertia(function ($page) {
                $roles = array_column($page->toArray()['props']['options']['roles'], 'value');

                $this->assertNotContains(self::SUPER, $roles);
            });
    }

    #[Test]
    public function the_gate_bypass_does_not_overrule_the_grant_rule(): void
    {
        $root = $this->userWithRole(self::SUPER);

        // Gate::before passes a super administrator everything, so the ability
        // is listed in POLICY_ALONE and the policy decides - including the
        // "never to yourself" half.
        $this->assertFalse(Gate::forUser($root)->allows('assignSuperAdmin', $root));
        $this->assertTrue(Gate::forUser($root)->allows('assignSuperAdmin', $this->userWithRole('VIEWER')));
    }

    #[Test]
    public function an_administrator_never_passes_the_ability(): void
    {
        $admin = $this->userWithRole('ADMIN');

        $this->assertFalse(Gate::forUser($admin)->allows('assignSuperAdmin', $admin));
        $this->assertFalse(Gate::forUser($admin)->allows('assignSuperAdmin', $this->userWithRole('VIEWER')));
    }

    #[Test]
    public function the_original_exploit_no_longer_reproduces(): void
    {
        $admin = User::query()->where('email', 'admin@torica.test')->first()
            ?? $this->userWithRole('ADMIN');

        if (! $admin->hasRole('ADMIN')) {
            $admin->syncRoles(['ADMIN']);
        }

        $before = $admin->getRoleNames()->sort()->values()->all();

        try {
            $this->users->update($admin, ['name' => $admin->name], ['ADMIN', self::SUPER], $admin);
            $this->fail('the escalation reproduced');
        } catch (BusinessRuleException) {
            // Expected.
        }

        $this->assertSame($before, $admin->fresh()->getRoleNames()->sort()->values()->all());
        $this->assertFalse(Gate::forUser($admin->fresh())->allows('anything.at.all'));
    }
}
