<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RecordStatus;
use App\Enums\UomType;
use App\Models\Department;
use App\Models\MaterialCategory;
use App\Models\ProblemCategory;
use App\Models\Uom;
use Illuminate\Database\Seeder;

/**
 * Reference data that the transactional modules depend on: departments, units
 * of measure, material categories and problem categories.
 *
 * Idempotent - re-running updates in place instead of duplicating.
 */
class MasterDataSeeder extends Seeder
{
    /**
     * @var array<int, array{code: string, name: string}>
     */
    private const DEPARTMENTS = [
        ['code' => 'PUR', 'name' => 'Purchasing'],
        ['code' => 'WHS', 'name' => 'Warehouse'],
        ['code' => 'LOG', 'name' => 'Logistic'],
        ['code' => 'PRD', 'name' => 'Production'],
        ['code' => 'QAS', 'name' => 'Quality Assurance'],
        ['code' => 'MGT', 'name' => 'Management'],
    ];

    /**
     * @var array<int, array{code: string, name: string, type: UomType}>
     */
    private const UOMS = [
        ['code' => 'KG', 'name' => 'Kilogram', 'type' => UomType::WEIGHT],
        ['code' => 'TON', 'name' => 'Ton', 'type' => UomType::WEIGHT],
        ['code' => 'PCS', 'name' => 'Pieces', 'type' => UomType::QTY],
        ['code' => 'BOX', 'name' => 'Box', 'type' => UomType::QTY],
        ['code' => 'LTR', 'name' => 'Liter', 'type' => UomType::VOLUME],
        ['code' => 'MTR', 'name' => 'Meter', 'type' => UomType::LENGTH],
    ];

    /**
     * @var array<int, array{code: string, name: string}>
     */
    private const MATERIAL_CATEGORIES = [
        ['code' => 'RESIN', 'name' => 'Resin'],
        ['code' => 'ADDTV', 'name' => 'Additive'],
        ['code' => 'MSTBT', 'name' => 'Masterbatch'],
        ['code' => 'METAL', 'name' => 'Metal'],
        ['code' => 'PACK', 'name' => 'Packaging'],
        ['code' => 'CHEM', 'name' => 'Chemical'],
    ];

    /**
     * @var array<int, array{code: string, name: string, description: string}>
     */
    private const PROBLEM_CATEGORIES = [
        ['code' => 'LATE_DELIVERY', 'name' => 'Late Delivery', 'description' => 'Material diterima melewati schedule delivery date.'],
        ['code' => 'SHORT_DELIVERY', 'name' => 'Quantity Kurang', 'description' => 'Quantity diterima lebih kecil dari quantity PO.'],
        ['code' => 'WRONG_MATERIAL', 'name' => 'Material Salah', 'description' => 'Material yang dikirim tidak sesuai dengan PO.'],
        ['code' => 'DOCUMENT_PROBLEM', 'name' => 'Dokumen Tidak Lengkap', 'description' => 'Surat jalan atau dokumen pendukung tidak lengkap.'],
        ['code' => 'QUALITY_PROBLEM', 'name' => 'Masalah Kualitas', 'description' => 'Material tidak memenuhi spesifikasi kualitas.'],
        ['code' => 'PACKAGING_PROBLEM', 'name' => 'Masalah Packaging', 'description' => 'Kemasan rusak atau tidak sesuai standar.'],
        ['code' => 'SCHEDULE_PROBLEM', 'name' => 'Delivery Tidak Sesuai Schedule', 'description' => 'Pengiriman tidak mengikuti jadwal yang disepakati.'],
        ['code' => 'OTHER', 'name' => 'Lain-lain', 'description' => 'Masalah delivery lain di luar kategori di atas.'],
    ];

    public function run(): void
    {
        foreach (self::DEPARTMENTS as $department) {
            Department::query()->updateOrCreate(
                ['code' => $department['code']],
                ['name' => $department['name'], 'status' => RecordStatus::ACTIVE],
            );
        }

        foreach (self::UOMS as $uom) {
            Uom::query()->updateOrCreate(
                ['code' => $uom['code']],
                ['name' => $uom['name'], 'type' => $uom['type'], 'status' => RecordStatus::ACTIVE],
            );
        }

        foreach (self::MATERIAL_CATEGORIES as $category) {
            MaterialCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                ['name' => $category['name'], 'status' => RecordStatus::ACTIVE],
            );
        }

        foreach (self::PROBLEM_CATEGORIES as $category) {
            ProblemCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'status' => RecordStatus::ACTIVE,
                ],
            );
        }
    }
}
