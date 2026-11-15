# DAFTAR TUGAS & WORKFLOW TRACKING: SISTEM MONITORING TTE & EMAIL DINAS

**Versi Dokumen:** 3.1.0-PROD (Laravel 13 + Zero-Trust VPN Gateway)  
**Basis Arsitektur:** Clean Domain Architecture (Laravel 13 / PHP 8.3 + PostgreSQL 16 + Redis 7 + Nginx Hardened + VPN Gateway)  
**Status Pemantauan:** ✅ **100% RAMPUNG (62 / 62 Tugas Selesai)**  

---

## Matriks Rekapitulasi Tahapan Proyek

| Tahap | Deskripsi Modul / Komponen | Total Item | Selesai | Persentase | Status |
| :---: | :--- | :---: | :---: | :---: | :---: |
| **Tahap 1** | Inisialisasi Proyek, Docker Environment & Boilerplate Foundation | 5 | 5 | 100% | ✅ Selesai |
| **Tahap 2** | Database Migrations, PostgreSQL 16 DDL, Domain Models & Seeders | 12 | 12 | 100% | ✅ Selesai |
| **Tahap 3** | Core Services, Security Infrastructure & Redis Queue | 7 | 7 | 100% | ✅ Selesai |
| **Tahap 4** | IAM, Autentikasi Superadmin & TOTP MFA | 3 | 3 | 100% | ✅ Selesai |
| **Tahap 5** | Backend Actions, FormRequests, Controllers & Schedulers | 8 | 8 | 100% | ✅ Selesai |
| **Tahap 6** | Frontend Zero-CDN Asset Pipeline & Carbon Flat UI Integration | 6 | 6 | 100% | ✅ Selesai |
| **Tahap 7** | Testing, Verifikasi & Audit Hardening | 4 | 4 | 100% | ✅ Selesai |
| **Tahap 8** | Clone Demo Environment, Dummy Data & Demo Documentation | 5 | 5 | 100% | ✅ Selesai |
| **Tahap 9** | Upgrade Laravel 13 & Isolasi Rute Admin Berbasis VPN Gateway | 8 | 8 | 100% | ✅ Selesai |
| **Tahap 10**| Pembersihan Data Testing & Finalisasi Dokumentasi Deployment V4 | 4 | 4 | 100% | ✅ Selesai |
| **Tahap 11**| Remediasi Temuan Audit Kode Program & Hardening Produksi Terpadu | 6 | 6 | 100% | ✅ Selesai |
| **Tahap 12**| Metrik Dashboard Top 10 SKPD Berbasis Persentase Pegawai (V5.1) | 7 | 7 | 100% | ✅ Selesai |
| **Tahap 13**| Modul Analitik Capaian Adopsi TTE Seluruh SKPD & Ekspor CSV (V5.2) | 5 | 5 | 100% | ✅ Selesai |
| **Tahap 14**| Core Dual-Track Backend & Dynamic Parameter Wiring (V6.1) | 6 | 6 | 100% | ✅ Selesai |
| **Tahap 15**| Validasi Masal, Mekanisme Undo & Standardisasi Terminologi (V6.2) | 7 | 7 | 100% | ✅ Selesai |
| **Tahap 16**| Portal Publik, Landing Page Agregat, Dual-track Form UI & Recovery (V6.3) | 7 | 7 | 100% | ✅ Selesai |
| **Tahap 17**| Admin Panel Settings, Pengelolaan Kunjungan & Target Bulanan (V6.4) | 6 | 6 | 100% | ✅ Selesai |
| **Tahap 18**| Fitur Otomasi, Ekspor PDF Resmi & Batch Importer Data Historis (V6.5) | 6 | 6 | 100% | ✅ Selesai |
| **TOTAL** | **Keseluruhan Siklus Pembangunan, Isolasi VPN, Remediasi & V6** | **107** | **107** | **100.0%** | ✅ **Selesai Penuh** |

---

