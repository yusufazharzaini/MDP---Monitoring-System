<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The administration screens and the permissions that gate them - Phase 9's
 * exit criterion.
 *
 * `user.*` opens user and role administration; `audit.view` is separate,
 * because reading the trail and changing who can do what are different jobs.
 */
final class AdminScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
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
            'status' => RecordStatus::ACTIVE->value,
            'roles' => ['PURCHASING'],
            ...$overrides,
        ];
    }

    #[Test]
    public function the_user_list_is_gated_by_user_view(): void
    {
        $this->actingAs($this->userWithPermissions(['dashboard.view']))
            ->get(route('users.index'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('ADMIN'))
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Users/Index')
                ->has('records.data')
                ->has('options.roles')
                ->where('can.create', true));
    }

    #[Test]
    public function a_user_who_may_read_cannot_create(): void
    {
        $reader = $this->userWithPermissions(['user.view']);

        $this->actingAs($reader)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.create', false));

        $this->actingAs($reader)->get(route('users.create'))->assertForbidden();
        $this->actingAs($reader)->post(route('users.store'), $this->payload())->assertForbidden();
    }

    #[Test]
    public function creating_a_user_through_the_screen_assigns_their_role(): void
    {
        $this->actingAs($this->userWithRole('ADMIN'))
            ->post(route('users.store'), $this->payload())
            ->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'budi@torica.test')->firstOrFail();

        $this->assertTrue($user->hasRole('PURCHASING'));
    }

    #[Test]
    public function a_user_must_be_given_at_least_one_role(): void
    {
        $this->actingAs($this->userWithRole('ADMIN'))
            ->post(route('users.store'), $this->payload(['roles' => []]))
            ->assertSessionHasErrors('roles');

        $this->assertDatabaseMissing('users', ['email' => 'budi@torica.test']);
    }

    #[Test]
    public function a_weak_password_is_refused(): void
    {
        $this->actingAs($this->userWithRole('ADMIN'))
            ->post(route('users.store'), $this->payload([
                'password' => 'abc',
                'password_confirmation' => 'abc',
            ]))
            ->assertSessionHasErrors('password');
    }

    #[Test]
    public function an_email_already_used_by_a_retired_account_is_refused(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $existing = User::factory()->create(['email' => 'budi@torica.test']);
        $existing->assignRole('VIEWER');

        $this->actingAs($admin)->delete(route('users.destroy', $existing->ulid))->assertRedirect();

        // Reusing it would merge two people's histories under one row.
        $this->actingAs($admin)
            ->post(route('users.store'), $this->payload())
            ->assertSessionHasErrors('email');
    }

    #[Test]
    public function retiring_and_restoring_a_user_works_through_the_screen(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $user = $this->userWithRole('WAREHOUSE');

        $this->actingAs($admin)->delete(route('users.destroy', $user->ulid))->assertRedirect();
        $this->assertSoftDeleted('users', ['id' => $user->getKey()]);

        $this->actingAs($admin)
            ->post(route('users.restore', $user->ulid))
            ->assertRedirect();

        $this->assertNull(User::query()->findOrFail($user->getKey())->deleted_at);
    }

    #[Test]
    public function the_screen_never_offers_an_administrator_the_button_to_retire_themselves(): void
    {
        $admin = $this->userWithRole('ADMIN');

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertInertia(function ($page) use ($admin) {
                $self = collect($page->toArray()['props']['records']['data'])
                    ->firstWhere('id', $admin->getKey());

                $this->assertTrue($self['is_self']);
                $this->assertFalse($self['can']['delete']);
            });

        $this->actingAs($admin)
            ->delete(route('users.destroy', $admin->ulid))
            ->assertForbidden();
    }

    #[Test]
    public function a_super_administrator_is_also_refused_retiring_themselves(): void
    {
        $root = $this->userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

        // The gate bypass grants every permission, but "this is you" is not a
        // permission question.
        $this->actingAs($root)
            ->delete(route('users.destroy', $root->ulid))
            ->assertForbidden();

        $this->assertNull($root->refresh()->deleted_at);
    }

    #[Test]
    public function demoting_the_last_super_administrator_is_refused_by_the_screen(): void
    {
        $root = $this->userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
        $admin = $this->userWithRole('ADMIN');

        $this->from(route('users.edit', $root->ulid))
            ->actingAs($admin)
            ->put(route('users.update', $root->ulid), [
                'name' => $root->name,
                'email' => $root->email,
                'status' => RecordStatus::ACTIVE->value,
                'roles' => ['VIEWER'],
            ])
            ->assertRedirect(route('users.edit', $root->ulid))
            ->assertSessionHas('error');

        $this->assertTrue($root->refresh()->hasRole(RolesAndPermissionsSeeder::SUPER_ADMIN));
    }

    #[Test]
    public function the_role_matrix_lists_every_role_with_its_permissions(): void
    {
        $this->actingAs($this->userWithRole('ADMIN'))
            ->get(route('roles.index'))
            ->assertOk()
            ->assertInertia(function ($page) {
                $props = $page->toArray()['props'];

                $this->assertSame('Roles/Index', $page->toArray()['component']);
                $this->assertGreaterThanOrEqual(7, count($props['roles']));
                $this->assertNotEmpty($props['groups']);

                $purchasing = collect($props['roles'])->firstWhere('name', 'PURCHASING');
                $this->assertContains('po.create', $purchasing['permissions']);
                $this->assertNotContains('po.approve', $purchasing['permissions']);
            });
    }

    #[Test]
    public function a_role_permission_change_takes_effect_immediately(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $viewer = $this->userWithRole('VIEWER');
        $role = Role::findByName('VIEWER');

        $this->assertFalse($viewer->fresh()->can('po.create'));

        $this->actingAs($admin)
            ->put(route('roles.update', $role->getKey()), [
                'permissions' => [...$role->permissions->pluck('name')->all(), 'po.create'],
            ])
            ->assertRedirect();

        // The permission cache is forgotten on write, or the change would take
        // an hour to appear and look like it had not saved.
        $this->assertTrue($viewer->fresh()->can('po.create'));
    }

    #[Test]
    public function the_super_administrator_role_cannot_be_edited(): void
    {
        $role = Role::findByName(RolesAndPermissionsSeeder::SUPER_ADMIN);

        // It is the escape hatch that makes every other role change reversible.
        $this->actingAs($this->userWithRole('ADMIN'))
            ->put(route('roles.update', $role->getKey()), ['permissions' => []])
            ->assertForbidden();

        $this->assertGreaterThan(0, $role->refresh()->permissions->count());
    }

    #[Test]
    public function not_even_a_super_administrator_may_edit_that_role(): void
    {
        $role = Role::findByName(RolesAndPermissionsSeeder::SUPER_ADMIN);

        $this->actingAs($this->userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN))
            ->put(route('roles.update', $role->getKey()), ['permissions' => []])
            ->assertForbidden();
    }

    #[Test]
    public function an_unknown_permission_is_refused(): void
    {
        $role = Role::findByName('VIEWER');

        $this->actingAs($this->userWithRole('ADMIN'))
            ->put(route('roles.update', $role->getKey()), ['permissions' => ['tidak.ada']])
            ->assertSessionHasErrors('permissions.0');
    }

    #[Test]
    public function the_audit_log_is_gated_by_its_own_permission(): void
    {
        // ADMIN holds user.* but audit.view is a separate grant.
        $this->actingAs($this->userWithPermissions(['user.view']))
            ->get(route('audit-logs.index'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AuditLogs/Index')
                ->has('records.data')
                ->has('options.actions'));
    }

    #[Test]
    public function the_audit_log_shows_what_changed_from_and_to(): void
    {
        $user = $this->userWithRole('VIEWER');
        app(AuditLogService::class)->record(
            AuditAction::UPDATED,
            'Supplier',
            $user->getKey(),
            ['name' => 'Sebelum'],
            ['name' => 'Sesudah'],
        );

        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('audit-logs.index', ['module' => 'Supplier']))
            ->assertInertia(function ($page) {
                $row = $page->toArray()['props']['records']['data'][0];

                $this->assertSame('Supplier', $row['module']);
                $this->assertSame(
                    [['field' => 'name', 'from' => 'Sebelum', 'to' => 'Sesudah', 'added' => [], 'removed' => []]],
                    $row['changes'],
                );
            });
    }

    #[Test]
    public function a_list_change_is_shown_as_what_moved_rather_than_two_whole_lists(): void
    {
        app(AuditLogService::class)->record(
            AuditAction::UPDATED,
            'Role',
            1,
            ['permissions' => ['po.view', 'po.create']],
            ['permissions' => ['po.view', 'po.approve']],
        );

        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('audit-logs.index', ['module' => 'Role']))
            ->assertInertia(function ($page) {
                $change = $page->toArray()['props']['records']['data'][0]['changes'][0];

                // Printing both forty-item arrays is technically the diff and
                // practically unreadable.
                $this->assertSame(['po.approve'], $change['added']);
                $this->assertSame(['po.create'], $change['removed']);
                $this->assertNull($change['from']);
            });
    }

    #[Test]
    public function the_audit_log_can_be_narrowed_by_module_action_and_user(): void
    {
        $actor = $this->userWithRole('ADMIN');
        $audit = app(AuditLogService::class);

        $this->actingAs($actor);
        $audit->record(AuditAction::CREATED, 'Supplier', 1);
        $audit->record(AuditAction::DELETED, 'Material', 2);

        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->get(route('audit-logs.index', ['module' => 'Supplier', 'action' => 'CREATED']))
            ->assertInertia(function ($page) {
                $rows = $page->toArray()['props']['records']['data'];

                $this->assertCount(1, $rows);
                $this->assertSame('Supplier', $rows[0]['module']);
                $this->assertSame('CREATED', $rows[0]['action']);
            });
    }

    #[Test]
    public function the_audit_log_offers_no_way_to_write_to_it(): void
    {
        // A trail somebody can edit answers no question worth asking, so there
        // is no store, update or destroy route at all.
        $names = collect(app('router')->getRoutes())
            ->map(fn ($route): string => (string) $route->getName())
            ->filter(fn (string $name): bool => str_starts_with($name, 'audit-logs.'))
            ->values()
            ->all();

        $this->assertSame(['audit-logs.index'], $names);
    }

    #[Test]
    public function a_retired_user_still_appears_behind_the_filter(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $user = $this->userWithRole('WAREHOUSE');
        $this->actingAs($admin)->delete(route('users.destroy', $user->ulid));

        $this->actingAs($admin)
            ->get(route('users.index', ['trashed' => 1]))
            ->assertInertia(function ($page) use ($user) {
                $ids = array_column($page->toArray()['props']['records']['data'], 'id');

                // Their orders and audit entries still name them; a reader
                // following a trail has to be able to find out who that was.
                $this->assertContains($user->getKey(), $ids);
            });
    }
}
