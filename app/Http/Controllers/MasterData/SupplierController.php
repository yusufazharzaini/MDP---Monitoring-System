<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use App\Http\Requests\MasterData\SupplierRequest;
use App\Models\Supplier;
use App\Services\MasterData\MasterDataService;
use App\Services\MasterData\SupplierService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends MasterDataController
{
    public function __construct(private readonly SupplierService $suppliers) {}

    protected function service(): MasterDataService
    {
        return $this->suppliers;
    }

    protected function modelClass(): string
    {
        return Supplier::class;
    }

    protected function pageDirectory(): string
    {
        return 'Suppliers';
    }

    protected function routeName(): string
    {
        return 'suppliers';
    }

    protected function label(): string
    {
        return 'Supplier';
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        return $this->storeRecord($request->validated());
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        return $this->updateRecord($supplier, $request->validated());
    }

    /**
     * The supplier detail page also hosts its contacts, which is where contact
     * maintenance naturally belongs rather than on a screen of its own.
     */
    public function show(Supplier $supplier): Response
    {
        $this->authorize('view', $supplier);

        $supplier->load(['contacts' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('name')]);

        return Inertia::render('Suppliers/Show', [
            'record' => $this->transform($supplier),
            'contacts' => $supplier->contacts->map(fn ($contact): array => [
                'id' => $contact->id,
                'name' => $contact->name,
                'position' => $contact->position,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'is_primary' => $contact->is_primary,
                'status' => $contact->status->value,
                'status_label' => $contact->status->label(),
                'status_variant' => $contact->status->badgeVariant(),
            ])->all(),
            'can' => $this->abilities(),
        ]);
    }

    public function edit(Supplier $supplier): Response
    {
        return $this->editView($supplier);
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        return $this->destroyRecord($supplier);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'types' => SupplierType::options(),
            'statuses' => SupplierStatus::options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function transform(Model $record): array
    {
        /** @var Supplier $record */
        return [
            'id' => $record->id,
            'ulid' => $record->ulid,
            'code' => $record->code,
            'name' => $record->name,
            'short_name' => $record->short_name,
            'address' => $record->address,
            'city' => $record->city,
            'country' => $record->country,
            'pic_name' => $record->pic_name,
            'pic_email' => $record->pic_email,
            'pic_phone' => $record->pic_phone,
            'lead_time_days' => $record->lead_time_days,
            'payment_term' => $record->payment_term,
            'supplier_type' => $record->supplier_type->value,
            'supplier_type_label' => $record->supplier_type->label(),
            'status' => $record->status->value,
            'status_label' => $record->status->label(),
            'status_variant' => $record->status->badgeVariant(),
            'contacts_count' => $record->contacts_count ?? 0,
        ];
    }
}
