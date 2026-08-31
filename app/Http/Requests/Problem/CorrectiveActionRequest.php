<?php

declare(strict_types=1);

namespace App\Http\Requests\Problem;

use Illuminate\Foundation\Http\FormRequest;

class CorrectiveActionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // An action already taken, so never in the future; the service also
            // refuses a date before the problem was reported.
            'action_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'action_by' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:action_date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action_date.before_or_equal' => 'Tanggal corrective action tidak boleh berada di masa depan.',
            'due_date.after_or_equal' => 'Target penyelesaian tidak boleh mendahului tanggal corrective action.',
            'description.min' => 'Corrective action harus dijelaskan, minimal 10 karakter.',
        ];
    }
}
