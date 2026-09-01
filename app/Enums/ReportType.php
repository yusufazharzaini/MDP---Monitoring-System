<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

/**
 * The reports this system publishes.
 *
 * Each case carries its own label, description and filename stem, so the
 * catalogue screen, the export filename and the PDF header all read from one
 * place rather than restating each other.
 */
enum ReportType: string
{
    use HasEnumMetadata;

    case DELIVERY = 'delivery';
    case PURCHASE_ORDER = 'purchase-order';
    case SUPPLIER_PERFORMANCE = 'supplier-performance';
    case PROBLEM = 'problem';
    case CRITICAL_MATERIAL = 'critical-material';

    public function defaultLabel(): string
    {
        return match ($this) {
            self::DELIVERY => 'Laporan Delivery',
            self::PURCHASE_ORDER => 'Laporan Purchase Order',
            self::SUPPLIER_PERFORMANCE => 'Laporan Performa Supplier',
            self::PROBLEM => 'Laporan Problem Delivery',
            self::CRITICAL_MATERIAL => 'Laporan Critical Material',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DELIVERY => 'Seluruh baris penerimaan pada periode, dengan status ketepatan waktu dan quantity.',
            self::PURCHASE_ORDER => 'Purchase order beserta pemenuhannya, dinilai terhadap schedule delivery date.',
            self::SUPPLIER_PERFORMANCE => 'Service rate dan grade setiap supplier yang aktif pada periode.',
            self::PROBLEM => 'Problem delivery, kategori, severity, dan status corrective action.',
            self::CRITICAL_MATERIAL => 'Material yang memicu aturan critical beserta alasannya.',
        };
    }

    /**
     * The stem of the download filename; the period and extension are appended.
     */
    public function filename(): string
    {
        return match ($this) {
            self::DELIVERY => 'laporan-delivery',
            self::PURCHASE_ORDER => 'laporan-purchase-order',
            self::SUPPLIER_PERFORMANCE => 'laporan-performa-supplier',
            self::PROBLEM => 'laporan-problem',
            self::CRITICAL_MATERIAL => 'laporan-critical-material',
        };
    }

    /**
     * Whether the report is one row per transaction rather than one row per
     * aggregate. Row reports are streamed from a cursor because a year of
     * receipts is thousands of rows; aggregate reports are bounded by the
     * number of suppliers or materials.
     */
    public function isRowLevel(): bool
    {
        return in_array($this, [self::DELIVERY, self::PURCHASE_ORDER, self::PROBLEM], true);
    }
}
