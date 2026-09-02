# Panduan Pengguna

Cara menggunakan Material Delivery Performance Monitoring System sehari-hari.

> 🇬🇧 English version: [06-USER-GUIDE.md](06-USER-GUIDE.md)

Panduan ini ditulis untuk orang yang bekerja di dalam sistem — purchasing,
gudang, logistik dan manajemen — bukan untuk developer. Untuk cara sistem ini
dibangun, lihat [docs/01-ARCHITECTURE.md](01-ARCHITECTURE.md).

Nama menu dan tombol di panduan ini memakai istilah yang muncul ketika bahasa
antarmuka disetel ke **Bahasa Indonesia**.

---

## 1. Masuk ke sistem

Buka alamat aplikasi, lalu masuk dengan email dan kata sandi yang diberikan
administrator Anda.

**Tidak ada reset kata sandi mandiri.** Ini disengaja: akun dibuat oleh
administrator, jadi tidak ada tautan reset yang bisa disadap siapa pun. Kalau
Anda terkunci, minta administrator menetapkan kata sandi baru. Tindakan itu
sekaligus **mengakhiri semua sesi yang masih aktif** pada akun tersebut — itulah
yang membuatnya aman dipakai sebagai langkah pemulihan setelah dugaan
penyusupan.

Akun yang dinonaktifkan akan ditolak. Percobaan masuk yang gagal berulang kali
dibatasi per alamat email dan per IP.

### Mengganti bahasa antarmuka

Sistem ini tersedia dalam **Bahasa Indonesia, English, 日本語 dan 简体中文**.

- **Di layar login**, pemilih bahasa ada di bagian bawah kartu masuk. Anda tidak
  perlu punya akun untuk memakainya — kalau Anda tidak bisa membaca layarnya,
  ganti bahasanya lebih dulu.
- **Setelah masuk**, pemilihnya ada di bagian bawah sidebar kiri.

Pilihan Anda disimpan pada akun, jadi ikut ke peramban atau perangkat mana pun
yang Anda pakai untuk masuk.

**Apa yang berubah dan apa yang tidak.** Menu, tombol, judul kolom, status dan
pesan kesalahan semuanya berganti bahasa. Data yang diketik orang — nama
supplier, kode material, nomor PO, deskripsi problem, corrective action —
**tidak pernah** diterjemahkan. Itu disengaja: data tersebut adalah catatan yang
diaudit, dan dua orang tidak boleh melihat dua versi berbeda dari baris yang
sama.

**Dokumen cetak tetap satu bahasa** siapa pun yang mengekspornya, sehingga
laporan yang diarsipkan adalah dokumen yang sama. Ekspor Excel mengikuti bahasa
antarmuka Anda.

---

## 2. Apa yang boleh dilakukan peran Anda

| Peran | Biasanya | Dapat melakukan |
|---|---|---|
| **SUPER_ADMIN** | Pemilik sistem | Segalanya, termasuk pengguna, peran dan pengaturan |
| **ADMIN** | Administrator IT | Segalanya |
| **PURCHASING** | Staf purchasing | Purchase order dari awal sampai akhir, supplier, problem, evaluasi, laporan |
| **WAREHOUSE** | Staf gudang | Penerimaan barang, melaporkan problem, membaca PO dan master data |
| **LOGISTIC** | Staf logistik | Pengiriman dan problem, plus akses evaluasi dan laporan |
| **MANAGEMENT** | Plant manager | Persetujuan, evaluasi, laporan, log audit, pengaturan — tetapi bukan administrasi pengguna |
| **VIEWER** | Perencana produksi | Hanya baca, pada modul yang bisa dijangkau |
| **SUPPLIER** | Pihak eksternal | Tampilan terbatas atas pengiriman dan PO miliknya sendiri |

Kalau ada menu atau tombol yang tidak muncul, berarti peran Anda tidak memiliki
hak akses itu. Layar menyembunyikan apa yang tidak boleh Anda lakukan, bukan
menampilkan tombol yang nanti menolak Anda.

---

## 3. Alur kerja harian

```
Purchase Order  →  Pengiriman (penerimaan)  →  Problem (bila ada yang salah)
                            ↓
              Dasbor + Performa Supplier + Laporan
```

### 3.1 Purchase order

**Purchase Order → Buat PO.**

1. **Buat PO** — pilih supplier, pabrik, tanggal PO dan mata uang.
2. Tambahkan **baris item**: material, jumlah, harga satuan, tanggal rencana
   kirim, dan gudang penerima. Minimal satu baris, dan pilihan gudang dibatasi
   pada pabrik yang Anda pilih.
3. **Ajukan approval** setelah pesanan lengkap. Statusnya keluar dari Draf.
4. Orang yang berhak **menyetujui** akan menyetujuinya. Hanya PO yang sudah
   disetujui yang bisa diterima barangnya.

