<?php

declare(strict_types=1);

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class GenerateEvaluationRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // A scorecard measures a month that has happened. The service
            // re-checks the range so the rule holds for callers with no form.
            'period' => ['required', 'string', 'date_format:Y-m', 'before_or_equal:'.Carbon::now()->format('Y-m')],
            // Absent means "every supplier active in the month".
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period.required' => __('requests.evaluation_period_req'),
            'period.date_format' => __('requests.period_format'),
            'period.before_or_equal' => __('requests.evaluation_period_future'),
        ];
    }

    public function year(): int
    {
        return (int) substr($this->string('period')->toString(), 0, 4);
    }

    public function month(): int
    {
        return (int) substr($this->string('period')->toString(), 5, 2);
    }
}
