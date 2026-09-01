<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

/**
 * User administration.
 *
 * Two rules run through everything here, and both exist to stop the system
 * being locked away from the people who run it: an administrator cannot take
 * their own access away by accident, and the last remaining super administrator
 * cannot be removed, deactivated or demoted by anybody.
 */
class UserService
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $roles
     */
    public function create(array $attributes, array $roles, ?User $actor = null): User
    {
        return DB::transaction(function () use ($attributes, $roles): User {
            $user = new User;
            $user->fill($attributes);
            $user->forceFill(['email_verified_at' => now()])->save();

            // Roles never travel through fill(): spatie owns that table, and a
            // mass-assigned role is a privilege escalation waiting to happen.
            $user->syncRoles($roles);

            $this->audit->record(AuditAction::CREATED, 'User', $user->getKey(), null, [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
            ]);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $roles
     */
    public function update(User $user, array $attributes, array $roles, ?User $actor = null): User
    {
        $this->guardKeepsASuperAdministrator($user, $roles, $attributes['status'] ?? null);
        $this->guardNotLockingSelfOut($user, $roles, $attributes['status'] ?? null, $actor);

        return DB::transaction(function () use ($user, $attributes, $roles): User {
            // A blank password field means "leave it alone", not "set it blank".
            if (($attributes['password'] ?? null) === null || $attributes['password'] === '') {
                unset($attributes['password']);
            }

            $user->fill($attributes);
            $this->audit->recordModelChange(AuditAction::UPDATED, $user);
            $user->save();

            $before = $user->getRoleNames()->sort()->values()->all();
            $user->syncRoles($roles);
            $after = collect($roles)->sort()->values()->all();

            if ($before !== $after) {
                // Role changes are the ones an auditor comes looking for, and
                // they live in a pivot table the model diff never sees.
                $this->audit->record(AuditAction::UPDATED, 'User', $user->getKey(), ['roles' => $before], ['roles' => $after]);
            }

            return $user->refresh();
        });
    }

    /**
     * Retire a user without destroying what they did.
     *
     * Their purchase orders, receipts and audit entries all point at this row,
     * so it soft-deletes: the history stays readable and the account stops
     * working.
     */
    public function retire(User $user, ?User $actor = null): void
    {
        $this->guardNotSelf($user, $actor, 'menonaktifkan akun Anda sendiri');
        $this->guardKeepsASuperAdministrator($user, [], RecordStatus::INACTIVE->value);

        DB::transaction(function () use ($user): void {
            $user->forceFill(['status' => RecordStatus::INACTIVE])->save();
            $user->delete();

            $this->audit->record(AuditAction::DELETED, 'User', $user->getKey(), null, [
                'name' => $user->name,
                'email' => $user->email,
            ]);
        });
    }

    public function restore(User $user): User
    {
        DB::transaction(function () use ($user): void {
            $user->restore();
            $user->forceFill(['status' => RecordStatus::ACTIVE])->save();

            $this->audit->record(AuditAction::RESTORED, 'User', $user->getKey(), null, [
                'name' => $user->name,
                'email' => $user->email,
            ]);
        });

        return $user->refresh();
    }

    /**
     * How many active super administrators the system would have left.
     */
    public function activeSuperAdministrators(?User $excluding = null): int
    {
        return User::query()
            ->role(RolesAndPermissionsSeeder::SUPER_ADMIN)
            ->active()
            ->when($excluding !== null, fn ($query) => $query->whereKeyNot($excluding->getKey()))
            ->count();
    }

    /**
     * Nobody may remove the last way into the system.
     *
     * @param  array<int, string>  $roles
     */
    private function guardKeepsASuperAdministrator(User $user, array $roles, mixed $status): void
    {
        if (! $user->hasRole(RolesAndPermissionsSeeder::SUPER_ADMIN)) {
            return;
        }

        $keepsRole = in_array(RolesAndPermissionsSeeder::SUPER_ADMIN, $roles, true);
        $staysActive = $status === null || $status === RecordStatus::ACTIVE->value;

        if ($keepsRole && $staysActive) {
            return;
        }

        if ($this->activeSuperAdministrators(excluding: $user) === 0) {
            throw new BusinessRuleException(
                'Super administrator terakhir tidak dapat dinonaktifkan atau diturunkan perannya. '
                .'Tunjuk super administrator lain terlebih dahulu.'
            );
        }
    }

    /**
     * An administrator editing their own account cannot remove their own way
     * back in - the mistake is silent until the next login.
     *
     * @param  array<int, string>  $roles
     */
    private function guardNotLockingSelfOut(User $user, array $roles, mixed $status, ?User $actor): void
    {
        if ($actor === null || $actor->getKey() !== $user->getKey()) {
            return;
        }

        if ($status !== null && $status !== RecordStatus::ACTIVE->value) {
            throw new BusinessRuleException('Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        if ($roles === []) {
            throw new BusinessRuleException('Anda tidak dapat menghapus seluruh peran dari akun Anda sendiri.');
        }
    }

    private function guardNotSelf(User $user, ?User $actor, string $what): void
    {
        if ($actor !== null && $actor->getKey() === $user->getKey()) {
            throw new BusinessRuleException("Anda tidak dapat {$what}.");
        }
    }
}