PO bergerak `Draf → Diajukan → Disetujui → Sebagian → Selesai`. PO bisa
**dibatalkan** di hampir semua titik, dengan alasan — tidak pernah dihapus.
Riwayat bisnis tetap tersimpan, dan log audit mencatat siapa mengubah apa.

### 3.2 Menerima pengiriman

**Pengiriman → Terima barang.** Ini layar terpenting di seluruh sistem: semua
angka yang dilaporkan dasbor dihitung dari apa yang dimasukkan di sini.

1. Pilih PO yang sudah disetujui.
2. Isi **No. Surat Jalan**, **Tanggal Terima**, dan bila perlu nama driver serta
   nomor kendaraan.
3. **Centang hanya baris yang benar-benar diterima pada pengiriman ini.**
   Kiriman sebagian itu normal — biarkan sisanya tidak tercentang dan terima
   kemudian pada PO yang sama.
4. Untuk tiap baris, isi jumlah yang diterima dan kondisinya (Baik, Rusak,
   Ditolak, Sebagian).

Sistem memperingatkan bila jumlah melebihi sisa yang masih terbuka pada PO.

**Koreksi.** Kalau ada yang salah masuk, gunakan **Koreksi** pada pengiriman
tersebut, jangan membuat pengiriman kedua. Koreksi tercatat; penghapusan tidak
dimungkinkan.

### 3.3 Bagaimana status pengiriman ditentukan

Anda tidak pernah mengisinya sendiri. Sistem menurunkan tiga status dari apa
yang Anda masukkan, dan dasbor menjumlahkannya.

**Ketepatan waktu** membandingkan tanggal terima dengan tanggal rencana,
**dibandingkan per tanggal saja** — datang jam berapa pun pada hari yang
dijadwalkan tetap dihitung tepat waktu.

| | Artinya |
|---|---|
| Tepat Waktu | Diterima pada atau sebelum tanggal rencana |
| Terlambat | Diterima setelahnya |

**Kuantitas** membandingkan jumlah diterima dengan jumlah dipesan.

| | Artinya |
|---|---|
| Kurang | Lebih sedikit dari yang dipesan |
| Penuh | Sesuai pesanan, masih dalam toleransi kelebihan kirim |
| Lebih | Di atas toleransi |

Toleransi kelebihan kirim adalah **persentase yang bisa dikonfigurasi**, bukan
angka mati, sehingga kelebihan kecil dihitung Penuh dan bukan kelebihan kirim.
Nilainya ada di pengaturan sistem.

**Status keseluruhan** menggabungkan keduanya: Tepat Waktu - Penuh, Tepat Waktu
- Kurang, Terlambat - Penuh, Terlambat - Kurang, atau Kelebihan Kirim.
Kelebihan kirim dilaporkan apa adanya, terlepas dari ketepatan waktunya.

### 3.4 Melaporkan problem

**Dari sebuah pengiriman → Laporkan problem**, atau **Analisis Masalah →
Laporkan Problem**.

Catat kategori, tingkat keparahan (Rendah / Sedang / Tinggi / Kritis), apa yang
terjadi, penanggung jawab (PIC), dan target penyelesaian. Deskripsi harus
benar-benar menjelaskan kejadian — minimal 10 karakter.

Lalu tambahkan **corrective action**: apa yang akan dilakukan, kapan, dan oleh
siapa. Tiap tindakan bergerak `Terbuka → Sedang Ditangani → Selesai`.

**Problem hanya bisa ditutup setelah minimal satu corrective action berstatus
Selesai.** Aturan ini dipaksakan sistem, bukan sekadar imbauan — supaya tidak
ada problem yang ditutup tanpa satu pun tindakan tercatat.

Lampiran (foto, surat jalan) bisa diunggah ke sebuah problem. Berkasnya disimpan
di penyimpanan privat dan hanya disajikan kepada orang yang berhak melihat
problem tersebut.

---

## 4. Membaca dasbor

**Ringkasan.** Kartu KPI menampilkan service rate, jumlah pengiriman, tepat
waktu, terlambat, kurang, dan material kritis untuk periode yang dipilih.
Gunakan bilah filter untuk mengubah periode, pabrik atau supplier; **Reset
filter** mengembalikan ke bawaan.

- **Trend Service Rate** — enam bulan terakhir. Bulan tanpa penerimaan **tidak**
  digambar sebagai 0%, karena "tidak ada pengiriman" bukan berarti "layanan 0%".
- **Pareto Masalah Delivery** — kategori problem mana yang menyumbang paling
  banyak masalah, dengan garis kumulatif. Kategori di sebelah kiri adalah "vital
  few" yang paling layak ditangani lebih dulu.
- **Detail Monitoring PO Delivery** — rincian per baris: dipesan vs diterima,
  jadwal vs aktual.

Semua ambang di balik angka-angka itu — target service rate, batas grade,
toleransi kelebihan kirim, aturan material kritis — tersimpan di basis data dan
bisa diubah, tidak ditanam mati di kode.

---

## 5. Performa supplier

