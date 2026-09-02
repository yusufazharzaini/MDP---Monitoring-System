<?php

declare(strict_types=1);

/**
 * Enum labels - the controlled vocabulary an operator reads all day.
 *
 * Resolved by App\Enums\Concerns\HasEnumMetadata::label(). Keys mirror the
 * enum class name and its case value, so a new case shows up as a missing key
 * in LocaleTest rather than silently rendering title-cased English.
 */
return [

    'AuditAction' => [
        'CREATED' => 'Dibuat',
        'UPDATED' => 'Diperbarui',
        'DELETED' => 'Dihapus',
        'RESTORED' => 'Dipulihkan',
        'SUBMITTED' => 'Diajukan',
        'APPROVED' => 'Disetujui',
        'CANCELLED' => 'Dibatalkan',
        'CLOSED' => 'Ditutup',
        'IMPORTED' => 'Diimpor',
        'EXPORTED' => 'Diekspor',
        'LOGIN' => 'Masuk',
        'LOGOUT' => 'Keluar',
    ],

    'CorrectiveActionStatus' => [
        'OPEN' => 'Terbuka',
        'IN_PROGRESS' => 'Sedang Ditangani',
        'DONE' => 'Selesai',
    ],

    'DeliveryItemCondition' => [
        'GOOD' => 'Baik',
        'DAMAGED' => 'Rusak',
        'REJECTED' => 'Ditolak',
        'PARTIAL' => 'Sebagian',
    ],

    'DeliveryStatus' => [
        'PENDING' => 'Menunggu',
        'RECEIVED' => 'Diterima',
        'PARTIAL' => 'Sebagian',
        'COMPLETED' => 'Selesai',
        'CANCELLED' => 'Dibatalkan',
    ],

    'EvaluationStatus' => [
        'DRAFT' => 'Draf',
        'APPROVED' => 'Disetujui',
    ],

    'OverallDeliveryStatus' => [
        'PENDING' => 'Menunggu',
        'ON_TIME_FULL' => 'Tepat Waktu - Penuh',
        'LATE_FULL' => 'Terlambat - Penuh',
        'ON_TIME_SHORT' => 'Tepat Waktu - Kurang',
        'LATE_SHORT' => 'Terlambat - Kurang',
        'OVER_DELIVERY' => 'Kelebihan Kirim',
    ],

    'ProblemSeverity' => [
        'LOW' => 'Rendah',
        'MEDIUM' => 'Sedang',
        'HIGH' => 'Tinggi',
        'CRITICAL' => 'Kritis',
    ],

    'ProblemStatus' => [
        'OPEN' => 'Terbuka',
        'IN_PROGRESS' => 'Sedang Ditangani',
        'CLOSED' => 'Ditutup',
        'CANCELLED' => 'Dibatalkan',
    ],

    'PurchaseOrderStatus' => [
        'DRAFT' => 'Draf',
        'SUBMITTED' => 'Diajukan',
        'APPROVED' => 'Disetujui',
        'PARTIAL' => 'Sebagian',
        'COMPLETED' => 'Selesai',
        'CANCELLED' => 'Dibatalkan',
    ],

    'QuantityStatus' => [
        'PENDING' => 'Menunggu',
        'SHORT' => 'Kurang',
        'FULL' => 'Penuh',
        'OVER' => 'Lebih',
    ],

    'RecordStatus' => [
        'ACTIVE' => 'Aktif',
        'INACTIVE' => 'Nonaktif',
    ],

    'ReportType' => [
        'delivery' => 'Laporan Pengiriman',
        'purchase-order' => 'Laporan Purchase Order',
        'supplier-performance' => 'Laporan Performa Supplier',
        'problem' => 'Laporan Masalah Pengiriman',
        'critical-material' => 'Laporan Material Kritis',
    ],

    'RiskLevel' => [
        'LOW' => 'Rendah',
        'MEDIUM' => 'Sedang',
        'HIGH' => 'Tinggi',
        'CRITICAL' => 'Kritis',
    ],

    'SettingType' => [
        'STRING' => 'Teks',
        'INTEGER' => 'Bilangan Bulat',
        'DECIMAL' => 'Desimal',
        'BOOLEAN' => 'Boolean',
        'JSON' => 'Json',
    ],

    'SupplierGrade' => [
        'EXCELLENT' => 'Sangat Baik',
        'GOOD' => 'Baik',
        'AVERAGE' => 'Cukup',
        'POOR' => 'Kurang',
    ],

    'SupplierStatus' => [
        'ACTIVE' => 'Aktif',
        'INACTIVE' => 'Nonaktif',
        'BLACKLISTED' => 'Masuk Daftar Hitam',
    ],

    'SupplierType' => [
        'LOCAL' => 'Lokal',
        'IMPORT' => 'Impor',
        'TOLLING' => 'Makloon',
        'SERVICE' => 'Jasa',
    ],

    'TimelinessStatus' => [
        'PENDING' => 'Menunggu',
        'ON_TIME' => 'Tepat Waktu',
        'LATE' => 'Terlambat',
    ],

    'UomType' => [
        'QTY' => 'Jumlah',
        'WEIGHT' => 'Berat',
        'VOLUME' => 'Volume',
        'LENGTH' => 'Panjang',
        'AREA' => 'Luas',
        'TIME' => 'Waktu',
    ],

];
