<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The activity trail.
 *
 * Read-only by design: there is no store, update or destroy action anywhere in
 * this controller or its routes, because a trail somebody can edit answers no
 * question worth asking.
 */
class AuditLogController extends Controller
{
    /**
     * What actually moved, as field / from / to.
     *
     * changedFields() names the keys; a reader needs the values, and an entry
     * created or deleted has only one side to show.
     *
     * @return array<int, array{field: string, from: string|null, to: string|null, added: array<int, string>, removed: array<int, string>}>
     */
    private function diff(AuditLog $log): array
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];

        return collect($log->changedFields())
            ->map(function (string $field) use ($old, $new): array {
                $before = $old[$field] ?? null;
                $after = $new[$field] ?? null;

                /*
                 * A list changing - a role's permissions, a user's roles -
                 * reads as what was added and what was taken away. Printing
                 * both forty-item arrays side by side is technically the diff
                 * and practically unreadable.
                 */
                if (is_array($before) || is_array($after)) {
                    $beforeList = array_map(strval(...), (array) $before);
                    $afterList = array_map(strval(...), (array) $after);

                    return [
                        'field' => $field,
                        'from' => null,
                        'to' => null,
                        'added' => array_values(array_diff($afterList, $beforeList)),
                        'removed' => array_values(array_diff($beforeList, $afterList)),
                    ];
                }

                return [
                    'field' => $field,
                    'from' => $this->readable($before),
                    'to' => $this->readable($after),
                    'added' => [],
                    'removed' => [],
                ];
            })
            ->all();
    }

    private function readable(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value),
        };
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('audit.view') ?? false, 403);

        $records = AuditLog::query()
            ->withListRelations()
            ->when(
                $request->filled('module'),
                fn (Builder $q) => $q->where('module', $request->string('module')->toString()),
            )
            ->when(
                $request->filled('action'),
                fn (Builder $q) => $q->where('action', $request->string('action')->toString()),
            )
            ->when(
                $request->filled('user_id'),
                fn (Builder $q) => $q->where('user_id', $request->integer('user_id')),
            )
            ->when(
                $request->filled('date_from') && $request->filled('date_to'),
                fn (Builder $q) => $q->betweenDates(
                    $request->string('date_from')->toString(),
                    $request->string('date_to')->toString(),
                ),
            )
            ->when(
                $request->filled('record_id'),
                fn (Builder $q) => $q->where('record_id', $request->integer('record_id')),
            )
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AuditLog $log): array => [
                'id' => $log->getKey(),
                'action' => $log->action->value,
                'action_label' => $log->action->label(),
                'action_variant' => $log->action->badgeVariant(),
                'module' => $log->module,
                'record_id' => $log->record_id,
                'user_name' => $log->user?->name ?? 'Sistem',
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toDateTimeString(),
                // The diff itself, so a reader sees what moved without opening
                // a second screen.
                'changes' => $this->diff($log),
            ]);

        return Inertia::render('AuditLogs/Index', [
            'records' => $records,
            'filters' => $request->only(['module', 'action', 'user_id', 'date_from', 'date_to', 'record_id']),
            'options' => [
                'actions' => AuditAction::options(),
                'modules' => AuditLog::query()
                    ->select('module')
                    ->distinct()
                    ->orderBy('module')
                    ->pluck('module')
                    ->map(fn (string $module): array => ['value' => $module, 'label' => $module])
                    ->all(),
                'users' => User::query()->withTrashed()->orderBy('name')->get(['id', 'name'])
                    ->map(fn (User $u): array => ['value' => $u->id, 'label' => $u->name])->all(),
            ],
        ]);
    }
}
