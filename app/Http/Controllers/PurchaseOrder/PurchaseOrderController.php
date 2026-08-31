<?php

declare(strict_types=1);

namespace App\Http\Controllers\PurchaseOrder;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrder\CancelPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\PurchaseOrderRequest;
use App\Models\Material;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\Warehouse;
use App\Services\PurchaseOrder\PurchaseOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The purchase order screens.
 *
 * Thin by design: it authorizes, hands validated input to PurchaseOrderService,
 * and renders. Every lifecycle rule lives in the service, so the same guard
 * applies whether a transition comes from this controller, a console command or
 * a future import.
 */
class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $orders,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $orders = PurchaseOrder::query()
            ->withListRelations()
            ->search($request->string('search')->toString())
            ->when(
                $request->filled('status'),
                fn (Builder $q) => $q->where('status', $request->string('status')->toString()),
            )
            ->when(
                $request->filled('supplier_id'),
                fn (Builder $q) => $q->where('supplier_id', $request->integer('supplier_id')),
            )
            ->when(
                $request->filled('plant_id'),
                fn (Builder $q) => $q->where('plant_id', $request->integer('plant_id')),
            )
            ->orderByDesc('po_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PurchaseOrder $order): array => $this->summarise($order));

        return Inertia::render('PurchaseOrders/Index', [
            'records' => $orders,
            'filters' => $request->only(['search', 'status', 'supplier_id', 'plant_id']),
            'options' => $this->filterOptions(),
            'can' => ['create' => $request->user()?->can('create', PurchaseOrder::class) ?? false],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', PurchaseOrder::class);

        return Inertia::render('PurchaseOrders/Create', [
            'options' => $this->formOptions(),
        ]);
    }

    public function store(PurchaseOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $data = $request->validated();
        $order = $this->orders->create(
            collect($data)->except('items')->all(),
            $data['items'],
            $request->user(),
        );

        return redirect()
            ->route('purchase-orders.show', $order->ulid)
            ->with('success', "Purchase order {$order->po_number} berhasil dibuat sebagai draft.");
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load([
            'supplier:id,ulid,code,name,short_name',
            'plant:id,ulid,code,name',
            'creator:id,name',
            'approver:id,name',
            'items.material:id,ulid,code,name',
            'items.warehouse:id,ulid,code,name',
            'items.uom:id,code,name',
            'deliveries:id,ulid,purchase_order_id,delivery_number,delivery_date,status',
        ]);

        return Inertia::render('PurchaseOrders/Show', [
            'record' => $this->detail($purchaseOrder),
            'can' => $this->abilities($purchaseOrder),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('update', $purchaseOrder);

        $purchaseOrder->load(['items.material:id,code,name', 'items.uom:id,code']);

        return Inertia::render('PurchaseOrders/Edit', [
            'record' => $this->detail($purchaseOrder),
            'options' => $this->formOptions(),
        ]);
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        $data = $request->validated();
        $this->orders->update(
            $purchaseOrder,
            collect($data)->except('items')->all(),
            $data['items'],
        );

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder->ulid)
            ->with('success', "Purchase order {$purchaseOrder->po_number} berhasil diperbarui.");
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('submit', $purchaseOrder);

        $this->orders->submit($purchaseOrder, $request->user());

        return back()->with('success', "Purchase order {$purchaseOrder->po_number} diajukan untuk approval.");
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('approve', $purchaseOrder);

        $this->orders->approve($purchaseOrder, $request->user());

        return back()->with('success', "Purchase order {$purchaseOrder->po_number} disetujui.");
    }

    public function cancel(CancelPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('cancel', $purchaseOrder);

        $this->orders->cancel($purchaseOrder, $request->user(), $request->string('reason')->toString());

        return back()->with('success', "Purchase order {$purchaseOrder->po_number} dibatalkan.");
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(PurchaseOrder $order): array
    {
        return [
            'id' => $order->id,
            'ulid' => $order->ulid,
            'po_number' => $order->po_number,
            'po_date' => $order->po_date?->toDateString(),
            'supplier_name' => $order->supplier?->displayName(),
            'supplier_code' => $order->supplier?->code,
            'plant_name' => $order->plant?->name,
            'items_count' => $order->items_count ?? 0,
            'total_amount' => (float) $order->total_amount,
            'currency' => $order->currency,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'status_variant' => $order->status->badgeVariant(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(PurchaseOrder $order): array
    {
        return [
            ...$this->summarise($order),
            'supplier_id' => $order->supplier_id,
            'plant_id' => $order->plant_id,
            'payment_term' => $order->payment_term,
            'remarks' => $order->remarks,
            'created_by_name' => $order->creator?->name,
            'approved_by_name' => $order->approver?->name,
            'approved_at' => $order->approved_at?->toIso8601String(),
            'items' => $order->items->map(fn (PurchaseOrderItem $item): array => [
                'id' => $item->id,
                'line_no' => $item->line_no,
                'material_id' => $item->material_id,
                'material_code' => $item->material?->code,
                'material_name' => $item->material?->name,
                'warehouse_id' => $item->warehouse_id,
                'warehouse_name' => $item->warehouse?->name,
                'uom_id' => $item->uom_id,
                'uom_code' => $item->uom?->code,
                'schedule_delivery_date' => $item->schedule_delivery_date?->toDateString(),
                'qty_ordered' => (float) $item->qty_ordered,
                'qty_received' => (float) $item->qty_received,
                'outstanding' => $item->outstandingQuantity(),
                'unit_price' => (float) $item->unit_price,
                'amount' => (float) $item->amount,
                'overall_status' => $item->overall_status->value,
                'overall_status_label' => $item->overall_status->label(),
                'overall_status_variant' => $item->overall_status->badgeVariant(),
                'remarks' => $item->remarks,
            ])->all(),
            'deliveries' => $order->deliveries->map(fn ($delivery): array => [
                'ulid' => $delivery->ulid,
                'delivery_number' => $delivery->delivery_number,
                'delivery_date' => $delivery->delivery_date?->toDateString(),
                'status' => $delivery->status->value,
                'status_label' => $delivery->status->label(),
                'status_variant' => $delivery->status->badgeVariant(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function abilities(PurchaseOrder $order): array
    {
        $user = request()->user();

        return [
            'update' => $user?->can('update', $order) ?? false,
            'submit' => $user?->can('submit', $order) ?? false,
            'approve' => $user?->can('approve', $order) ?? false,
            'cancel' => $user?->can('cancel', $order) ?? false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'statuses' => PurchaseOrderStatus::options(),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn (Supplier $s): array => ['value' => $s->id, 'label' => $s->code.' - '.$s->name])->all(),
            'plants' => Plant::query()->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn (Plant $p): array => ['value' => $p->id, 'label' => $p->code.' - '.$p->name])->all(),
        ];
    }

    /**
     * Only active master data may be chosen on a new line - an order pointing
     * at a retired material is an order nobody can fulfil.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'suppliers' => Supplier::query()->orderable()->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn (Supplier $s): array => ['value' => $s->id, 'label' => $s->code.' - '.$s->name])->all(),
            'plants' => Plant::query()->active()->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn (Plant $p): array => ['value' => $p->id, 'label' => $p->code.' - '.$p->name])->all(),
            'materials' => Material::query()->active()->orderBy('code')->get(['id', 'code', 'name', 'uom_id'])
                ->map(fn (Material $m): array => [
                    'value' => $m->id,
                    'label' => $m->code.' - '.$m->name,
                    'uom_id' => $m->uom_id,
                ])->all(),
            'uoms' => Uom::query()->active()->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn (Uom $u): array => ['value' => $u->id, 'label' => $u->code])->all(),
            // Grouped by plant so the line editor can narrow the choice once a
            // plant is picked, which is what makes the same-plant rule obvious.
            'warehouses' => Warehouse::query()->active()->orderBy('code')
                ->get(['id', 'plant_id', 'code', 'name'])
                ->map(fn (Warehouse $w): array => [
                    'value' => $w->id,
                    'label' => $w->code.' - '.$w->name,
                    'plant_id' => $w->plant_id,
                ])->all(),
        ];
    }
}
