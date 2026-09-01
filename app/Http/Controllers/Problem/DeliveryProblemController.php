<?php

declare(strict_types=1);

namespace App\Http\Controllers\Problem;

use App\Enums\CorrectiveActionStatus;
use App\Enums\ProblemSeverity;
use App\Enums\ProblemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Problem\CancelProblemRequest;
use App\Http\Requests\Problem\CloseProblemRequest;
use App\Http\Requests\Problem\DeliveryProblemRequest;
use App\Models\CorrectiveAction;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryProblem;
use App\Models\ProblemAttachment;
use App\Models\ProblemCategory;
use App\Models\Supplier;
use App\Services\Problem\ProblemService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Delivery problem screens.
 *
 * A problem is always raised against a receipt - that is what ties it to a
 * supplier and a period - so create and store are nested under a delivery.
 */
class DeliveryProblemController extends Controller
{
    public function __construct(
        private readonly ProblemService $problems,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DeliveryProblem::class);

        $records = DeliveryProblem::query()
            ->withListRelations()
            ->search($request->string('search')->toString())
            ->when(
                $request->filled('status'),
                fn (Builder $q) => $q->where('delivery_problems.status', $request->string('status')->toString()),
            )
            ->when(
                $request->filled('severity'),
                fn (Builder $q) => $q->where('severity', $request->string('severity')->toString()),
            )
            ->when(
                $request->filled('supplier_id'),
                fn (Builder $q) => $q->where('delivery_problems.supplier_id', $request->integer('supplier_id')),
            )
            ->when(
                $request->filled('problem_category_id'),
                fn (Builder $q) => $q->where('problem_category_id', $request->integer('problem_category_id')),
            )
            // "Open and past its due date" is the queue this screen exists to
            // work through, so it is a filter rather than something the reader
            // has to spot for themselves.
            ->when($request->boolean('overdue'), fn (Builder $q) => $q->overdue())
            ->orderByDesc('problem_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (DeliveryProblem $problem): array => $this->summarise($problem));

        return Inertia::render('Problems/Index', [
            'records' => $records,
            'filters' => $request->only([
                'search', 'status', 'severity', 'supplier_id', 'problem_category_id', 'overdue',
            ]),
            'options' => $this->filterOptions(),
            'summary' => $this->queueSummary(),
        ]);
    }

    /**
     * The reporting form for one receipt: its materials are the only ones a
     * problem against it may name.
     */
    public function create(Delivery $delivery): Response
    {
        $this->authorize('create', DeliveryProblem::class);

        $delivery->load([
            'supplier:id,code,name,short_name',
            'plant:id,code,name',
            'items.material:id,code,name',
        ]);

        return Inertia::render('Problems/Create', [
            'delivery' => $this->deliveryContext($delivery),
            'options' => $this->formOptions(),
        ]);
    }

    public function store(DeliveryProblemRequest $request, Delivery $delivery): RedirectResponse
    {
        $this->authorize('create', DeliveryProblem::class);

        $problem = $this->problems->report($delivery, $request->validated(), $request->user());

        return redirect()
            ->route('problems.show', $problem->ulid)
            ->with('success', "Problem {$problem->problem_number} berhasil dilaporkan.");
    }

    public function show(Request $request, DeliveryProblem $problem): Response
    {
        $this->authorize('view', $problem);

        $problem->load([
            'supplier:id,ulid,code,name,short_name',
            'material:id,ulid,code,name',
            'category:id,code,name',
            'delivery:id,ulid,delivery_number,delivery_date,plant_id,purchase_order_id',
            'delivery.plant:id,name',
            'delivery.purchaseOrder:id,ulid,po_number',
            'creator:id,name',
            'attachments.uploader:id,name',
            'correctiveActions.actionBy:id,name',
        ]);

        return Inertia::render('Problems/Show', [
            'record' => $this->detail($problem),
            'can' => [
                'update' => $request->user()?->can('update', $problem) ?? false,
                'close' => $request->user()?->can('close', $problem) ?? false,
                'cancel' => $request->user()?->can('cancel', $problem) ?? false,
                'addAction' => $request->user()?->can('create', [CorrectiveAction::class, $problem]) ?? false,
                'addAttachment' => $request->user()?->can('create', [ProblemAttachment::class, $problem]) ?? false,
            ],
            // The closing rule, answered by the backend rather than re-derived
            // in the page: the button is enabled only when it would succeed.
            'closable' => $this->problems->hasCompletedAction($problem),
            'maxAttachmentKb' => (int) config('mdp.attachments.max_kilobytes', 5120),
        ]);
    }

    public function edit(DeliveryProblem $problem): Response
    {
        $this->authorize('update', $problem);

        $problem->load([
            'delivery.supplier:id,code,name,short_name',
            'delivery.plant:id,code,name',
            'delivery.items.material:id,code,name',
        ]);

        return Inertia::render('Problems/Edit', [
            'record' => $this->summarise($problem) + [
                'material_id' => $problem->material_id,
                'problem_category_id' => $problem->problem_category_id,
                'description' => $problem->description,
                'root_cause' => $problem->root_cause,
                'pic' => $problem->pic,
            ],
            'delivery' => $this->deliveryContext($problem->delivery),
            'options' => $this->formOptions(),
        ]);
    }

    public function update(DeliveryProblemRequest $request, DeliveryProblem $problem): RedirectResponse
    {
        $this->authorize('update', $problem);

        $this->problems->update($problem, $request->validated(), $request->user());

        return redirect()
            ->route('problems.show', $problem->ulid)
            ->with('success', "Problem {$problem->problem_number} berhasil diperbarui.");
    }

