<?php

declare(strict_types=1);

/**
 * Validation messages.
 *
 * Covers every rule the application's form requests actually use, plus the
 * common ones. A rule with no entry here falls back to English rather than
 * rendering a key, so an untranslated exotic rule degrades to readable text.
 *
 * `attributes` matters as much as the messages: without it a translated
 * sentence still names the column, and the reader gets "Kolom critical_stock
 * wajib diisi" instead of a field they recognise from the form.
 */
return [

    'between' => [
        'array' => 'Kolom :attribute harus memiliki antara :min dan :max item.',
        'file' => 'Kolom :attribute harus antara :min dan :max kilobita.',
        'numeric' => 'Kolom :attribute harus antara :min dan :max.',
        'string' => 'Kolom :attribute harus antara :min dan :max karakter.',
    ],

    'gt' => [
        'array' => 'Kolom :attribute harus lebih dari :value item.',
        'file' => 'Kolom :attribute harus lebih besar dari :value kilobita.',
        'numeric' => 'Kolom :attribute harus lebih besar dari :value.',
        'string' => 'Kolom :attribute harus lebih dari :value karakter.',
    ],

    'gte' => [
        'array' => 'Kolom :attribute harus memiliki :value item atau lebih.',
        'file' => 'Kolom :attribute harus lebih besar dari atau sama dengan :value kilobita.',
        'numeric' => 'Kolom :attribute harus lebih besar dari atau sama dengan :value.',
        'string' => 'Kolom :attribute harus lebih dari atau sama dengan :value karakter.',
    ],

    'lt' => [
        'array' => 'Kolom :attribute harus kurang dari :value item.',
        'file' => 'Kolom :attribute harus lebih kecil dari :value kilobita.',
        'numeric' => 'Kolom :attribute harus lebih kecil dari :value.',
        'string' => 'Kolom :attribute harus kurang dari :value karakter.',
    ],

    'lte' => [
        'array' => 'Kolom :attribute tidak boleh lebih dari :value item.',
        'file' => 'Kolom :attribute harus lebih kecil dari atau sama dengan :value kilobita.',
        'numeric' => 'Kolom :attribute harus lebih kecil dari atau sama dengan :value.',
        'string' => 'Kolom :attribute harus kurang dari atau sama dengan :value karakter.',
    ],

    'max' => [
        'array' => 'Kolom :attribute tidak boleh lebih dari :max item.',
        'file' => 'Kolom :attribute tidak boleh lebih dari :max kilobita.',
        'numeric' => 'Kolom :attribute tidak boleh lebih dari :max.',
        'string' => 'Kolom :attribute tidak boleh lebih dari :max karakter.',
    ],

    'min' => [
        'array' => 'Kolom :attribute minimal :min item.',
        'file' => 'Kolom :attribute minimal :min kilobita.',
        'numeric' => 'Kolom :attribute minimal :min.',
        'string' => 'Kolom :attribute minimal :min karakter.',
    ],

    'size' => [
        'array' => 'Kolom :attribute harus memiliki :size item.',
        'file' => 'Kolom :attribute harus berukuran :size kilobita.',
        'numeric' => 'Kolom :attribute harus bernilai :size.',
        'string' => 'Kolom :attribute harus :size karakter.',
    ],

    'after_or_equal' => 'Kolom :attribute harus berupa tanggal setelah atau sama dengan :date.',
    'array' => 'Kolom :attribute harus berupa larik.',
    'before_or_equal' => 'Kolom :attribute harus berupa tanggal sebelum atau sama dengan :date.',
    'boolean' => 'Kolom :attribute harus bernilai benar atau salah.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => 'Kolom :attribute harus berupa tanggal yang valid.',
    'date_format' => 'Kolom :attribute harus sesuai format :format.',
    'distinct' => 'Kolom :attribute memiliki nilai yang duplikat.',
    'email' => 'Kolom :attribute harus berupa alamat email yang valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'file' => 'Kolom :attribute harus berupa berkas.',
    'image' => 'Kolom :attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
    'mimes' => 'Kolom :attribute harus berupa berkas bertipe: :values.',
    'not_in' => ':attribute yang dipilih tidak valid.',
    'numeric' => 'Kolom :attribute harus berupa angka.',
    'present' => 'Kolom :attribute harus ada.',
    'regex' => 'Format kolom :attribute tidak valid.',
    'required' => 'Kolom :attribute wajib diisi.',
    'required_if' => 'Kolom :attribute wajib diisi bila :other bernilai :value.',
    'string' => 'Kolom :attribute harus berupa teks.',
    'unique' => ':attribute sudah digunakan.',
    'url' => 'Kolom :attribute harus berupa URL yang valid.',

    'custom' => [],

    'attributes' => [
        'action_date' => 'tanggal tindakan',
        'address' => 'alamat',
        'category_id' => 'kategori',
        'city' => 'kota',
        'code' => 'kode',
        'country' => 'negara',
        'critical_stock' => 'critical stock',
        'currency' => 'mata uang',
        'date_from' => 'tanggal awal',
        'date_to' => 'tanggal akhir',
        'delivery_date' => 'tanggal delivery',
        'delivery_id' => 'delivery',
        'department_id' => 'departemen',
        'description' => 'deskripsi',
        'do_number' => 'nomor surat jalan',
        'driver_name' => 'nama driver',
        'due_date' => 'target penyelesaian',
        'email' => 'email',
        'employee_code' => 'nomor induk',
        'is_critical' => 'material critical',
        'lead_time_days' => 'lead time (hari)',
        'locale' => 'bahasa',
        'material_id' => 'material',
        'minimum_stock' => 'minimum stock',
        'name' => 'nama',
        'notes' => 'catatan',
        'password' => 'kata sandi',
        'phone' => 'telepon',
        'plant_id' => 'plant',
        'po_date' => 'tanggal PO',
        'position' => 'jabatan',
        'problem_id' => 'problem',
        'quantity' => 'jumlah',
        'reason' => 'alasan',
        'role' => 'peran',
        'root_cause' => 'root cause',
        'severity' => 'tingkat keparahan',
        'status' => 'status',
        'supplier_id' => 'supplier',
        'unit_price' => 'harga satuan',
        'uom_id' => 'satuan',
        'user_id' => 'pengguna',
        'warehouse_id' => 'warehouse',
    ],

];
