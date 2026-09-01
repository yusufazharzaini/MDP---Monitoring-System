<?php

declare(strict_types=1);

/**
 * Interface strings.
 *
 * The whole file is handed to the browser through Inertia's shared props for
 * the active locale, so one language's worth of text crosses the wire, not four.
 * Business data - supplier names, material codes, problem descriptions - is
 * never translated: it is the record the audit trail is kept against.
 */
return [

    'nav' => [
        'overview' => 'Ringkasan',
        'dashboard' => 'Dasbor',
        'supplier' => 'Supplier',
        'plant' => 'Pabrik',
        'warehouse' => 'Gudang',
        'material' => 'Material',
        'department' => 'Departemen',
        'purchase_order' => 'Purchase Order',
        'delivery' => 'Pengiriman',
        'problem_analysis' => 'Analisis Masalah',
        'supplier_performance' => 'Performa Supplier',
        'supplier_evaluation' => 'Evaluasi Supplier',
        'critical_material' => 'Material Kritis',
        'report' => 'Laporan',
        'user' => 'Pengguna',
        'role_permission' => 'Peran & Hak Akses',
        'audit_log' => 'Log Audit',
        'notification' => 'Notifikasi',
        'soon' => 'Segera',
    ],

    'auth' => [
        'email' => 'Email',
        'password' => 'Kata Sandi',
        'sign_in' => 'Masuk',
        'sign_out' => 'Keluar',
        'email_placeholder' => 'nama@contoh.com',
        'sign_in_title' => 'Masuk ke sistem',
        'sign_in_subtitle' => 'Gunakan akun perusahaan Anda untuk melanjutkan.',
        'remember_me' => 'Ingat saya di perangkat ini',
        'tagline' => 'Monitor ketepatan pengiriman material dari supplier ke plant: service rate, keterlambatan, quantity shortage, analisa masalah, dan performa supplier.',
    ],

    'common' => [
        'language' => 'Bahasa',
        'search' => 'Cari',
        'save' => 'Simpan',
        'cancel' => 'Batal',
        'create' => 'Tambah',
        'edit' => 'Ubah',
        'delete' => 'Hapus',
        'back' => 'Kembali',
        'actions' => 'Aksi',
        'no_data' => 'Belum ada data',
        'to' => 's/d',
    ],

];
