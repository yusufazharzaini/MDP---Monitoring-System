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
        'audit_log' => 'Log Audit',
        'critical_material' => 'Material Kritis',
        'dashboard' => 'Dasbor',
        'delivery' => 'Pengiriman',
        'department' => 'Departemen',
        'material' => 'Material',
        'notification' => 'Notifikasi',
        'overview' => 'Ringkasan',
        'plant' => 'Pabrik',
        'problem_analysis' => 'Analisis Masalah',
        'purchase_order' => 'Purchase Order',
        'report' => 'Laporan',
        'role_permission' => 'Peran & Hak Akses',
        'soon' => 'Segera',
        'supplier' => 'Supplier',
        'supplier_evaluation' => 'Evaluasi Supplier',
        'supplier_performance' => 'Performa Supplier',
        'user' => 'Pengguna',
        'warehouse' => 'Gudang',
    ],

    'auth' => [
        'email' => 'Email',
        'email_placeholder' => 'nama@contoh.com',
        'password' => 'Kata Sandi',
        'remember_me' => 'Ingat saya di perangkat ini',
        'sign_in' => 'Masuk',
        'sign_in_subtitle' => 'Gunakan akun perusahaan Anda untuk melanjutkan.',
        'sign_in_title' => 'Masuk ke sistem',
        'sign_out' => 'Keluar',
        'tagline' => 'Monitor ketepatan pengiriman material dari supplier ke plant: service rate, keterlambatan, quantity shortage, analisa masalah, dan performa supplier.',
    ],

    'common' => [
        'actions' => 'Aksi',
        'address' => 'Alamat',
        'approve' => 'Setujui',
        'approved_by' => 'Disetujui oleh',
        'back' => 'Kembali',
        'cancel' => 'Batal',
        'cancel_record' => 'Batalkan',
        'cancellation_reason' => 'Alasan pembatalan',
        'category' => 'Kategori',
        'city' => 'Kota',
        'condition' => 'Kondisi',
        'create' => 'Tambah',
        'date' => 'Tanggal',
        'delete' => 'Hapus',
        'department' => 'Departemen',
        'description' => 'Deskripsi',
        'details' => 'Detail',
        'edit' => 'Ubah',
        'email' => 'Email',
        'grade' => 'Grade',
        'item' => 'Item',
        'language' => 'Bahasa',
        'module' => 'Modul',
        'name' => 'Nama',
        'no_data' => 'Belum ada data',
        'notes' => 'Catatan',
        'period' => 'Periode',
        'phone' => 'Telepon',
        'position' => 'Jabatan',
        'quantity' => 'Jumlah',
        'rank' => 'Peringkat',
        'reason' => 'Alasan',
        'role' => 'Peran',
        'root_cause' => 'Root Cause',
        'save' => 'Simpan',
        'save_changes' => 'Simpan perubahan',
        'search' => 'Cari',
        'severity' => 'Tingkat Keparahan',
        'status' => 'Status',
        'target' => 'Target',
        'to' => 's/d',
        'total' => 'Total',
        'unit' => 'Satuan',
    ],

    'entity' => [
        'critical_material' => 'Critical Material',
        'delivery' => 'Delivery',
        'material' => 'Material',
        'plant' => 'Plant',
        'supplier' => 'Supplier',
        'supplier_performance' => 'Performa Supplier',
        'user' => 'Pengguna',
        'warehouse' => 'Warehouse',
    ],

    'po' => [
        'lead_time_days' => 'Lead Time (hari)',
        'number' => 'No PO',
        'payment_term' => 'Payment Term',
        'pic_name' => 'Nama PIC',
        'pic_phone' => 'Telepon PIC',
        'qty' => 'Qty PO',
        'qty_received' => 'Qty Terima',
        'schedule' => 'Schedule',
    ],

    'action' => [
        'receive_goods' => 'Terima barang',
    ],

    'state' => [
        'late' => 'Terlambat',
        'on_time' => 'Tepat Waktu',
        'short' => 'Kurang',
    ],

    'metric' => [
        'service_rate' => 'Service Rate',
    ],

    'filter' => [
        'all_categories' => 'Semua kategori',
        'all_plants' => 'Semua plant',
        'all_status' => 'Semua status',
        'all_suppliers' => 'Semua supplier',
        'category' => 'Filter kategori',
        'material_category' => 'Filter kategori material',
        'plant' => 'Filter plant',
        'status' => 'Filter status',
        'supplier' => 'Filter supplier',
    ],

    'select' => [
        'category' => 'Pilih kategori',
        'plant' => 'Pilih plant',
    ],

    'msg' => [
        'check_marked_fields' => 'Periksa kembali isian yang ditandai di bawah ini.',
        'fill_then_save' => 'Lengkapi data berikut lalu simpan.',
        'no_evaluation' => 'Belum ada evaluasi',
        'no_problem' => 'Tidak ada problem',
    ],

];
