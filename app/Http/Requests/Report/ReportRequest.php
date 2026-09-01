<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
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

    /**
     * The widest period a single report may cover.
     *
     * Two years spans every comparison the business actually makes - this year
     * against last - while stopping a request for a century from opening a
     * cursor over the entire history in one go.
     */
    public const MAX_SPAN_DAYS = 731;

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
            'date_to' => [
                'nullable', 'date_format:Y-m-d', 'after_or_equal:date_from',
                // Bounded: an unbounded range is a cursor over the whole table.
                'before_or_equal:'.$this->latestAllowedEnd(),
            ],
            'plant_id' => ['nullable', 'integer', 'exists:plants,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'material_category_id' => ['nullable', 'integer', 'exists:material_categories,id'],
            'status' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * The latest end date the given start allows.
     *
     * Returned as a date so the rule reads naturally in the message; with no
     * start date the filter falls back to a single month anyway.
     */
    private function latestAllowedEnd(): string
    {
        $from = $this->string('date_from')->toString();

        if ($from === '' || ! strtotime($from)) {
            return Carbon::now()->addYears(10)->toDateString();
        }

        return Carbon::parse($from)->addDays(self::MAX_SPAN_DAYS)->toDateString();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_to.before_or_equal' => __('requests.report_span_too_wide', ['years' => (int) round(self::MAX_SPAN_DAYS / 365)]),
            'type.Illuminate\Validation\Rules\Enum' => __('requests.report_type_unknown'),
            'format.in' => __('requests.report_format_invalid', ['values' => implode(', ', self::FORMATS)]),
            'date_to.after_or_equal' => __('requests.date_to_before_from'),
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
