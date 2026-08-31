<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\SupplierContactRequest;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Services\MasterData\SupplierContactService;
use Illuminate\Http\RedirectResponse;

/**
 * Supplier contacts are maintained from the supplier's own page rather than a
 * screen of their own - a contact has no meaning away from its supplier - so
 * this controller carries only the write actions.
 */
class SupplierContactController extends Controller
{
    public function __construct(
        private readonly SupplierContactService $contacts,
    ) {}

    public function store(SupplierContactRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('create', SupplierContact::class);

        $this->contacts->create([
            ...$request->validated(),
            'supplier_id' => $supplier->getKey(),
        ]);

        return back()->with('success', 'Kontak supplier berhasil ditambahkan.');
    }

    public function update(
        SupplierContactRequest $request,
        Supplier $supplier,
        SupplierContact $contact,
    ): RedirectResponse {
        $this->authorize('update', $contact);
        $this->assertBelongsTo($supplier, $contact);

        $this->contacts->update($contact, $request->validated());

        return back()->with('success', 'Kontak supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier, SupplierContact $contact): RedirectResponse
    {
        $this->authorize('delete', $contact);
        $this->assertBelongsTo($supplier, $contact);

        $this->contacts->delete($contact);

        return back()->with('success', 'Kontak supplier berhasil dihapus.');
    }

    /**
     * Guard against a contact id from one supplier being posted to another's
     * URL - the route binding alone would happily accept it.
     */
    private function assertBelongsTo(Supplier $supplier, SupplierContact $contact): void
    {
        abort_unless($contact->supplier_id === $supplier->getKey(), 404);
    }
}
