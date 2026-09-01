<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Department;
use App\Models\Plant;
use App\Models\User;
use App\Services\Admin\UserService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * User administration.
 *
 * Retired users stay visible behind a filter rather than vanishing: their
 * purchase orders and audit entries still name them, and a reader following a
 * trail needs to be able to find out who that was.
 */
class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $records = User::query()
            ->withListRelations()
            ->with('roles:id,name')
            ->when($request->boolean('trashed'), fn (Builder $q) => $q->onlyTrashed())
            ->search($request->string('search')->toString())
            ->when(
                $request->filled('role'),
                fn (Builder $q) => $q->role($request->string('role')->toString()),
            )
            ->when(
                $request->filled('status'),
                fn (Builder $q) => $q->where('status', $request->string('status')->toString()),
            )
            ->when(
                $request->filled('department_id'),
                fn (Builder $q) => $q->where('department_id', $request->integer('department_id')),
            )
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user): array => $this->summarise($user, $request->user()));

        return Inertia::render('Users/Index', [
            'records' => $records,
            'filters' => $request->only(['search', 'role', 'status', 'department_id', 'trashed']),
            'options' => $this->options(),
            'can' => ['create' => $request->user()?->can('create', User::class) ?? false],
            // Shown on screen so an administrator can see why the last super
            // admin refuses to be demoted, before they try it.
            'superAdminCount' => $this->users->activeSuperAdministrators(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create', ['options' => $this->options()]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $user = $this->users->create($request->userAttributes(), $request->roles(), $request->user());

        return redirect()
            ->route('users.index')
            ->with('success', "Pengguna {$user->name} berhasil dibuat.");
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        $user->load(['roles:id,name', 'department:id,code,name', 'plant:id,code,name']);

        return Inertia::render('Users/Edit', [
            'record' => [
                ...$this->summarise($user, $request->user()),
                'department_id' => $user->department_id,
                'plant_id' => $user->plant_id,
                'phone' => $user->phone,
            ],
            'options' => $this->options($user),
            // Editing yourself is allowed; removing your own way back in is not.
            'isSelf' => $request->user()?->getKey() === $user->getKey(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->users->update($user, $request->userAttributes(), $request->roles(), $request->user());

        return redirect()
            ->route('users.index')
            ->with('success', "Pengguna {$user->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $this->users->retire($user, $request->user());

        return back()->with('success', "Akses pengguna {$user->name} dicabut.");
    }

    public function restore(User $user): RedirectResponse
    {
        $this->authorize('restore', $user);

        $this->users->restore($user);

        return back()->with('success', "Akses pengguna {$user->name} dipulihkan.");
    }

    /**
     * @return array<string, mixed>
     */
    private function options(?User $subject = null): array
    {
        return [
            // The super administrator role is not offered to someone who
            // cannot grant it. UserService refuses it regardless, so this is
            // the screen telling the truth rather than the control itself.
            'roles' => Role::query()->orderBy('name')->pluck('name')
                ->reject(fn (string $name): bool => $name === RolesAndPermissionsSeeder::SUPER_ADMIN
                    && ! $this->mayAssignSuperAdmin($subject))
                ->values()
                ->map(fn (string $name): array => ['value' => $name, 'label' => $name])->all(),
            'statuses' => RecordStatus::options(),
            'departments' => Department::query()->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn (Department $d): array => ['value' => $d->id, 'label' => $d->code.' - '.$d->name])->all(),
            'plants' => Plant::query()->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn (Plant $p): array => ['value' => $p->id, 'label' => $p->code.' - '.$p->name])->all(),
        ];
    }

    /**
     * Whether the signed-in user may hand the super administrator role to this
     * subject - or, when creating, to the account about to exist.
     */
    private function mayAssignSuperAdmin(?User $subject): bool
    {
        $actor = request()->user();

        if ($actor === null) {
            return false;
        }

        return $subject === null
            ? $actor->hasRole(RolesAndPermissionsSeeder::SUPER_ADMIN)
            : $actor->can('assignSuperAdmin', $subject);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(User $user, ?User $actor): array
    {
        return [
            'id' => $user->getKey(),
            'ulid' => $user->ulid,
            'name' => $user->name,
            'email' => $user->email,
            'employee_code' => $user->employee_code,
            'position' => $user->position,
            'department_name' => $user->department?->name,
            'plant_name' => $user->plant?->name,
            'roles' => $user->roles->pluck('name')->all(),
            'status' => $user->status->value,
            'status_label' => $user->status->label(),
            'status_variant' => $user->status->badgeVariant(),
            'is_retired' => $user->trashed(),
            'is_self' => $actor?->getKey() === $user->getKey(),
            'can' => [
                'update' => $actor?->can('update', $user) ?? false,
                'delete' => $actor?->can('delete', $user) ?? false,
                'restore' => $actor?->can('restore', $user) ?? false,
            ],
        ];
    }
}
