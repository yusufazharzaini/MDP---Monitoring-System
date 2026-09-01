<?php

declare(strict_types=1);

namespace App\Http\Requests\Problem;

use App\Enums\ProblemSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryProblemRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // The supplier is never accepted from the form - ProblemService
            // takes it from the delivery - so it is absent here on purpose.
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'problem_category_id' => ['required', 'integer', 'exists:problem_categories,id'],
            // A problem is observed, so it cannot be dated in the future; the
            // service re-checks this and also refuses a date before the receipt.
            'problem_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'severity' => ['required', Rule::enum(ProblemSeverity::class)],
            'root_cause' => ['nullable', 'string', 'max:2000'],
            'pic' => ['nullable', 'string', 'max:100'],
            // Left empty, the service defaults it to the severity's resolution
            // window, which is what makes "overdue" meaningful.
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:problem_date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'problem_date.before_or_equal' => __('requests.problem_date_future'),
            'due_date.after_or_equal' => __('requests.problem_due_before_date'),
            'description.min' => __('requests.problem_description_min'),
        ];
    }
}