**Performa Supplier** memeringkat supplier untuk satu periode dan memberi tiap
supplier grade (Sangat Baik / Baik / Cukup / Kurang) dari service rate dan
catatan problemnya. Buka satu supplier untuk melihat tren enam bulan, problem
per kategori, dan riwayat evaluasi bulanannya.

**Evaluasi Supplier** adalah catatan resmi yang disetujui.

1. **Hitung evaluasi bulanan** untuk satu periode. Ini menilai setiap supplier
   yang aktif pada periode itu dari sisi quality, quantity dan respons.
2. Evaluasi dimulai sebagai **Draf**. Periksa, dan **Hitung ulang** bila data
   dasarnya berubah.
3. **Setujui**. Evaluasi yang disetujui menjadi catatan resmi periode tersebut.
4. Kalau setelah itu harus diubah, **Buka kembali** — dan itu mewajibkan alasan,
   serta tercatat.

Evaluasi hanya dapat dibuat untuk bulan yang sudah berjalan.

---

## 6. Material kritis

**Material Kritis** mendaftar material yang berisiko: yang ditandai kritis di
master data, yang mengalami kekurangan jumlah, dan yang membawa problem kritis.
Gunakan untuk melihat di mana harus turun tangan sebelum lini produksi berhenti.

Material ditandai kritis pada data masternya ("Tandai sebagai material
critical"), dan aturan yang menaikkan tingkat risikonya bisa dikonfigurasi.

---

## 7. Laporan

**Laporan** menghasilkan lima laporan — pengiriman, purchase order, performa
supplier, masalah pengiriman, dan material kritis — untuk periode yang dipilih.

Empat format keluaran:

| Format | Untuk apa |
|---|---|
| **Excel (.xlsx)** | Analisis lanjutan. Mengikuti bahasa antarmuka Anda. |
| **CSV** | Memasok sistem lain |
| **PDF** | Arsip dan tanda tangan |
| **Cetak** | Dialog cetak bawaan peramban |

Dua batasan yang disengaja:

- **Rentang laporan maksimal dua tahun.** Persempit periodenya, atau unduh per
  tahun.
- **Ekspor dibatasi** sepuluh kali per menit, karena setiap ekspor membaca
  seluruh baris pada periode tersebut.

Kalau Anda bisa melihat laporan tetapi tidak bisa mengunduhnya, peran Anda punya
`report.view` tanpa `report.export`.

---

## 8. Notifikasi

Lonceng di bilah atas menampilkan notifikasi yang belum dibaca: purchase order
yang menunggu persetujuan Anda, dan rangkuman harian problem yang melewati due
date, yang datang tiap pagi.

Buka **Notifikasi** untuk membacanya, menandai satu sebagai dibaca, atau
menandai semua sudah dibaca.

---

## 9. Administrasi

Tersedia untuk SUPER_ADMIN dan ADMIN.

**Pengguna.** Buat akun, beri tepat satu peran, dan isi departemen, pabrik serta
nomor induk. Setiap pengguna wajib memiliki minimal satu peran.

Untuk mencabut akses seseorang, gunakan **Cabut akses**, bukan menghapus akun —
riwayatnya tetap utuh, dan akun bisa **Pulihkan** kemudian. Alamat email tetap
dianggap terpakai walaupun akunnya sudah dicabut.

Tidak seorang pun dapat menaikkan akunnya sendiri menjadi SUPER_ADMIN.

**Peran & Hak Akses.** Menampilkan modul mana yang bisa dijangkau tiap peran.

**Log Audit.** Catatan yang hanya bisa ditambah, berisi siapa mengubah apa dan
kapan, bisa disaring per pengguna, modul, aksi dan tanggal. Yang disimpan hanya
atribut yang benar-benar berubah. Menghapus isinya tidak dimungkinkan.

---

## 10. Kalau ada yang tidak beres

| Yang Anda lihat | Artinya |
|---|---|
| "Kolom ... wajib diisi", dalam bahasa Anda | Validasi biasa. Pesannya menyebut nama field sebagaimana tertulis di formulir. |
| Tombol yang Anda cari tidak ada | Peran Anda tidak memiliki hak akses itu. |
| "Akun Anda dapat melihat laporan tetapi tidak mengunduhnya" | `report.view` tanpa `report.export`. |
| Problem tidak mau ditutup | Belum ada corrective action berstatus Selesai. |
| Jumlah ditolak karena melebihi sisa | Anda menerima lebih banyak dari sisa yang masih terbuka pada PO. Periksa PO-nya, atau catat sebagai kelebihan kirim. |
| Tiba-tiba keluar dari sistem | Kata sandi akun Anda diubah, dan itu mengakhiri semua sesi pada akun tersebut. |
| Sama sekali tidak bisa login, tanpa pesan error | Tanyakan ke administrator apakah situs disajikan lewat HTTPS. Di produksi, cookie sesi hanya dikirim lewat koneksi aman. |

Untuk hal lain, administrator Anda dapat melihat log audit, yang mencatat apa
yang benar-benar terjadi — bukan apa yang diingat orang.
