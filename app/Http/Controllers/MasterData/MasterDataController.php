<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Services\MasterData\MasterDataService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shared read/write flow for a master-data screen.
 *
 * All eight master entities list, filter, paginate, create, edit and retire the
 * same way, so that flow lives here once. A subclass says what it is - which
 * model, which service, which pages, which columns - and nothing more.
 *
 * Authorization runs through the policy on every action, so a route that
 * forgets its permission middleware still cannot be reached by the wrong user.
 */
abstract class MasterDataController extends Controller
{
    protected int $perPage = 15;

    abstract protected function service(): MasterDataService;

    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    /**
     * Inertia page directory, e.g. "Suppliers" for Suppliers/Index.vue.
     */
    abstract protected function pageDirectory(): string;

    /**
     * Route name prefix, e.g. "suppliers" for suppliers.index.
     */
    abstract protected function routeName(): string;

    /**
     * Human label used in flash messages.
     */
    abstract protected function label(): string;

    /**
     * One row as the index table renders it.
     *
     * @return array<string, mixed>
     */
    abstract protected function transform(Model $record): array;

    /**
     * Reference data the create/edit form needs.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [];
    }

    /**
     * @return Builder<Model>
     */
    protected function indexQuery(Request $request): Builder
    {
        $query = $this->modelClass()::query();

        if (method_exists($this->modelClass(), 'scopeWithListRelations')) {
            $query->withListRelations();
        }

        return $query;
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', $this->modelClass());

        $records = $this->indexQuery($request)
            ->search($request->string('search')->toString())
            ->when(
                $request->filled('status'),
                fn (Builder $q) => $q->where('status', $request->string('status')->toString()),
            )
            ->orderBy($this->defaultSortColumn())
            ->paginate($this->perPage)
            ->withQueryString()
            ->through(fn (Model $record): array => $this->transform($record));

        return Inertia::render($this->pageDirectory().'/Index', [
            'records' => $records,
            'filters' => $request->only(['search', 'status']),
            'can' => $this->abilities(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', $this->modelClass());

        return Inertia::render($this->pageDirectory().'/Create', [
            'options' => $this->formOptions(),
        ]);
    }

    /*
     * edit() and destroy() are declared by each subclass with its own concrete
     * model type. Route-model binding resolves the bound record from the type
     * hint, and an abstract Model hint gives the container nothing to build -
     * so the shared bodies live here and the signatures live there.
     */

    protected function editView(Model $record): Response
    {
        $this->authorize('update', $record);

        return Inertia::render($this->pageDirectory().'/Edit', [
            'record' => $this->transform($record),
            'options' => $this->formOptions(),
        ]);
    }

    protected function destroyRecord(Model $record): RedirectResponse
    {
        $this->authorize('delete', $record);

        // A BusinessRuleException from the service renders itself as a flashed
        // error, so a refused delete tells the user why rather than 500-ing.
        $this->service()->delete($record);

        return redirect()
            ->route($this->routeName().'.index')
            ->with('success', $this->label().' berhasil dihapus.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function storeRecord(array $data): RedirectResponse
    {
        $this->authorize('create', $this->modelClass());

        $record = $this->service()->create($data);

        return redirect()
            ->route($this->routeName().'.index')
            ->with('success', $this->label().' '.$this->displayName($record).' berhasil dibuat.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function updateRecord(Model $record, array $data): RedirectResponse
    {
        $this->authorize('update', $record);

        $this->service()->update($record, $data);

        return redirect()
            ->route($this->routeName().'.index')
            ->with('success', $this->label().' '.$this->displayName($record).' berhasil diperbarui.');
    }

    /**
     * What the signed-in user may do, so the UI hides buttons it would refuse.
     *
     * @return array<string, bool>
     */
    protected function abilities(): array
    {
        $user = request()->user();
        $model = $this->modelClass();

        return [
            'create' => $user?->can('create', $model) ?? false,
            'update' => $user?->can('update', new $model) ?? false,
            'delete' => $user?->can('delete', new $model) ?? false,
        ];
    }

    protected function defaultSortColumn(): string
    {
        return 'code';
    }

    protected function displayName(Model $record): string
    {
        return (string) ($record->code ?? $record->name ?? $record->getKey());
    }
}
