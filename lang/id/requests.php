<?php

declare(strict_types=1);

/**
 * Messages a form request overrides.
 *
 * These are business rules stated in words - "critical stock must be less than
 * or equal to minimum stock" - rather than generic rule failures, so they live
 * here rather than in validation.php, and each form request looks them up by
 * key instead of carrying the sentence itself.
 */
return [
    'action_date_future' => 'Tanggal corrective action tidak boleh berada di masa depan.',
    'action_description_min' => 'Corrective action harus dijelaskan, minimal 10 karakter.',
    'action_due_before_date' => 'Target penyelesaian tidak boleh mendahului tanggal corrective action.',
    'critical_stock_vs_min' => 'Critical stock harus lebih kecil atau sama dengan minimum stock.',
    'date_to_before_from' => 'Tanggal akhir tidak boleh mendahului tanggal awal.',
    'delivery_date_future' => 'Tanggal delivery tidak boleh berada di masa depan.',
    'delivery_needs_lines' => 'Delivery harus memiliki minimal satu baris penerimaan.',
    'evaluation_period_future' => 'Evaluasi hanya dapat dibuat untuk bulan yang sudah berjalan.',
    'evaluation_period_req' => 'Periode evaluasi wajib diisi.',
    'password_mismatch' => 'Konfirmasi kata sandi tidak cocok.',
    'period_format' => 'Periode harus dalam format YYYY-MM.',
    'po_needs_lines' => 'Purchase order harus memiliki minimal satu baris item.',
    'po_qty_positive' => 'Quantity harus lebih besar dari 0.',
    'po_schedule_before_date' => 'Schedule delivery tidak boleh mendahului tanggal PO.',
    'problem_date_future' => 'Tanggal problem tidak boleh berada di masa depan.',
    'problem_description_min' => 'Deskripsi problem harus menjelaskan kejadian, minimal 10 karakter.',
    'problem_due_before_date' => 'Target penyelesaian tidak boleh mendahului tanggal problem.',
    'report_format_invalid' => 'Format laporan harus salah satu dari: :values.',
    'report_span_too_wide' => 'Rentang laporan maksimal :years tahun. Persempit periodenya atau unduh per tahun.',
    'report_type_unknown' => 'Jenis laporan tidak dikenal.',
    'user_email_taken' => 'Alamat email ini sudah terdaftar, termasuk pada akun yang dinonaktifkan.',
    'user_needs_role' => 'Pengguna harus memiliki minimal satu peran.',
];
