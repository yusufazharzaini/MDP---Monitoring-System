<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\RecordStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Admin\UserService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * User administration.
 *
 * Two rules run through all of this, and both exist to stop the system being
 * locked away from the people who run it: an administrator cannot take their
 * own access away by accident, and the last super administrator cannot be
 * removed, deactivated or demoted by anybody.
 */
final class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private UserService $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->users = app(UserService::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Budi Santoso',
            'email' => 'budi@yusufazharzaini.test',
            'password' => 'RahasiaSekali123',
            'password_confirmation' => 'RahasiaSekali123',
            'position' => 'Purchasing Officer',
            'status' => RecordStatus::ACTIVE->value,
            'roles' => ['PURCHASING'],
            ...$overrides,
        ];
    }

    #[Test]
    public function creating_a_user_assigns_their_roles_and_hashes_the_password(): void
    {
        $user = $this->users->create(
            collect($this->payload())->except(['roles', 'password_confirmation'])->all(),
            ['PURCHASING'],
        );

        $this->assertTrue($user->hasRole('PURCHASING'));
        $this->assertNotSame('RahasiaSekali123', $user->password);
        $this->assertTrue(password_verify('RahasiaSekali123', $user->password));
    }

    #[Test]
    public function a_role_can_never_arrive_by_mass_assignment(): void
    {
        /*
         * `roles` is not fillable and strict mode is on, so smuggling one in
         * through the attribute array does not quietly do nothing - it throws.
         * Loud is the right failure here: an escalation attempt that silently
         * no-ops is an escalation attempt nobody investigates.
         */
        $this->expectException(MassAssignmentException::class);

        $this->users->create(
            ['name' => 'Curang', 'email' => 'curang@yusufazharzaini.test', 'password' => 'RahasiaSekali123',
                'status' => RecordStatus::ACTIVE->value, 'roles' => [RolesAndPermissionsSeeder::SUPER_ADMIN]],
            ['VIEWER'],
        );
    }

    #[Test]
    public function only_the_explicit_role_argument_assigns_anything(): void
    {
        $user = $this->users->create(
            collect($this->payload())->except(['roles', 'password_confirmation'])->all(),
            ['VIEWER'],
        );

        $this->assertTrue($user->hasRole('VIEWER'));
        $this->assertFalse($user->hasRole(RolesAndPermissionsSeeder::SUPER_ADMIN));
        $this->assertCount(1, $user->getRoleNames());
    }

    #[Test]
    public function an_empty_password_on_edit_leaves_the_existing_one_alone(): void
    {
        $user = $this->userWithRole('PURCHASING');
        $before = $user->password;

        $this->users->update($user, ['name' => 'Nama Baru', 'password' => ''], ['PURCHASING']);

        $this->assertSame($before, $user->refresh()->password);
        $this->assertSame('Nama Baru', $user->name);
    }

    #[Test]
    public function retiring_a_user_keeps_their_history_readable(): void
    {
        $user = $this->userWithRole('WAREHOUSE');
        $admin = $this->userWithRole('ADMIN');

        $this->users->retire($user, $admin);

        // Soft-deleted, not gone: their receipts and audit entries still point
        // at this row.
        $this->assertSoftDeleted('users', ['id' => $user->getKey()]);
        $this->assertNotNull(User::withTrashed()->find($user->getKey()));
        $this->assertSame(RecordStatus::INACTIVE, User::withTrashed()->findOrFail($user->getKey())->status);
    }

    #[Test]
    public function a_retired_user_can_be_brought_back(): void
    {
        $user = $this->userWithRole('WAREHOUSE');
        $this->users->retire($user, $this->userWithRole('ADMIN'));

        $restored = $this->users->restore(User::withTrashed()->findOrFail($user->getKey()));

        $this->assertNull($restored->deleted_at);
        $this->assertSame(RecordStatus::ACTIVE, $restored->status);
    }

    #[Test]
    public function nobody_retires_their_own_account(): void
    {
        $admin = $this->userWithRole('ADMIN');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('akun Anda sendiri');

        $this->users->retire($admin, $admin);
    }

    #[Test]
    public function nobody_deactivates_their_own_account(): void
    {
        $admin = $this->userWithRole('ADMIN');

        $this->expectException(BusinessRuleException::class);

        $this->users->update($admin, ['status' => RecordStatus::INACTIVE->value], ['ADMIN'], $admin);
    }

    #[Test]
    public function nobody_strips_every_role_from_their_own_account(): void
    {
        $admin = $this->userWithRole('ADMIN');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('seluruh peran');

        $this->users->update($admin, ['name' => 'Tetap'], [], $admin);
    }

    #[Test]
    public function the_last_super_administrator_cannot_be_demoted(): void
    {
        $only = $this->userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
        $other = $this->userWithRole('ADMIN');

        $this->assertSame(1, $this->users->activeSuperAdministrators());

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Super administrator terakhir');

        $this->users->update($only, ['name' => 'Turun Pangkat'], ['VIEWER'], $other);
    }

    #[Test]
    public function the_last_super_administrator_cannot_be_retired(): void
    {
        $only = $this->userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
        $other = $this->userWithRole('ADMIN');

        $this->expectException(BusinessRuleException::class);

        $this->users->retire($only, $other);
    }

    #[Test]
    public function the_last_super_administrator_cannot_be_deactivated(): void
    {
        $only = $this->userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
        $other = $this->userWithRole('ADMIN');

        $this->expectException(BusinessRuleException::class);

        $this->users->update(
            $only,
            ['status' => RecordStatus::INACTIVE->value],
            [RolesAndPermissionsSeeder::SUPER_ADMIN],
            $other,
        );
    }

    #[Test]
    public function a_super_administrator_may_be_demoted_once_another_one_exists(): void
    {
        $first = $this->userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
        $this->userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
        $admin = $this->userWithRole('ADMIN');

        $this->assertSame(2, $this->users->activeSuperAdministrators());

        $this->users->update($first, ['name' => 'Sekarang Viewer'], ['VIEWER'], $admin);

        $this->assertTrue($first->refresh()->hasRole('VIEWER'));
        $this->assertSame(1, $this->users->activeSuperAdministrators());
    }

    #[Test]
    public function an_inactive_super_administrator_does_not_count_as_a_way_in(): void
    {
        $active = $this->userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
        $dormant = $this->userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
        $dormant->forceFill(['status' => RecordStatus::INACTIVE])->save();

        // Only the active one is a real way in, so demoting it is still refused.
        $this->assertSame(1, $this->users->activeSuperAdministrators());

        $this->expectException(BusinessRuleException::class);

        $this->users->update($active, ['name' => 'x'], ['VIEWER'], $this->userWithRole('ADMIN'));
    }

    #[Test]
    public function creating_updating_and_retiring_are_all_audited(): void
    {
        $admin = $this->userWithRole('ADMIN');

        $user = $this->users->create(
            collect($this->payload())->except(['roles', 'password_confirmation'])->all(),
            ['PURCHASING'],
            $admin,
        );
        $this->users->update($user, ['name' => 'Budi Baru'], ['WAREHOUSE'], $admin);
        $this->users->retire($user, $admin);

        foreach (['CREATED', 'UPDATED', 'DELETED'] as $action) {
            $this->assertDatabaseHas('audit_logs', [
                'module' => 'User',
                'record_id' => $user->getKey(),
                'action' => $action,
            ]);
        }
    }

    #[Test]
    public function a_role_change_is_audited_with_both_sides(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $user = $this->userWithRole('VIEWER');

        $this->users->update($user, ['name' => $user->name], ['PURCHASING'], $admin);

        $entry = AuditLog::query()
            ->where('module', 'User')
            ->where('record_id', $user->getKey())
            ->get()
            ->first(fn ($log): bool => isset($log->new_values['roles']));

        // Role changes live in a pivot table the model diff never sees, so
        // they are recorded explicitly - and they are what an auditor comes for.
        $this->assertNotNull($entry);
        $this->assertSame(['VIEWER'], $entry->old_values['roles']);
        $this->assertSame(['PURCHASING'], $entry->new_values['roles']);
    }
}
