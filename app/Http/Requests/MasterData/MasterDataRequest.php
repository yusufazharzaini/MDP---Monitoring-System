<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Shared validation plumbing for master-data forms.
 *
 * Authorization is delegated to the policy, so a request never re-states a
 * permission rule that already lives in one place.
 */
abstract class MasterDataRequest extends FormRequest
{
    /**
     * The record being edited, or null when creating.
     */
    protected function record(): ?Model
    {
        $parameter = $this->route()?->parameters() ?? [];

        foreach ($parameter as $value) {
            if ($value instanceof Model) {
                return $value;
            }
        }

        return null;
    }

    /**
     * A unique rule that ignores the record being edited, so saving a form
     * without changing its code does not collide with itself.
     */
    protected function uniqueIgnoringSelf(string $table, string $column): Unique
    {
        $rule = Rule::unique($table, $column)->whereNull('deleted_at');
        $record = $this->record();

        return $record === null ? $rule : $rule->ignore($record->getKey());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'kode',
            'name' => 'nama',
            'status' => 'status',
            'plant_id' => 'plant',
            'supplier_id' => 'supplier',
            'category_id' => 'kategori',
            'uom_id' => 'satuan',
        ];
    }
}
