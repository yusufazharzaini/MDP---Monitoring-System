<?php

declare(strict_types=1);

namespace App\Http\Requests\Performance;

use App\DataTransferObjects\DashboardFilter;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The period filter shared by the ranking, the scorecard and the critical
 * material list.
 *
 * The same shape as the dashboard filter, behind `report.view` instead of
 * `dashboard.view`, because these screens are the reporting side of the same
 * aggregates. Every id is checked against its table so a filter cannot smuggle
 * an unknown value into an aggregate and quietly return an empty page.
 */
class PerformanceFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('report.view') ?? false;
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
            'material_category_id' => ['nullable', 'integer', 'exists:material_categories,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period.date_format' => 'Periode harus dalam format YYYY-MM.',
            'date_to.after_or_equal' => 'Tanggal akhir tidak boleh mendahului tanggal awal.',
        ];
    }

    public function toFilter(): DashboardFilter
    {
        return DashboardFilter::fromArray($this->validated());
    }
}
