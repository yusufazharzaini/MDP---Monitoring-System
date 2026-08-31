<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\SupplierContact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SupplierContactService extends MasterDataService
{
    protected function modelClass(): string
    {
        return SupplierContact::class;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes): Model {
            /** @var SupplierContact $contact */
            $contact = parent::create($attributes);
            $this->enforceSinglePrimary($contact);

            return $contact;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $record, array $attributes): Model
    {
        return DB::transaction(function () use ($record, $attributes): Model {
            /** @var SupplierContact $contact */
            $contact = parent::update($record, $attributes);
            $this->enforceSinglePrimary($contact);

            return $contact;
        });
    }

    /**
     * A supplier has at most one primary contact.
     *
     * MySQL cannot express a partial unique index, so the invariant is held
     * here - inside the same transaction as the write, which is what stops two
     * concurrent edits both claiming the flag.
     */
    private function enforceSinglePrimary(SupplierContact $contact): void
    {
        if (! $contact->is_primary) {
            return;
        }

        SupplierContact::query()
            ->where('supplier_id', $contact->supplier_id)
            ->whereKeyNot($contact->getKey())
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
