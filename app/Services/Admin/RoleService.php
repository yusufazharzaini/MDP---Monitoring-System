<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\AuditAction;
use App\Exceptions\BusinessRuleException;
use App\Services\Audit\AuditLogService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role and permission administration.
 *
 * Roles themselves are fixed - they are the organisation's job titles, seeded
 * with the system - so what this service changes is which permissions each one
 * carries. SUPER_ADMIN is deliberately not editable: it is the escape hatch
 * that makes every other change reversible.
 */
class RoleService
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    /**
     * Every role with the permissions it holds and how many users wear it.
     *
     * One query for roles with their permissions and a count, rather than a
     * lookup per role.
     *
     * @return array<int, array<string, mixed>>
     */
    public function matrix(): array
    {
        return Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->getKey(),
                'name' => $role->name,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
                'protected' => $this->isProtected($role),
            ])
            ->all();
    }

    /**
     * The permission catalogue, grouped by the module it belongs to.
     *
     * `po.approve` becomes group `po`, action `approve`, so the screen renders
     * a grid rather than a flat list of forty checkboxes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function permissionGroups(): array
    {
        return Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->groupBy(static fn (string $name): string => explode('.', $name)[0])
            ->map(static fn ($names, string $group): array => [
                'group' => $group,
                'permissions' => $names->map(static fn (string $name): array => [
                    'name' => $name,
                    'action' => explode('.', $name)[1] ?? $name,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Replace a role's permissions.
     *
     * @param  array<int, string>  $permissions
     */
    public function syncPermissions(Role $role, array $permissions): Role
    {
        if ($this->isProtected($role)) {
            throw new BusinessRuleException(
                "Peran {$role->name} tidak dapat diubah: peran ini adalah jalan masuk terakhir "
                .'jika konfigurasi peran lain keliru.'
            );
        }

        $known = Permission::query()->whereIn('name', $permissions)->pluck('name')->all();
        $unknown = array_diff($permissions, $known);

        if ($unknown !== []) {
            throw new BusinessRuleException('Permission tidak dikenal: '.implode(', ', $unknown).'.');
        }

        return DB::transaction(function () use ($role, $known): Role {
            $before = $role->permissions->pluck('name')->sort()->values()->all();
            $after = collect($known)->sort()->values()->all();

            $role->syncPermissions($known);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            if ($before !== $after) {
                $this->audit->record(
                    AuditAction::UPDATED,
                    'Role',
                    $role->getKey(),
                    ['permissions' => $before],
                    ['permissions' => $after],
                );
            }

            return $role->refresh();
        });
    }

    /**
     * SUPER_ADMIN passes every gate through AppServiceProvider anyway, so
     * editing its permission list would change nothing visible while removing
     * the only role guaranteed to be able to put a mistake right.
     */
    public function isProtected(Role $role): bool
    {
        return $role->name === RolesAndPermissionsSeeder::SUPER_ADMIN;
    }
}
