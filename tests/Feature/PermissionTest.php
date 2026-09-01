<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Role and permission wiring (requirement 22).
 */
final class PermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    /**
     * @return array<int, array{string}>
     */
    public static function roles(): array
    {
        return array_map(
            static fn (string $role): array => [$role],
            ['SUPER_ADMIN', 'ADMIN', 'PURCHASING', 'WAREHOUSE', 'LOGISTIC', 'MANAGEMENT', 'SUPPLIER', 'VIEWER'],
        );
    }

    #[Test]
    #[DataProvider('roles')]
    public function every_specified_role_exists_and_can_view_the_dashboard(string $role): void
    {
        $model = Role::query()->where('name', $role)->first();

        $this->assertNotNull($model, "Role {$role} was not seeded.");
        $this->assertTrue($model->hasPermissionTo('dashboard.view'));
    }

    #[Test]
    public function the_full_permission_matrix_is_seeded(): void
    {
        $expected = [
            'dashboard.view',
            'supplier.view', 'supplier.create', 'supplier.update', 'supplier.delete',
            'material.view', 'material.create', 'material.update', 'material.delete',
            'po.view', 'po.create', 'po.update', 'po.approve', 'po.cancel',
            'delivery.view', 'delivery.create', 'delivery.update', 'delivery.cancel',
            'problem.view', 'problem.create', 'problem.update', 'problem.close',
            'report.view', 'report.export',
            'user.view', 'user.create', 'user.update', 'user.delete',
        ];

        $actual = Permission::query()->pluck('name')->all();

        foreach ($expected as $permission) {
            $this->assertContains($permission, $actual);
        }
    }

    #[Test]
    public function super_admin_holds_every_permission(): void
    {
        $user = $this->userWithRole('SUPER_ADMIN');
        $all = Permission::query()->pluck('name');

        foreach ($all as $permission) {
            $this->assertTrue($user->can($permission), "SUPER_ADMIN is missing {$permission}.");
        }
    }

    #[Test]
    public function purchasing_can_create_orders_but_never_approve_them(): void
    {
        $user = $this->userWithRole('PURCHASING');

        $this->assertTrue($user->can('po.create'));
        $this->assertTrue($user->can('po.update'));
        $this->assertFalse($user->can('po.approve'));
        $this->assertFalse($user->can('user.delete'));
    }

    #[Test]
    public function management_can_approve_and_cancel_orders_but_never_create_them(): void
    {
        $user = $this->userWithRole('MANAGEMENT');

        $this->assertTrue($user->can('po.approve'));
        // Approving without the power to cancel would leave a manager able to
        // start work they cannot call off.
        $this->assertTrue($user->can('po.cancel'));
        $this->assertTrue($user->can('audit.view'));
        $this->assertFalse($user->can('po.create'));
        $this->assertFalse($user->can('delivery.create'));
    }

    #[Test]
    public function warehouse_can_receive_goods_but_not_cancel_a_delivery(): void
    {
        $user = $this->userWithRole('WAREHOUSE');

        $this->assertTrue($user->can('delivery.create'));
        $this->assertFalse($user->can('delivery.cancel'));
        $this->assertFalse($user->can('po.approve'));
    }

    #[Test]
    public function viewer_has_read_access_only(): void
    {
        $user = $this->userWithRole('VIEWER');

        foreach (['supplier.view', 'material.view', 'po.view', 'delivery.view', 'problem.view'] as $permission) {
            $this->assertTrue($user->can($permission));
        }

        foreach (['supplier.create', 'po.create', 'delivery.create', 'problem.create', 'report.export'] as $permission) {
            $this->assertFalse($user->can($permission), "VIEWER should not hold {$permission}.");
        }
    }

    #[Test]
    public function the_supplier_role_is_limited_to_its_own_read_only_views(): void
    {
        $user = $this->userWithRole('SUPPLIER');

        $this->assertTrue($user->can('po.view'));
        $this->assertTrue($user->can('delivery.view'));
        $this->assertFalse($user->can('supplier.view'));
        $this->assertFalse($user->can('report.export'));
    }

    #[Test]
    public function a_user_without_any_role_holds_no_permissions(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can('dashboard.view'));
        $this->assertSame([], $user->getAllPermissions()->pluck('name')->all());
    }
}
