<?php

declare(strict_types=1);

namespace App\Http\Controllers\Delivery;

use App\Enums\DeliveryItemCondition;
use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\CancelDeliveryRequest;
use App\Http\Requests\Delivery\DeliveryRequest;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryProblem;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\Delivery\DeliveryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Receiving screens.
 *
 * Booking always starts from a purchase order - a receipt without a commitment
 * behind it is not something this system can measure - so create and store are
 * nested under one.
 */
class DeliveryController extends Controller
{
    public function __construct(
        private readonly DeliveryService $deliveries,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Delivery::class);

        $records = Delivery::query()
            ->withListRelations()
            ->search($request->string('search')->toString())
            ->when(
                $request->filled('status'),
                fn (Builder $q) => $q->where('deliveries.status', $request->string('status')->toString()),
            )
            ->when(
                $request->filled('supplier_id'),
                fn (Builder $q) => $q->where('supplier_id', $request->integer('supplier_id')),
            )
            ->when(
                $request->filled('plant_id'),
                fn (Builder $q) => $q->where('plant_id', $request->integer('plant_id')),
            )
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Delivery $delivery): array => $this->summarise($delivery));

        return Inertia::render('Deliveries/Index', [
            'records' => $records,
            'filters' => $request->only(['search', 'status', 'supplier_id', 'plant_id']),
            'options' => [
                'statuses' => DeliveryStatus::options(),
                'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'code', 'name'])
                    ->map(fn (Supplier $s): array => ['value' => $s->id, 'label' => $s->code.' - '.$s->name])->all(),
                'plants' => Plant::query()->orderBy('code')->get(['id', 'code', 'name'])
                    ->map(fn (Plant $p): array => ['value' => $p->id, 'label' => $p->code.' - '.$p->name])->all(),
            ],
            'can' => ['create' => $request->user()?->can('create', Delivery::class) ?? false],
        ]);
    }

    /**
     * The receiving form for one purchase order, pre-filled with every line
     * that still has quantity outstanding.
     */
    public function create(PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('create', Delivery::class);

        abort_unless($purchaseOrder->acceptsDeliveries(), 403, 'Purchase order ini tidak dapat menerima delivery.');

        $purchaseOrder->load(['supplier:id,code,name,short_name', 'plant:id,code,name']);

        return Inertia::render('Deliveries/Create', [
            'purchaseOrder' => $this->orderContext($purchaseOrder),
            'options' => ['conditions' => DeliveryItemCondition::options()],
        ]);
    }

    public function store(DeliveryRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('create', Delivery::class);

        $data = $request->validated();
        $delivery = $this->deliveries->receive(
            $purchaseOrder,
            collect($data)->except('items')->all(),
            $data['items'],
            $request->user(),
        );

        return redirect()
            ->route('deliveries.show', $delivery->ulid)
            ->with('success', "Delivery {$delivery->delivery_number} berhasil dicatat.");
    }

    public function show(Delivery $delivery): Response
    {
        $this->authorize('view', $delivery);

        $delivery->load([
            'supplier:id,ulid,code,name,short_name',
            'plant:id,ulid,code,name',
            'purchaseOrder:id,ulid,po_number,po_date,status',
            'receiver:id,name',
            'items.material:id,code,name',
            'items.uom:id,code',
            'items.purchaseOrderItem:id,line_no,schedule_delivery_date,qty_ordered,qty_received',
        ]);

        return Inertia::render('Deliveries/Show', [
            'record' => $this->detail($delivery),
            'can' => [
                'update' => request()->user()?->can('update', $delivery) ?? false,
                'cancel' => request()->user()?->can('cancel', $delivery) ?? false,
                // Problems are raised from here, because a problem without a
                // receipt behind it has no supplier and no period.
                'reportProblem' => ! $delivery->isCancelled()
                    && (request()->user()?->can('create', DeliveryProblem::class) ?? false),
            ],
        ]);
    }

    public function edit(Delivery $delivery): Response
    {
        $this->authorize('update', $delivery);

        $delivery->load(['purchaseOrder.supplier:id,code,name,short_name', 'purchaseOrder.plant:id,code,name', 'items']);

        return Inertia::render('Deliveries/Edit', [
            'record' => $this->detail($delivery),
            'purchaseOrder' => $this->orderContext($delivery->purchaseOrder, $delivery),
            'options' => ['conditions' => DeliveryItemCondition::options()],
        ]);
    }

    public function update(DeliveryRequest $request, Delivery $delivery): RedirectResponse
    {
        $this->authorize('update', $delivery);

        $data = $request->validated();
        $this->deliveries->update(
            $delivery,
            collect($data)->except('items')->all(),
            $data['items'],
            $request->user(),
        );

        return redirect()
            ->route('deliveries.show', $delivery->ulid)
            ->with('success', "Delivery {$delivery->delivery_number} berhasil dikoreksi.");
    }

    public function cancel(CancelDeliveryRequest $request, Delivery $delivery): RedirectResponse
    {
        $this->authorize('cancel', $delivery);

        $this->deliveries->cancel($delivery, $request->user(), $request->string('reason')->toString());

        return back()->with('success', "Delivery {$delivery->delivery_number} dibatalkan.");
    }

    /**
     * The purchase order as the receiving form needs it: its outstanding lines,
     * with what an editing receipt already booked folded back in.
     *
     * @return array<string, mixed>
     */
    private function orderContext(PurchaseOrder $order, ?Delivery $editing = null): array
    {
        $booked = $editing === null
            ? collect()
            : $editing->items->keyBy('purchase_order_item_id');

        $lines = $order->items()
            ->with(['material:id,code,name', 'uom:id,code', 'warehouse:id,code,name'])
            ->orderBy('line_no')
            ->get()
            ->map(function (PurchaseOrderItem $item) use ($booked): array {
                /** @var DeliveryItem|null $existing */
                $existing = $booked->get($item->getKey());

                // Outstanding excludes what this very receipt already booked,
                // otherwise correcting a receipt would look like an over-delivery.
                $outstanding = $item->outstandingQuantity()
                    + ($existing === null ? 0.0 : $existing->effectiveQuantity());

                return [
                    'purchase_order_item_id' => $item->getKey(),
                    'line_no' => $item->line_no,
                    'material_code' => $item->material?->code,
                    'material_name' => $item->material?->name,
                    'warehouse_name' => $item->warehouse?->name,
                    'uom_code' => $item->uom?->code,
                    'schedule_delivery_date' => $item->schedule_delivery_date?->toDateString(),
                    'qty_ordered' => (float) $item->qty_ordered,
                    'qty_received' => (float) $item->qty_received,
                    'outstanding' => round(max(0.0, $outstanding), 4),
                    'booked_here' => $existing === null ? null : (float) $existing->qty_received,
                    'booked_condition' => $existing?->condition->value,
                ];
            });

        return [
            'id' => $order->getKey(),
            'ulid' => $order->ulid,
            'po_number' => $order->po_number,
            'po_date' => $order->po_date?->toDateString(),
            'supplier_name' => $order->supplier?->displayName(),
            'plant_name' => $order->plant?->name,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'status_variant' => $order->status->badgeVariant(),
            'lines' => $lines->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(Delivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'ulid' => $delivery->ulid,
            'delivery_number' => $delivery->delivery_number,
            'delivery_date' => $delivery->delivery_date?->toDateString(),
            'do_number' => $delivery->do_number,
            'po_number' => $delivery->purchaseOrder?->po_number,
            'supplier_name' => $delivery->supplier?->displayName(),
            'plant_name' => $delivery->plant?->name,
            'items_count' => $delivery->items_count ?? 0,
            'problems_count' => $delivery->problems_count ?? 0,
            'status' => $delivery->status->value,
            'status_label' => $delivery->status->label(),
            'status_variant' => $delivery->status->badgeVariant(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Delivery $delivery): array
    {
        return [
            ...$this->summarise($delivery),
            'purchase_order_ulid' => $delivery->purchaseOrder?->ulid,
            'vehicle_number' => $delivery->vehicle_number,
            'driver_name' => $delivery->driver_name,
            'received_by_name' => $delivery->receiver?->name,
            'remarks' => $delivery->remarks,
            'items' => $delivery->items->map(fn (DeliveryItem $item): array => [
                'id' => $item->id,
                'purchase_order_item_id' => $item->purchase_order_item_id,
                'line_no' => $item->purchaseOrderItem?->line_no,
                'material_code' => $item->material?->code,
                'material_name' => $item->material?->name,
                'uom_code' => $item->uom?->code,
                'schedule_delivery_date' => $item->purchaseOrderItem?->schedule_delivery_date?->toDateString(),
                'qty_ordered' => (float) ($item->purchaseOrderItem?->qty_ordered ?? 0),
                'qty_received' => (float) $item->qty_received,
                'condition' => $item->condition->value,
                'condition_label' => $item->condition->label(),
                'condition_variant' => $item->condition->badgeVariant(),
                'timeliness_status' => $item->timeliness_status->value,
                'quantity_status' => $item->quantity_status->value,
                'overall_status' => $item->overall_status->value,
                'overall_status_label' => $item->overall_status->label(),
                'overall_status_variant' => $item->overall_status->badgeVariant(),
                'days_late' => $item->days_late,
                'remarks' => $item->remarks,
            ])->all(),
        ];
    }
}
