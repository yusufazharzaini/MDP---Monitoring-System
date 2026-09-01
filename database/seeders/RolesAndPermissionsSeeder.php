<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles and permissions (requirement 22).
 *
 * SUPER_ADMIN is granted every permission implicitly through a Gate::before
 * rule in AuthServiceProvider, but is also given them explicitly here so the
 * role screen shows the truth.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public const SUPER_ADMIN = 'SUPER_ADMIN';

    /**
     * module => actions
     *
     * @var array<string, array<int, string>>
     */
    private const PERMISSIONS = [
        'dashboard' => ['view'],
        'supplier' => ['view', 'create', 'update', 'delete'],
        'plant' => ['view', 'create', 'update', 'delete'],
        'warehouse' => ['view', 'create', 'update', 'delete'],
        'material' => ['view', 'create', 'update', 'delete'],
        'po' => ['view', 'create', 'update', 'approve', 'cancel'],
        'delivery' => ['view', 'create', 'update', 'cancel'],
        'problem' => ['view', 'create', 'update', 'close'],
        /*
         * Approving a monthly scorecard is a management judgement on a
         * supplier, not a reporting action - `report.view` reaches every
         * VIEWER, and a viewer must not be able to sign one off.
         */
        'evaluation' => ['view', 'create', 'approve'],
        'report' => ['view', 'export'],
        'user' => ['view', 'create', 'update', 'delete'],
        'setting' => ['view', 'update'],
        'audit' => ['view'],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const ROLE_PERMISSIONS = [
        'ADMIN' => ['*'],
        'PURCHASING' => [
            'dashboard.view',
            'supplier.view', 'supplier.create', 'supplier.update',
            'material.view',
            'plant.view', 'warehouse.view',
            'po.view', 'po.create', 'po.update', 'po.cancel',
            'delivery.view',
            'problem.view', 'problem.create', 'problem.update',
            'evaluation.view', 'evaluation.create',
            'report.view', 'report.export',
        ],
        'WAREHOUSE' => [
            'dashboard.view',
            'supplier.view', 'material.view', 'plant.view', 'warehouse.view',
            'po.view',
            'delivery.view', 'delivery.create', 'delivery.update',
            'problem.view', 'problem.create', 'problem.update',
            'report.view',
        ],
        'LOGISTIC' => [
            'dashboard.view',
            'supplier.view', 'material.view', 'plant.view', 'warehouse.view',
            'po.view',
            'delivery.view', 'delivery.create', 'delivery.update', 'delivery.cancel',
            'problem.view', 'problem.create', 'problem.update', 'problem.close',
            'evaluation.view',
            'report.view', 'report.export',
        ],
        'MANAGEMENT' => [
            'dashboard.view',
            'supplier.view', 'material.view', 'plant.view', 'warehouse.view',
            // A manager who may release an order must also be able to stop
            // one; approving without the power to cancel leaves them able to
            // start work they cannot call off.
            'po.view', 'po.approve', 'po.cancel',
            'delivery.view',
            'problem.view', 'problem.close',
            'evaluation.view', 'evaluation.create', 'evaluation.approve',
            'report.view', 'report.export',
            'audit.view', 'setting.view',
        ],
        // External supplier portal access: their own data, read only.
        'SUPPLIER' => [
            'dashboard.view',
            'po.view', 'delivery.view', 'problem.view',
        ],
        'VIEWER' => [
            'dashboard.view',
            'supplier.view', 'material.view', 'plant.view', 'warehouse.view',
            'po.view', 'delivery.view', 'problem.view',
            'evaluation.view',
            'report.view',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = $this->createPermissions();

        $superAdmin = Role::findOrCreate(self::SUPER_ADMIN, 'web');
        $superAdmin->syncPermissions($all);

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions === ['*'] ? $all : $permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<int, string>
     */
    private function createPermissions(): array
    {
        $names = [];

        foreach (self::PERMISSIONS as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = $module.'.'.$action;
            }
        }

        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }

        return $names;
    }
}
