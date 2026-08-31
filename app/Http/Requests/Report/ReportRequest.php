<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The report filter, and which report it applies to.
 *
 * `type` and `format` are validated against their enums rather than matched in
 * the controller, so an unknown value is a 422 rather than something the
 * exporter has to defend against.
 */
class ReportRequest extends FormRequest
{
    public const FORMATS = ['xlsx', 'csv', 'pdf', 'print'];

    public function authorize(): bool
    {
        return $this->user()?->can('report.view') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::enum(ReportType::class)],
            'format' => ['nullable', Rule::in(self::FORMATS)],
            'period' => ['nullable', 'string', 'date_format:Y-m'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'plant_id' => ['nullable', 'integer', 'exists:plants,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'material_category_id' => ['nullable', 'integer', 'exists:material_categories,id'],
            'status' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.Illuminate\Validation\Rules\Enum' => 'Jenis laporan tidak dikenal.',
            'format.in' => 'Format laporan harus salah satu dari: '.implode(', ', self::FORMATS).'.',
            'date_to.after_or_equal' => 'Tanggal akhir tidak boleh mendahului tanggal awal.',
        ];
    }

    public function reportType(): ReportType
    {
        return ReportType::tryFrom($this->string('type')->toString()) ?? ReportType::DELIVERY;
    }

    /**
     * Not named format(): Illuminate\Http\Request already declares
     * format($default = 'html') for content negotiation, and shadowing it with
     * an incompatible signature is a fatal error at class load.
     */
    public function exportFormat(): string
    {
        $format = $this->string('format')->toString();

        return in_array($format, self::FORMATS, true) ? $format : 'xlsx';
    }

    public function toFilter(): DashboardFilter
    {
        return DashboardFilter::fromArray($this->validated());
    }
}
