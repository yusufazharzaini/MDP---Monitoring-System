<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RolePermissionRequest;
use App\Services\Admin\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * The permission matrix.
 *
 * Roles are seeded with the system and neither created nor deleted here - they
 * are the organisation's job titles. What an administrator changes is which
 * permissions each one carries.
 */
class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roles,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        return Inertia::render('Roles/Index', [
            'roles' => $this->roles->matrix(),
            'groups' => $this->roles->permissionGroups(),
            'can' => ['update' => $request->user()?->can('user.update') ?? false],
        ]);
    }

    public function update(RolePermissionRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $this->roles->syncPermissions($role, $request->permissions());

        return back()->with('success', "Permission peran {$role->name} diperbarui.");
    }
}
