<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\DataTransferObjects\DashboardFilter;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the dashboard filter before it reaches the query layer.
 *
 * Every id is checked against its table, so a filter can never smuggle an
 * unknown value into an aggregate and quietly return an empty dashboard.
 */
class DashboardFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('dashboard.view') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', 'date_format:Y-m'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'plant_id' => ['nullable', 'integer', 'exists:plants,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'material_category_id' => ['nullable', 'integer', 'exists:material_categories,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period.date_format' => __('requests.period_format'),
            'date_to.after_or_equal' => __('requests.date_to_before_from'),
        ];
    }

    public function toFilter(): DashboardFilter
    {
        return DashboardFilter::fromArray($this->validated());
    }
}
