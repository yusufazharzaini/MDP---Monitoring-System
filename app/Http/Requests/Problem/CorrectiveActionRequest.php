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
            'action_date.before_or_equal' => __('requests.action_date_future'),
            'due_date.after_or_equal' => __('requests.action_due_before_date'),
            'description.min' => __('requests.action_description_min'),
        ];
    }
}