> **Catatan Sebelumnya:**
> Pada rilis V4 (Tahap 1 s/d Tahap 10 per 23 Agustus 2026), seluruh 62 tugas awal telah selesai 100% meliputi pembentukan sistem, isolasi VPN Gateway, dan verifikasi test suite dasar. Tahap 11 ditambahkan setelah pelaksanaan audit kode program komprehensif ([AUDIT.md](file:///f:/sismon-tte-testing-V4/AUDIT.md) & [laporan_audit.html](file:///f:/sismon-tte-testing-V4/laporan_audit.html)) untuk menuntaskan 11 temuan perbaikan (Keamanan, Database, Kualitas Kode, Infrastruktur).

---

## Rincian Item Tugas per Tahap

### 🟦 Tahap 1: Inisialisasi Proyek, Docker Environment & Boilerplate Foundation
- [x] **T1.1** Inisialisasi skeleton framework Laravel dan verifikasi environment PHP 8.2+/8.3 (`composer.json`, `package.json`).
- [x] **T1.2** Konfigurasi environment `.env` dan `.env.example` (PostgreSQL 16, Redis 7, session database, queue redis).
- [x] **T1.3** Pembuatan `docker-compose.yml` standar produksi dengan 4 layanan: `nginx` (edge proxy), `app` (PHP-FPM), `postgres` (PostgreSQL 16), dan `redis` (Redis 7 dengan requirepass & AOF persistence).
- [x] **T1.4** Konfigurasi Nginx hardened (`docker/nginx/nginx.conf`, `docker/nginx/Dockerfile`) dengan Brotli/Gzip, rate limiting zone (`pendaftaran_limit: 5r/m`, `general_limit: 30r/s`), dan security headers.
- [x] **T1.5** Konfigurasi PHP FPM (`docker/php/Dockerfile`, `docker/php/php.ini`) dengan memory limit, upload limit 5M, dan non-root execution.

---

### 🟦 Tahap 2: Database Migrations, PostgreSQL 16 DDL, Domain Models & Seeders
- [x] **T2.1** Migrasi DDL `skpd` (`2026_08_01_000001_create_skpd_table.php`) dengan kode unik dan index nama.
- [x] **T2.2** Migrasi DDL `master_jabatan_golongan` (`2026_08_01_000002_create_master_jabatan_golongan_table.php`) dengan CHECK constraint `kategori IN ('JABATAN', 'GOLONGAN')`.
- [x] **T2.3** Migrasi DDL `users` & `sessions` (`2026_08_01_000003_create_users_table.php`) dengan CHECK constraint `role = 'superadmin'` dan field MFA.
- [x] **T2.4** Migrasi DDL `pendaftaran_tte` (`2026_08_01_000004_create_pendaftaran_tte_table.php`) dengan 4 Foreign Keys dan CHECK status.
- [x] **T2.5** Migrasi Raw SQL Partial Unique Index (`2026_08_01_000005_add_partial_unique_indexes_pendaftaran_tte.php`) untuk `nik_hash` & `nip_hash` (WHERE status != 'DITOLAK').
- [x] **T2.6** Migrasi DDL `sertifikat_tte` (`2026_08_01_000007_create_sertifikat_tte_table.php`) relasi 1:1, CHECK status 5 nilai, dan composite index status-kadaluarsa.
- [x] **T2.7** Migrasi DDL `dashboard_daily_stats` (`2026_08_01_000008_create_dashboard_daily_stats_table.php`) fast counter harian.
- [x] **T2.8** Migrasi Raw SQL Partisi Bulanan `security_audit_logs` (`2026_08_01_000009_create_security_audit_logs_partitioned_table.php`) dengan `PARTITION BY RANGE (created_at)` dan indeks GIN.
- [x] **T2.9** Pembuatan Clean Domain Eloquent Models.
- [x] **T2.10** Pembuatan Seeder data awal (`SkpdSeeder`, `MasterJabatanGolonganSeeder`, `SuperadminUserSeeder`, `DatabaseSeeder`).
- [x] **T2.11** Verifikasi eksekusi migrasi dan seeder terhadap PostgreSQL 16.

---

### 🟦 Tahap 3: Core Services, Security Infrastructure & Redis Queue
- [x] **T3.1** Implementasi `App\Services\EmailGeneratorService`: generator otomatis format `nama.nip@cirebonkota.go.id`.
- [x] **T3.2** Implementasi `App\Services\LocalCaptchaService`: engine matematika lokal berpenanda tangan HMAC-SHA256.
- [x] **T3.3** Implementasi `App\Services\CaptchaService`: router reCAPTCHA v2/v3 dengan timeout 3 detik + fallback otomatis.
- [x] **T3.4** Implementasi `App\Http\Middleware\SecurityHeaders`: HSTS Preload, X-Frame-Options DENY, CSP.
- [x] **T3.5** Implementasi `App\Http\Middleware\ThrottlingMiddleware`: rate limiting berlapis.
- [x] **T3.6** Implementasi `App\Jobs\ProcessAuditLogJob` pada antrian Redis `audit-logs`.
- [x] **T3.7** Implementasi `App\Domain\Pendaftaran\Observers\AuditObserver`.

---

### 🟦 Tahap 4: IAM, Autentikasi Superadmin & TOTP MFA
- [x] **T4.1** Konfigurasi sistem autentikasi Superadmin (`AuthController` dengan progressive lockout).
- [x] **T4.2** Implementasi TOTP MFA (Google Authenticator) menggunakan `App\Services\Security\TotpService`.
- [x] **T4.3** Implementasi RBAC Policies: `App\Policies\PendaftaranTtePolicy` dan `App\Policies\SertifikatTtePolicy`.

---

### 🟦 Tahap 5: Backend Actions, FormRequests, Controllers & Schedulers
- [x] **T5.1** Modul Pendaftaran Publik (`StorePendaftaranRequest`, `StorePendaftaranAction`, `PendaftaranController`).
- [x] **T5.2** Modul Verifikasi Tahap 1 & Rekap Email Dinas (`RekapEmailDinasAction`, `ApproveTahap1Action`, `RejectPendaftaranAction`, `VerifikasiController`).
- [x] **T5.3** Modul Penerbitan Sertifikat Tahap 2 & Lifecycle (`TerbitkanSertifikatAction`, `PenerbitanController`, `UpdateSertifikatStatusJob`).
- [x] **T5.4** Modul Dashboard Analytics & Fast Aggregator (`RefreshDailyAggregatorJob`, `DashboardController`).
- [x] **T5.5** Modul Streaming Export (`ExportLaporanController` Database Cursor Streaming).
- [x] **T5.6** Modul Manajemen Data Master (`MasterSkpdController`, `MasterJabatanGolonganController`, `UserManagementController`).
- [x] **T5.7** Task Scheduling `routes/console.php` (Scanner H-3 00:01 WIB & Fast Aggregator 30m).
- [x] **T5.8** Centralized Exception Handler pada `bootstrap/app.php`.

---

### 🟦 Tahap 6: Frontend Zero-CDN Asset Pipeline & Carbon Flat UI Integration
- [x] **T6.1** Zero-CDN Typography Pipeline (`public/css/ibm-plex.css`).
- [x] **T6.2** Carbon Flat CSS Engine (`public/css/app.css` 0px radius).
- [x] **T6.3** Interactive Vanilla JS Engine (`public/js/app.js` SHA-256 WebCrypto).
- [x] **T6.4** Layouts Master (`layouts/public.blade.php`, `layouts/admin.blade.php`).
- [x] **T6.5** Portal Publik Resilien (`public/pendaftaran.blade.php`).
- [x] **T6.6** Panel Admin Views (`dashboard.blade.php`, verifikasi, penerbitan, master data).

---

### 🟦 Tahap 7: Testing, Verifikasi & Audit Hardening
- [x] **T7.1** Test Suite Kriptografi & Service Helpers (`CryptoHelperTest`, `EmailGeneratorServiceTest`, `LocalCaptchaServiceTest`).
- [x] **T7.2** Test Suite Feature Pendaftaran, Verifikasi, & Penerbitan.
- [x] **T7.3** Test Suite View Rendering & Mass Streaming Export.
- [x] **T7.4** Hardening dan penyusunan SOP Operasional ([`docs/OPERATIONAL_GUIDE.md`](file:///f:/sismon-tte-testing-V4/docs/OPERATIONAL_GUIDE.md)).

---

### 🟦 Tahap 8: Clone Demo Environment, Dummy Data & Demo Documentation
- [x] **T8.1** Pembentukan clone repositori demo `f:\sismon-tte-testing-V4`.
- [x] **T8.2** Setup SSL Localhost 2048-bit SAN self-signed.
- [x] **T8.3** Inisialisasi akun Superadmin Demo & master data ASN.
- [x] **T8.4** Penyusunan panduan simulasi `DEMO_GUIDELINES.md`.
- [x] **T8.5** Penyusunan log evaluasi `CATATAN_DEMO_DAN_TEMUAN.md`.

---

### 🟦 Tahap 9: Upgrade Laravel 13 & Isolasi Rute Admin Berbasis VPN Gateway
- [x] **T9.1** Upgrade dependensi framework `composer.json` ke `"laravel/framework": "^13.0"` dan PHP `"^8.2|^8.3"`.
- [x] **T9.2** Implementasi file konfigurasi VPN Gateway `config/vpn.php` dan pembaruan `.env` / `.env.example`.
- [x] **T9.3** Implementasi middleware `App\Http\Middleware\EnsureVpnAccess` dengan evaluasi bitwise IP CIDR dan respon Stealth 404.
- [x] **T9.4** Pendaftaran middleware alias `vpn.gateway` pada `bootstrap/app.php`.
- [x] **T9.5** Restrukturisasi rute `routes/web.php` (rute auth admin dipindahkan ke `/gateway/login` dan diproteksi VPN).
- [x] **T9.6** Penyesuaian antarmuka Blade (`views/auth/login.blade.php` dan `views/layouts/admin.blade.php` dengan badge status VPN).
- [x] **T9.7** Hardening konfigurasi Nginx Edge Proxy (`docker/nginx/nginx.conf`) untuk lokasi `/gateway/` dan `/admin/`.
- [x] **T9.8** Pembuatan test suite `tests/Feature/VpnGatewaySecurityTest.php` dan penyesuaian `tests/Feature/AuthenticationTest.php`.

---

### 🟦 Tahap 10: Pembersihan Data Testing & Finalisasi Dokumentasi Deployment V4
- [x] **T10.1** Pembersihan data testing dummy dan reset basis data ke master data resmi produksi (`SkpdSeeder`, `MasterJabatanGolonganSeeder`, `SuperadminUserSeeder`).
- [x] **T10.2** Pembaruan menyeluruh berkas `README.md` dengan panduan deployment lengkap langkah demi langkah, arsitektur sistem, konfigurasi cron/supervisor, kredensial, dan matriks diagnosis error E-01 s/d E-11.
- [x] **T10.3** Sinkronisasi dan penyesuaian path repositori `f:\sismon-tte-testing-V4` serta keterhubungan seluruh dokumen spesifikasi di folder `docs/`.
- [x] **T10.4** Validasi akhir mutu sistem dengan 46 Automated Unit & Feature Tests Passed (179 Assertions) berstatus 100% PASS.

---

### 🟦 Tahap 11: Remediasi Audit Kode Program & Hardening Produksi Terpadu
- [x] **T11.1** Keamanan Klien: Eliminasi PII NIK/NIP dari draft browser, migrasi ke `sessionStorage`, dan auto-clear pada submit ([`pendaftaran.blade.php`](file:///f:/sismon-tte-testing-V4/resources/views/public/pendaftaran.blade.php)).
- [x] **T11.2** Keamanan IAM: Validasi `current_password` (step-up auth), otorisasi RBAC, audit log pada `disableMfa()`, dan persistensi terenkripsi `mfa_recovery_codes` ([`UserManagementController.php`](file:///f:/sismon-tte-testing-V4/app/Http/Controllers/UserManagementController.php), [`User.php`](file:///f:/sismon-tte-testing-V4/app/Models/User.php), migrasi `000010`).
- [x] **T11.3** Penegakan Otorisasi Policy: Menyematkan trait `AuthorizesRequests` & `ValidatesRequests` pada base `Controller.php`, mendaftarkan binding `Gate::policy()` di `AppServiceProvider`, dan memanggil `$this->authorize()` pada `VerifikasiController`, `PenerbitanController`, dan `ExportLaporanController`.
- [x] **T11.4** Optimasi Basis Data: Mengubah kueri mass export menjadi `DB::table('pendaftaran_tte')->leftJoin(...)` (0 kueri N+1) dan penambahan `security_audit_logs_default` fallback partition (migrasi `000011`).
- [x] **T11.5** Kualitas Kode & Sanitasi: Penghapusan duplikasi aturan validasi di `StorePendaftaranRequest`, penyelarasan regex sanitasi nama ASN di frontend, refaktor DRY centralized exception handler, dan optimasi nomor referensi di `StorePendaftaranAction`.
- [x] **T11.6** Infrastruktur Hardening & Dokumentasi: Penegakan strict mandatory variable pada `docker-compose.yml`, unbuffered streaming FastCGI di Nginx proxy (`docker/nginx/nginx.conf`), serta penyusunan laporan hasil remediasi [`solusi_audit.md`](file:///f:/sismon-tte-testing-V4/solusi_audit.md).

---

---

### 🟦 Tahap 12: Penyesuaian Metrik Dashboard Top 10 SKPD Berbasis Persentase Pegawai (V5.1)
- [x] **T12.1** Pembuatan migrasi `2026_09_09_000001_add_total_asn_to_skpd_table.php` untuk penambahan kolom `total_asn` (default 0) pada tabel `skpd`.
- [x] **T12.2** Update model `Skpd.php` (`$fillable` dan casts integer `total_asn`).
- [x] **T12.3** Pembaruan `SkpdSeeder.php` dengan data riil populasi ASN pada instansi di lingkungan Pemkot Cirebon.
- [x] **T12.4** Pembaruan `MasterSkpdController.php` dan tampilan view master SKPD (`index.blade.php`) dengan input populasi total ASN.
- [x] **T12.5** Pembaruan `DashboardController.php` dengan parameter query `?sort=persentase|volume`, kalkulasi rasio adopsi aman dari pembagian nol, dan segregasi Redis cache key.
- [x] **T12.6** Redesain widget Top 10 SKPD pada `admin/dashboard.blade.php` dengan progress bar 4 warna (Hijau, Kuning, Merah, Abu-abu) dan filter toggle.
- [x] **T12.7** Penyesuaian unit & feature tests pada `FrontendViewRenderTest.php`.

---

### 🟦 Tahap 13: Modul Analitik Capaian Adopsi TTE Seluruh SKPD & Ekspor CSV (V5.2)
- [x] **T13.1** Pembuatan Controller `App\Http\Controllers\CapaianSkpdController`:
  - Method `index()`: kalkulasi agregat 4 KPI kota, pemfilteran nama/singkatan (`q`), kategori capaian (`kategori`), pengurutan dinamis (`sort`, `dir`), dan paginasi 20 item/halaman.
  - Method `export()`: ekspor data capaian adopsi TTE berbasis persentase format CSV via unbuffered streaming (`response()->streamDownload()`), UTF-8 BOM (`\xEF\xBB\xBF`), dan sinkronisasi filter aktif.
- [x] **T13.2** Pembuatan Blade View `resources/views/admin/capaian_skpd/index.blade.php`:
  - 4 Kartu KPI Summary (Rata-rata Adopsi Kota, Total Pegawai, Total Pendaftar, Total Sertifikat).
  - Search bar teks instan dan filter dropdown kategori capaian (Sangat Tinggi, Tinggi, Sedang, Rendah).
  - Tabel interaktif seluruh SKPD aktif dengan progress bar capaian berkode warna.
  - Paginasi rapi dengan mempertahankan parameter filter query string.
- [x] **T13.3** Integrasi Rute & Navigasi:
  - Rute `admin.capaian-skpd.index` dan `admin.capaian-skpd.export` pada `routes/web.php`.
  - Item menu sidebar "Capaian Adopsi SKPD" dan shortcut ekspor CSV pada `resources/views/layouts/admin.blade.php`.
  - Tombol aksi pintas "Lihat Seluruh SKPD & Ekspor" pada header widget Top 10 di `resources/views/admin/dashboard.blade.php`.
- [x] **T13.4** Pembuatan Test Suite `tests/Feature/CapaianSkpdTest.php`:
  - 12 feature test cases yang mencakup pengujian render view, kalkulasi KPI agregat, filter pencarian, filter kategori, sorting, listing seluruh SKPD tanpa batas 10, ekspor CSV, sinkronisasi filter pada ekspor, proteksi autentikasi, serta verifikasi link dashboard dan menu sidebar.
- [x] **T13.5** Eksekusi pengujian menyeluruh (Full Test Suite):
*Status seluruh 13 tahapan tugas sebelumnya telah selesai dan terverifikasi 100% tanpa celah.*

---

### 🟦 Tahap 14: Core Dual-Track Backend & Dynamic Parameter Wiring (V6.1)
- [x] **T14.1** Integrasi `SystemParameterService::get('cert_validity_years', 2)` pada `TerbitkanSertifikatAction.php` untuk kalkulasi masa kedaluwarsa sertifikat secara dinamis (anti hard-coding).
- [x] **T14.2** Integrasi `SystemParameterService::get('cert_expiry_warning_days', 7)` pada `UpdateSertifikatStatusJob.php` untuk ambang batas scanner H-7 dinamis.
- [x] **T14.3** Update `EmailGeneratorService.php` dengan signature dan sanitasi `?string $nip` nullable untuk mendukung pembuatan usulan email Non-ASN.
- [x] **T14.4** Update `StorePendaftaranRequest.php` untuk validasi `tipe_pegawai` ('ASN', 'NON_ASN'), relaksasi NIP nullable bagi Non-ASN, validasi berkas `file_bukti_tte` (PDF/JPG/PNG max 2MB), dan evaluasi parameter `allow_non_asn_registration`.
- [x] **T14.5** Update `StorePendaftaranAction.php` untuk persistensi `tipe_pegawai`, upload file fisik `file_bukti_tte` ke disk publik, dan persistensi NIP nullable.
- [x] **T14.6** Pembuatan Test Suite `tests/Feature/DualTrackAndParameterWiringTest.php` (6 tests / 27 assertions) dengan status 100% PASS dan zero regression.

*Pembaruan Tahap 14 (V6.1) berhasil diselesaikan dan terverifikasi penuh.*

---

### 🟦 Tahap 15: Validasi Masal (Bulk Verification), Mekanisme Undo & Standardisasi Terminologi (V6.2)
- [x] **T15.1** Implementasi Action Classes & Audit Snapshot:
  - `BulkVerifikasiAction.php`: Verifikasi & persetujuan masal Tahap 1 dalam transaksi database atomik aman, dengan penegakan batas dinamis `bulk_verification_max_batch` (default 50) dan pencatatan audit log `BULK_VERIFIKASI_TAHAP1`.
  - `BulkValidasiTahap2Action.php`: Validasi dan penerbitan sertifikat masal Tahap 2 dalam transaksi database atomik, dengan penegakan batas dinamis `bulk_verification_max_batch` dan audit log `BULK_VALIDASI_TAHAP2`.
  - `UndoVerifikasiAction.php`: Pembatalan status verifikasi permohonan kembali ke `MENUNGGU_VERIFIKASI` dengan audit trail `UNDO_VERIFIKASI_TAHAP1`.
  - `UndoRevokeAction.php`: Pemulihan sertifikat yang dicabut kembali ke status `AKTIF` dengan audit trail `UNDO_REVOKE_SERTIFIKAT`.
  - `UpdateStatusSertifikatAction.php`: Logging snapshot kondisi awal sebelum sertifikat dicabut (`REVOKE_SERTIFIKAT`).
- [x] **T15.2** Otorisasi RBAC & Policies:
  - `PendaftaranTtePolicy.php`: Tambahan otorisasi `bulkApprove` dan `undo` (dengan proteksi ketat `!$pendaftaran->sertifikat()->exists()`).
  - `SertifikatTtePolicy.php`: Tambahan otorisasi `bulkValidate` dan `undoRevoke` (dengan proteksi status sertifikat `REVOKED`).
- [x] **T15.3** Pembaruan Controllers & Routes:
  - `VerifikasiController.php`: Endpoint `bulkApprove` (menerima array UUID/ID) dan `undo` (resolusi aman tipe data UUID PostgreSQL).
  - `PenerbitanController.php`: Endpoint `bulkValidate` dan `undoRevoke`.
  - `routes/web.php`: Pendaftaran rute POST `/admin/verifikasi/bulk-approve`, `/admin/verifikasi/{id}/undo`, `/admin/penerbitan/bulk-validate`, dan `/admin/penerbitan/sertifikat/{id}/undo-revoke`.
- [x] **T15.4** Standardisasi Terminologi TTE:
  - Penyelarasan seluruh label antrian dan tombol dari "Penerbitan" menjadi **"Validasi Sertifikat TTE"** untuk mempertegas peran Diskominfo sebagai verifikator/validator penerbitan sertifikat BSrE.
- [x] **T15.5** Modernisasi Blade Views:
  - `admin/verifikasi/show.blade.php`: Penambahan badge identitas `Tipe Pegawai` (ASN vs NON-ASN), tautan unduh/buka lampiran dokumen bukti TTE (`file_bukti_tte`), dan tombol + modal konfirmasi Undo Verifikasi.
  - `admin/verifikasi/index.blade.php`: Checkbox pemilihan massal, badge tipe pegawai, toolbar aksi dinamis "⚡ Setujui Masal Terpilih", dan modal konfirmasi persetujuan masal.
  - `admin/penerbitan/index.blade.php`: Checkbox antrian validasi massal, toolbar aksi "⚡ Validasi Masal Terpilih", modal batch validation dengan pemilih tanggal terbit, tombol "↩ Pulihkan (Undo)" pada sertifikat berstatus REVOKED, dan modal konfirmasi pemulihan.
- [x] **T15.6** Automated Feature Test Suite:
  - Pembuatan `tests/Feature/BulkOperationsAndUndoTest.php` mencakup 8 test cases:
    1. Bulk approve Tahap 1
    2. Guard batasan parameter `bulk_verification_max_batch` pada Tahap 1
    3. Bulk validate Tahap 2
    4. Guard batasan parameter `bulk_verification_max_batch` pada Tahap 2
    5. Undo verifikasi ketika sertifikat belum terbit
    6. Blokir undo verifikasi ketika sertifikat sudah terbit (403 Forbidden)
    7. Undo revoke sertifikat yang dicabut (audit trail pemulihan)
    8. Blokir undo revoke jika sertifikat masih aktif (403 Forbidden)
- [x] **T15.7** Verifikasi & Quality Assurance (100% PASS):
  - Eksekusi `BulkOperationsAndUndoTest.php`: **8 passed (40 assertions)**.
  - Eksekusi Regression Test (`DualTrackAndParameterWiringTest.php` & `PenerbitanTahap2Test.php`): **10 passed (37 assertions)**.
  - Eksekusi Regression Test (`PendaftaranPublikTest.php`): **3 passed (17 assertions)**.
  - Total pengujian aktif: **21 passed (94 assertions)** tanpa satupun regresi.

*Pembaruan Tahap 15 (V6.2) berhasil diselesaikan dan terverifikasi penuh.*

---

### 🟦 Tahap 16: Portal Publik, Landing Page Agregat, Dual-track Form UI & Account Recovery (V6.3)
- [x] **T16.1** Landing Page Agregat & Macro KPI Kota:
  - Kalkulasi agregat KPI kota pada `PendaftaranController::index` (Tingkat Adopsi Kota %, Total Sertifikat TTE Aktif, Total Pendaftar, Unit Kerja Terlayani) dengan proteksi cache Redis.
  - Implementasi Macro KPI Banner, Progress Bar Adopsi, dan 4 Kartu Metrik Agregat pada `resources/views/public/pendaftaran.blade.php`.
  - Penambahan shortcut aksi cepat: "Cek Status & Pemulihan Akun" dan "Pengajuan Kunjungan Layanan On-Site".
- [x] **T16.2** Antarmuka Formulir Pendaftaran Dual-Track (ASN vs Non-ASN):
  - Segmented control pemilih jalur ASN (PNS/PPPK) dan Non-ASN (THL/Kontrak/BLUD).
  - Penyesuaian dinamis status wajib NIP (wajib 18 digit untuk ASN, opsional bagi Non-ASN).
  - Penegakan parameter dinamis `allow_non_asn_registration` pada UI.
  - Penambahan input berkas `file_bukti_tte` (PDF/JPG/PNG max 2MB) dengan dukungan `enctype="multipart/form-data"` pada `<form>`.
- [x] **T16.3** Modul Pemulihan Akun & Pengecekan Sertifikat (`/recovery`):
  - Pembuatan Controller `App\Http\Controllers\AccountRecoveryController`:
    - Method `showForm()`: render form verifikasi identitas pemohon.
    - Method `check()`: verifikasi NIK dan NIP deterministik aman (`CryptoHelper::encryptField`), validasi 4 digit kontak, serta penarikan data permohonan & status sertifikat TTE BSrE.
  - Pembuatan Blade View `resources/views/public/recovery.blade.php`:
    - Layout Carbon Flat terstruktur, penampil identitas ter-masking (UU PDP), rincian siklus hidup sertifikat, peringatan masa berlaku H-7 / Expired / Revoked, dan panduan kontak helpdesk DKIS.
- [x] **T16.4** Modul Pengajuan Kunjungan Layanan Asistensi On-Site (`/kunjungan-layanan`):
  - Pembuatan Controller `App\Http\Controllers\PengajuanKunjunganController`:
    - Method `create()`: form permohonan kunjungan asistensi on-site SKPD.
    - Method `store()`: validasi, upload surat resmi SKPD, pembuatan tiket otomatis berformat `VISIT-YYYYMMDD-XXXXXX`.
    - Method `status()`: pelacak progres status tiket permohonan kunjungan.
  - Pembuatan Blade Views:
    - `resources/views/public/kunjungan_layanan.blade.php`.
    - `resources/views/public/kunjungan_status.blade.php`.
- [x] **T16.5** Navigasi Global Portal Publik:
  - Pembaruan `resources/views/layouts/public.blade.php` dengan sub-nav bar resmi antar-layanan (Pendaftaran TTE, Pemulihan Akun, Kunjungan Layanan, dan Gateway Admin).
  - Pendaftaran rute publik di `routes/web.php` dengan proteksi rate limiter `throttle.custom`.
- [x] **T16.6** Automated Feature Test Suite:
  - Pembuatan `tests/Feature/PortalAndRecoveryTest.php` mencakup 10 test cases:
    1. Render Macro KPI dan opsi Dual-Track pada portal publik
    2. Respon API JSON dengan macro stats dan list SKPD
    3. Render form pemulihan akun
    4. Pencarian pendaftaran aktif via NIK & NIP
    5. Menampilkan status sertifikat terbit (UUID & masa aktif)
    6. Proteksi nomor kontak tidak cocok (UU PDP)
    7. Penanganan data tidak ditemukan dengan pesan ramah pengguna
    8. Render form kunjungan layanan
    9. Pengiriman permohonan kunjungan & pembuatan nomor tiket unik
    10. Validasi field wajib form kunjungan layanan
- [x] **T16.7** Quality Assurance & Full Regression Testing:
  - `PortalAndRecoveryTest`: **10 passed (69 assertions)**.
  - `PendaftaranPublikTest` & `FrontendViewRenderTest`: **9 passed (65 assertions)**.
  - Full Project Test Suite: **118 passed (547 assertions) — 100% PASS**.

*Pembaruan Tahap 16 (V6.3) berhasil diselesaikan dan terverifikasi penuh.*

---

### 🟦 Tahap 17: Admin Panel Settings, Pengelolaan Kunjungan Layanan & Target SKPD Bulanan (V6.4)
- [x] **T17.1** Panel Pengaturan Parameter Kebijakan Sistem (`/admin/settings/parameters`):
  - Pembuatan Controller `App\Http\Controllers\SystemParameterController`:
    - Method `index()`: pengelompokan parameter kebijakan (`CERTIFICATE`, `REGISTRATION`, `OPERATIONAL`, `ANALYTICS`).
    - Method `update()`: validasi numerik & boolean, persistensi transaksi DB, invalidasi cache otomatis via `SystemParameterService::clearCache()`, dan pencatatan audit log `UPDATE_SYSTEM_PARAMETERS`.
  - Pembuatan Blade View `resources/views/admin/settings/parameters.blade.php` dengan UI Carbon Flat, toggle switch dinamis, hairline borders, dan alert penjelas integrasi real-time.
- [x] **T17.2** Panel Pengelolaan Permohonan Kunjungan Layanan Asistensi SKPD (`/admin/kunjungan-layanan`):
  - Pembuatan Controller `App\Http\Controllers\AdminKunjunganLayananController`:
    - Method `index()`: filter status, pencarian toleran teks (tiket, narahubung, SKPD), dan kalkulasi 5 KPI status.
    - Method `updateStatus()`: workflow penetapan tanggal definitif kunjungan (`DIJADWALKAN`), penyelesaian asistensi (`SELESAI`), dan penolakan berargumen resmi (`DITOLAK`) dengan audit trail `UPDATE_STATUS_KUNJUNGAN`.
  - Pembuatan Blade View `resources/views/admin/kunjungan/index.blade.php`:
    - KPI cards status permohonan, tabel responsif dengan link unduh surat permohonan dinas, modal interaktif penjadwalan, penyelesaian, dan penolakan tiket.
- [x] **T17.3** Panel Pengelolaan Target Adopsi TTE SKPD Bulanan (`/admin/target-skpd`):
  - Pembuatan Controller `App\Http\Controllers\SkpdTargetMetricController`:
    - Method `index()`: filter periode bulan dan tahun, pemuatan data seluruh SKPD aktif beserta status target penetapan.
    - Method `update()`: penyimpanan batch target persentase dan target jumlah pegawai menggunakan `updateOrCreate()` serta audit log `UPDATE_SKPD_TARGET_METRICS`.
    - Method `bulkSetDefault()`: penetapan cepat target 100% kepatuhan ke seluruh SKPD aktif dengan audit log `BULK_DEFAULT_SKPD_TARGET_METRICS`.
  - Pembuatan Blade View `resources/views/admin/target_skpd/index.blade.php`:
    - Pemilih periode bulan & tahun, kalkulator interaktif persentase ke estimasi pegawai via JavaScript, dan tombol cepat penetapan default 100%.
- [x] **T17.4** Integrasi Navigasi Sidebar Superadmin:
  - Pembaruan `resources/views/layouts/admin.blade.php` dengan menu "Kunjungan Layanan SKPD", "Target Bulanan SKPD", dan seksi baru "Pengaturan Sistem -> Parameter Kebijakan".
  - Standardisasi label sidebar menjadi "Validasi Sertifikat (Tahap 2)".
  - Pendaftaran rute web terproteksi VPN Gateway & Auth di `routes/web.php`.
- [x] **T17.5** Automated Feature Test Suite:
  - Pembuatan `tests/Feature/AdminSettingsAndManagementTest.php` mencakup 10 test cases:
    1. Proteksi akses non-autentikasi (guest redirect)
    2. Render halaman pengaturan parameter kebijakan sistem
    3. Pembaruan parameter sistem, verifikasi cache invalidation, dan audit log
    4. Penegakan validasi batasan minimal/maksimal parameter sistem
    5. Render tabel daftar permohonan kunjungan layanan SKPD
    6. Penjadwalan tanggal kunjungan definitif dan pencatatan audit log
    7. Alur penyelesaian dan penolakan permohonan kunjungan
    8. Render halaman penetapan target bulanan SKPD
    9. Pembaruan target SKPD per periode dan persistensi database
    10. Aksi masal penetapan target 100% kepatuhan ke seluruh SKPD
- [x] **T17.6** Quality Assurance & Full Regression Testing:
  - `AdminSettingsAndManagementTest`: **10 passed (54 assertions)**.
  - Gabungan seluruh suite V6 (Tahap 1 s/d 4): **34 passed (190 assertions)**.
  - Full Project Test Suite: **128 passed (601 assertions) — 100% PASS**.

*Pembaruan Tahap 17 (V6.4) berhasil diselesaikan dan terverifikasi penuh.*

---

### 🟦 Tahap 18: Fitur Otomasi, Ekspor PDF Resmi & Batch Importer Data Historis (V6.5)
- [x] **T18.1** Modul Ekspor Laporan PDF Resmi Berkop Diskominfo:
  - Pembuatan Controller `App\Http\Controllers\ExportLaporanPdfController` dengan otorisasi policy `exportLaporan`.
  - Dukungan filter komprehensif: SKPD (`skpd_id`), rentang tanggal (`start_date`, `end_date`), status permohonan (`status`), status sertifikat (`status_sertifikat`), dan tipe pegawai (`tipe_pegawai`).
  - Dukungan mode download default dan browser inline stream (`?stream=1`).
  - Pembuatan template Blade `resources/views/admin/export/pdf_laporan.blade.php`:
    - Kop surat resmi Pemerintah Daerah Kota Cirebon & Dinas Komunikasi dan Informatika (Jl. Sudarsono No. 40 Cirebon 45134) dengan garis ganda resmi (double rule).
    - Judul laporan resmi dan metadata pencetakan.
    - 6 kartu KPI ringkasan statistik (Total Permohonan, ASN, Non-ASN, Sertifikat Aktif, Menunggu Audit, Ditolak).
    - Tabel data terstruktur dengan penyamaran NIK/NIP (UU PDP).
    - Blok pengesahan dan tanda tangan Kepala Dinas Komunikasi dan Informatika Kota Cirebon.
  - Integrasi modal `modal-pdf-export` pada `resources/views/layouts/admin.blade.php` dan menu sidebar dengan badge merah `PDF`.
  - Pendaftaran shared SKPD pada `AppServiceProvider.php` via `View::composer('layouts.admin', ...)`.
- [x] **T18.2** Scheduler Otomasi Notifikasi Email Kedaluwarsa H-7:
  - Pembuatan Mailable `App\Mail\SertifikatExpiringNotification`:
    - Mengemas data sertifikat, nama pemohon, NIP/NIK masked, SKPD, dan sisa hari.
    - Judul email dinamis: `[PENTING] Peringatan Masa Berlaku Sertifikat Elektronik TTE (H-X) - Diskominfo Kota Cirebon`.
  - Pembuatan template email Blade `resources/views/emails/sertifikat_expiring.blade.php` dengan inline CSS Carbon Flat responsif, tabel rincian sertifikat, rekomendasi langkah perpanjangan, dan kontak helpdesk DKIS.
  - Pembuatan Artisan Command `App\Console\Commands\NotifyExpiringCertificatesH7Command` (`tte:notify-expiring-h7`):
    - Pembacaan ambang batas dinamis `cert_expiry_warning_days` via `SystemParameterService`.
    - Dukungan opsi `--days=`, `--window`, dan `--dry-run` (simulasi).
    - Pengiriman email via `Mail::to()`, pencatatan audit log `NOTIFIKASI_EXPIRING_H7`, dan ringkasan tabel CLI.
  - Pendaftaran scheduler pada `routes/console.php` untuk eksekusi harian pukul 07:00 WIB:
    `Schedule::command('tte:notify-expiring-h7')->dailyAt('07:00')->timezone('Asia/Jakarta');`
- [x] **T18.3** Modul Impor Data Historis Pegawai & Sertifikat CSV:
  - Pembuatan Action Class `App\Domain\Pendaftaran\Actions\ImportHistoricalDataAction`:
    - Deteksi otomatis delimiter koma (`,`) dan titik koma (`;`).
    - Pemetaan header fleksibel (`nama_lengkap`, `nik`, `nip`, `tipe_pegawai`, `skpd`, `no_hp`, `email_dinas`, `tanggal_penerbitan`, `status_sertifikat`).
    - Validasi ketat NIK 16 digit, sanitasi nama tanpa gelar, dan penanganan NIP nullable bagi Non-ASN.
    - Enkripsi deterministik NIK/NIP dengan `CryptoHelper::encryptField`.
    - Pemeriksaan anti-collision duplikasi NIK aktif (status bukan DITOLAK).
    - Otomasi status `DISETUJUI` dan penerbitan rekaman `SertifikatTte` jika tanggal penerbitan disediakan (masa berlaku dinamis sesuai `cert_validity_years`).
    - Pencatatan log batch ke `AuditActionHistory` (`IMPORT_HISTORICAL_DATA`).
  - Pembuatan Controller `App\Http\Controllers\AdminImportHistoricalDataController`:
    - Endpoint `index()`: form upload dan tabel 10 riwayat import terakhir.
    - Endpoint `downloadTemplate()`: generator file template CSV standar `template_impor_historis_tte.csv`.
    - Endpoint `store()`: validasi berkas CSV max 10MB, opsi dry-run, dan flash message rekapitulasi.
  - Pembuatan Blade view `resources/views/admin/import/index.blade.php` dengan spesifikasi kolom dan tabel audit.
  - Penambahan menu sidebar "Impor Data Historis" (badge `CSV`).
- [x] **T18.4** Otorisasi RBAC & Integrasi Rute:
  - Penambahan otorisasi `create` pada `App\Policies\PendaftaranTtePolicy`.
  - Pendaftaran rute web di `routes/web.php` (`admin.export.pdf`, `admin.import.index`, `admin.import.template`, `admin.import.store`).
- [x] **T18.5** Automated Feature Test Suite:
  - Pembuatan `tests/Feature/PdfExportAndSchedulerTest.php` mencakup 9 test cases:
    1. Proteksi akses non-autentikasi (guest redirect)
    2. Ekspor PDF resmi (HTTP 200, Content-Type application/pdf, valid binary header `%PDF-`)
    3. Filter SKPD, status, dan tanggal pada ekspor PDF
    4. Artisan command `tte:notify-expiring-h7` mengirimkan email H-7 dan mencatat audit log
    5. Mode simulasi `--dry-run` tidak mengirimkan email
    6. Render halaman impor dan download template CSV standar
    7. Batch import CSV menyimpan data pegawai dan sertifikat dengan benar
    8. Penanganan duplikasi NIK dilewati secara aman (tanpa galat)
    9. Mode dry-run import tidak mengubah basis data
- [x] **T18.6** Quality Assurance & Full Regression Testing:
  - `PdfExportAndSchedulerTest`: **9 passed (54 assertions)**.
  - Gabungan seluruh suite V6 (Tahap 1 s/d 5): **43 passed (244 assertions)**.
  - Full Project Test Suite: **137 passed (655 assertions) — 100% PASS**.

*Pembaruan Tahap 18 (V6.5) berhasil diselesaikan dan terverifikasi penuh.*


