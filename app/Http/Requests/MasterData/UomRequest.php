<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Enums\RecordStatus;
use App\Enums\UomType;
use Illuminate\Validation\Rule;

class UomRequest extends MasterDataRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', $this->uniqueIgnoringSelf('uoms', 'code')],
            'name' => ['required', 'string', 'max:50'],
            'type' => ['required', Rule::enum(UomType::class)],
            'status' => ['required', Rule::enum(RecordStatus::class)],
        ];
    }
}