    public function close(CloseProblemRequest $request, DeliveryProblem $problem): RedirectResponse
    {
        $this->authorize('close', $problem);

        $this->problems->close($problem, $request->user(), $request->string('note')->toString() ?: null);

        return back()->with('success', "Problem {$problem->problem_number} ditutup.");
    }

    public function cancel(CancelProblemRequest $request, DeliveryProblem $problem): RedirectResponse
    {
        $this->authorize('cancel', $problem);

        $this->problems->cancel($problem, $request->user(), $request->string('reason')->toString());

        return back()->with('success', "Problem {$problem->problem_number} dibatalkan.");
    }

    /**
     * How the queue stands right now, counted in the database.
     *
     * Three conditional aggregates in one pass rather than three round trips,
     * and never a collection of problems loaded to be counted in PHP.
     *
     * @return array<string, int>
     */
    private function queueSummary(): array
    {
        $open = [ProblemStatus::OPEN->value, ProblemStatus::IN_PROGRESS->value];

        /** @var object{open_count: int|null, overdue_count: int|null, critical_count: int|null}|null $row */
        $row = DB::table('delivery_problems')
            ->selectRaw(
                'sum(case when status in (?, ?) then 1 else 0 end) as open_count',
                $open,
            )
            ->selectRaw(
                'sum(case when status in (?, ?) and due_date is not null and due_date < ? then 1 else 0 end) as overdue_count',
                [...$open, now()->toDateString()],
            )
            ->selectRaw(
                'sum(case when status in (?, ?) and severity = ? then 1 else 0 end) as critical_count',
                [...$open, ProblemSeverity::CRITICAL->value],
            )
            ->first();

        return [
            'open' => (int) ($row->open_count ?? 0),
            'overdue' => (int) ($row->overdue_count ?? 0),
            'critical' => (int) ($row->critical_count ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'statuses' => ProblemStatus::options(),
            'severities' => ProblemSeverity::options(),
            'categories' => ProblemCategory::query()->active()->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn (ProblemCategory $c): array => ['value' => $c->id, 'label' => $c->name])->all(),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn (Supplier $s): array => ['value' => $s->id, 'label' => $s->code.' - '.$s->name])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'severities' => ProblemSeverity::options(),
            'categories' => ProblemCategory::query()->active()->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn (ProblemCategory $c): array => ['value' => $c->id, 'label' => $c->name])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryContext(?Delivery $delivery): array
    {
        if ($delivery === null) {
            return [];
        }

        return [
            'ulid' => $delivery->ulid,
            'delivery_number' => $delivery->delivery_number,
            'delivery_date' => $delivery->delivery_date?->toDateString(),
            'supplier_name' => $delivery->supplier?->displayName(),
            'plant_name' => $delivery->plant?->name,
            // The materials this receipt actually carried; the service refuses
            // a problem that names anything else.
            'materials' => $delivery->items
                ->map(fn (DeliveryItem $item): array => [
                    'value' => $item->material_id,
                    'label' => $item->material?->code.' - '.$item->material?->name,
                ])
                ->unique('value')
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(DeliveryProblem $problem): array
    {
        return [
            'id' => $problem->id,
            'ulid' => $problem->ulid,
            'problem_number' => $problem->problem_number,
            'problem_date' => $problem->problem_date?->toDateString(),
            'due_date' => $problem->due_date?->toDateString(),
            'supplier_name' => $problem->supplier?->displayName(),
            'material_name' => $problem->material?->name,
            'category_name' => $problem->category?->name,
            'delivery_number' => $problem->delivery?->delivery_number,
            'delivery_ulid' => $problem->delivery?->ulid,
            'pic' => $problem->pic,
            'severity' => $problem->severity->value,
            'severity_label' => $problem->severity->label(),
            'severity_variant' => $problem->severity->badgeVariant(),
            'status' => $problem->status->value,
            'status_label' => $problem->status->label(),
            'status_variant' => $problem->status->badgeVariant(),
            'is_overdue' => $problem->isOverdue(),
            'days_until_due' => $problem->daysUntilDue(),
            'attachments_count' => $problem->attachments_count ?? 0,
            'corrective_actions_count' => $problem->corrective_actions_count ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(DeliveryProblem $problem): array
    {
        return [
            ...$this->summarise($problem),
            'description' => $problem->description,
            'root_cause' => $problem->root_cause,
            'closed_at' => $problem->closed_at?->toDateTimeString(),
            'reported_by' => $problem->creator?->name,
            'plant_name' => $problem->delivery?->plant?->name,
            'po_number' => $problem->delivery?->purchaseOrder?->po_number,
            'po_ulid' => $problem->delivery?->purchaseOrder?->ulid,
            'delivery_date' => $problem->delivery?->delivery_date?->toDateString(),
            'attachments' => $problem->attachments->map(fn (ProblemAttachment $file): array => [
                'ulid' => $file->ulid,
                'file_name' => $file->file_name,
                'human_file_size' => $file->human_file_size,
                'mime_type' => $file->mime_type,
                'is_image' => $file->isImage(),
                'uploaded_by' => $file->uploader?->name,
                'uploaded_at' => $file->created_at?->toDateTimeString(),
            ])->all(),
            'corrective_actions' => $problem->correctiveActions
                ->map(fn (CorrectiveAction $action): array => [
                    'id' => $action->id,
                    'action_date' => $action->action_date?->toDateString(),
                    'due_date' => $action->due_date?->toDateString(),
                    'description' => $action->description,
                    'action_by' => $action->actionBy?->name,
                    'status' => $action->status->value,
                    'status_label' => $action->status->label(),
                    'status_variant' => $action->status->badgeVariant(),
                    'is_done' => $action->status === CorrectiveActionStatus::DONE,
                    'is_overdue' => $action->isOverdue(),
                    'completed_at' => $action->completed_at?->toDateTimeString(),
                ])->all(),
        ];
    }
}
