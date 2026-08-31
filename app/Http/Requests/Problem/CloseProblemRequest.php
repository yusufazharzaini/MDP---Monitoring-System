<?php

declare(strict_types=1);

namespace App\Http\Requests\Problem;

use Illuminate\Foundation\Http\FormRequest;

class CloseProblemRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // Optional: the closing note refines the root cause when the
            // investigation ended somewhere other than where it started.
            'note' => ['nullable', 'string', 'min:5', 'max:2000'],
        ];
    }
}
