<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Enums\RecordStatus;
use Illuminate\Validation\Rule;

class DepartmentRequest extends MasterDataRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', $this->uniqueIgnoringSelf('departments', 'code')],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::enum(RecordStatus::class)],
        ];
    }
}
