# Sistem Monitoring Penerbitan TTE & Pelayanan Email Dinas Resmi Pemerintah Kota Cirebon (v6.1)

[![PHP](https://img.shields.io/badge/PHP-8.2%20%2F%208.3-blue.svg)]()
[![Framework](https://img.shields.io/badge/Framework-Laravel%2013-red.svg)]()
[![Database](https://img.shields.io/badge/Database-PostgreSQL%2016%20Alpine-336791.svg)]()
[![Cache & Queue](https://img.shields.io/badge/Cache%20%26%20Queue-Redis%207%20Alpine-dc382d.svg)]()
[![Security](https://img.shields.io/badge/Security-Zero--Trust%20VPN%20Gateway%20%2B%20Stealth%20404-0043ce.svg)]()
[![Agency](https://img.shields.io/badge/Instansi-DKIS%20Kota%20Cirebon-0e6027.svg)]()

Aplikasi web enterprise berskala pemerintahan daerah yang dikembangkan khusus untuk **Dinas Komunikasi, Informatika dan Statistik (DKIS) Kota Cirebon** (khususnya **Bidang Persandian dan Keamanan Informasi**). Sistem ini mengelola seluruh siklus permohonan sertifikat Tanda Tangan Elektronik (TTE) Balai Sertifikasi Elektronik (BSrE), penetapan & rekapitulasi akun email dinas resmi ASN (`@cirebonkota.go.id`), verifikasi berkas 2 tahap, penerbitan sertifikat dengan penegakan kebijakan masa berlaku, scanner peringatan dini kedaluwarsa H-7 (*Warning Zone*), analitik adopsi TTE berbasis kategori & sub-unit SKPD secara dinamis, ekspor massal data transaksi *memory-safe streaming* (FastCGI unbuffered join), serta perimeter keamanan **Zero-Trust VPN Gateway** dengan mode siluman (*Stealth Defense* HTTP 404).

---

## 📑 Daftar Isi
1. [Rangkuman Fitur Utama Sistem](#-rangkuman-fitur-utama-sistem)
2. [Arsitektur & Topologi Jaringan](#-arsitektur--topologi-jaringan)
3. [Prasyarat Sistem (System Requirements)](#-prasyarat-sistem-system-requirements)
4. [Panduan Deployment Cepat via Docker Compose](#-panduan-deployment-cepat-via-docker-compose)
5. [Panduan Instalasi Manual / Bare-Metal (Non-Docker)](#-panduan-instalasi-manual--bare-metal-non-docker)
6. [Konfigurasi Background Worker & Otomatisasi](#-konfigurasi-background-worker--otomatisasi)
7. [Kredensial Default & Rute Akses](#-kredensial-default--rute-akses)
8. [Matriks Troubleshooting & Penanganan Error](#-matriks-troubleshooting--penanganan-error)
9. [Struktur Direktori Sistem](#-struktur-direktori-sistem)
10. [Lisensi & Hak Cipta](#-lisensi--hak-cipta)

---

## 🏛️ Rangkuman Fitur Utama Sistem

### 1. Landing Page Publik Modern & Responsif (DKIS Kota Cirebon)
- **Desain Bersih & Elegan**: Mengadopsi bahasa visual modern dengan tipografi *Plus Jakarta Sans* & *Outfit*, navigasi dinamis, dan tata letak *card* berlapis yang nyaman di perangkat seluler maupun desktop.
- **Aset Visual Lokal Kota Cirebon**: Menggunakan aset lokal resmi Pemkot Cirebon di `public/images/` (Logo DKIS Kota Cirebon, Gedung Kantor DKIS, Badge Resmi BSrE 2024, dan ilustrasi pendukung).
- **Statistik Makro Publik Real-Time**: Menampilkan total berkas masuk, sertifikat TTE aktif terbit, total ASN terdaftar, dan cakupan SKPD yang terhubung.
- **Pelacakan Status Berkas Mandiri**: Pemohon dapat memeriksa status berkas secara langsung menggunakan NIK dan Nomor Registrasi tanpa login.
- **Bantuan & FAQ Interaktif**: Panduan persyaratan pengajuan TTE ASN, Non-ASN, dan aktivasi email dinas resmi.

### 2. Formulir Pendaftaran Mandiri ASN (Resilient & Low-Bandwidth)
- **Live Searchable Combobox**: Dropdown pencarian interaktif untuk Unit Kerja (SKPD), Jabatan, dan Golongan tanpa *library* luar (100% Vanilla JS & CSS ringan).
- **SessionStorage Auto-Save (Aman UU PDP No. 27/2022)**: Draft form disimpan sementara di browser (`tte_registration_draft_temp`) dan otomatis dihapus saat form disubmit. Data rahasia NIK/NIP tidak pernah disimpan pada draft browser.
- **Enkripsi AES-256-GCM & Blind Indexing HMAC-SHA256**: NIK (16 digit) dan NIP (18 digit) dienkripsi pada tingkat kolom basis data (`nik_encrypted`, `nip_encrypted`), dengan pencarian cepat menggunakan blind index hash (`nik_bidx`, `nip_bidx`) serta perutean URL berbasis UUID v4 untuk mencegah eksploitasi ID Enumeration (OWASP IDOR).
- **Auto Email Generator**: Menghasilkan rekomendasi format email dinas resmi `nama.nip@cirebonkota.go.id` otomatis dengan pembersihan gelar akademik/keagamaan.
- **Dual Layer CAPTCHA**: Google reCAPTCHA (v2/v3) dengan *failover* otomatis ke *Local Math CAPTCHA* berpenanda tangan HMAC-SHA256 berbatas waktu 5 menit jika koneksi internet publik terputus.

### 3. Panel Verifikasi Berkas Tahap 1
- **Antrean FIFO Terstruktur**: Permohonan diverifikasi secara ketat berurutan sesuai waktu pengajuan (*First-In, First-Out*).
- **Otorisasi Ketat**: Pengamanan aksi verifikasi dan penetapan email dinas diproteksi oleh `PendaftaranTtePolicy`.
- **Penetapan & Rekapitulasi Email Dinas**: Verifikator dapat menetapkan email definitif pemohon berakhiran `@cirebonkota.go.id` sebelum berkas disetujui.
- **Persetujuan & Penolakan Disertai Catatan**: Form penolakan berkas wajib menyertakan alasan spesifik sehingga pemohon dapat memperbaiki dokumen.

### 4. Penerbitan Sertifikat Tahap 2 & Kebijakan Masa Berlaku
- **Kebijakan Masa Berlaku Terkunci Sesuai Tahun Terbit**:
  - Sertifikat yang diterbitkan pada tahun kebijakan sebelumnya (1 tahun) tetap **terkunci permanen pada masa berlaku 1 tahun** dan tidak akan berubah.
  - Permohonan baru yang diterbitkan pada tahun berjalan otomatis menerapkan kebijakan masa berlaku **2 tahun** (`tanggal_terbit + 2 tahun`).
- **Pencegahan Duplikasi Penerbitan**: Validasi ketat mencegah penerbitan ganda pada permohonan yang sama.
- **Daily Scanner Pukul 00:01 WIB**: Scheduler latar belakang memindai masa berlaku setiap malam dan otomatis menandai status `WARNING_H7` (Peringatan H-7 Kedaluwarsa) serta `EXPIRED`.
- **Status Lifecycle Lengkap**: Mendukung status `AKTIF`, `WARNING_H7` (dan `WARNING_H3`), `EXPIRED`, `REVOKED`, dan `PERLU_PERPANJANG`.

### 5. Dashboard Analitik Cepat & Breakdown Dinamis Seluruh SKPD
- **Performa Tinggi (< 50ms via Redis Cache)**: Ringkasan metrik disimpan pada tabel `dashboard_daily_stats` dan disinkronkan ke Redis.
- **Analitik Capaian Adopsi SKPD**: Halaman khusus (`/admin/capaian-skpd`) menyajikan metrik adopsi TTE seluruh perangkat daerah Kota Cirebon.
- **Breakdown Dinamis Sub-Kategori ASN**: Mendukung *drill-down* hierarki sub-unit kerja secara universal (misal: Dinas Kesehatan -> RSUD Gunung Jati, Puskesmas/UPTD, Dinas Induk, BUMD; Dinas Pendidikan -> Satuan Pendidikan SD/SMP; Kecamatan -> Kelurahan).
- **Filter & Ekspor CSV Khusus Sub-Unit**: Unduh CSV terfilter seketika per sub-kategori/sub-unit secara instan.

### 6. Manajemen Akun Superadmin & Safeguards Keamanan
- **Proteksi Minimal 1 Superadmin Aktif**: Sistem secara otomatis menolak deaktivasi atau penghapusan akun superadmin jika akun tersebut adalah satu-satunya superadmin aktif (HTTP 422 Unprocessable Content).
- **Anti Self-Deletion**: Administrator yang sedang login dicegah menghapus atau menonaktifkan akun miliknya sendiri (HTTP 403 Forbidden).
- **Integritas Relasi Transaksi**: Pengecekan riwayat verifikasi dan penerbitan sertifikat sebelum akun dapat dihapus, mencegah kerusakan relasi basis data.
- **Pencatatan Audit Log Lengkap**: Setiap perubahan pengguna dicatat melalui `ProcessAuditLogJob` ke tabel audit log.

### 7. Keamanan Perimeter Zero-Trust & Enkripsi
- **Zero-Trust VPN Gateway**: Seluruh jalur administrasi (`/gateway/login` dan `/admin/*`) diisolasi khusus subnet IP VPN terdaftar (`EnsureVpnAccess`).
- **Stealth Defense (HTTP 404)**: Akses ilegal dari luar subnet VPN merespon dengan status 404 Not Found murni untuk mengaburkan eksistensi antarmuka backend.
- **Multi-Factor Authentication (RFC 6238 TOTP)**: Terintegrasi dengan Google Authenticator / Microsoft Authenticator disertai 8 kode pemulihan darurat (*recovery backup codes*) terenkripsi.
- **Audit Log Terpartisi Bulanan**: Tabel `security_audit_logs` dipartisi bulanan (`PARTITION BY RANGE (created_at)`) dengan indeks GIN untuk performa kueri log jangka panjang.
- **Proteksi Brute-Force & Rate Limiting**: Progressive lockout 5x percobaan gagal dengan jeda eksponensial (HTTP 429).
- **Security Headers Hardened**: HSTS Preload (1 tahun), Content Security Policy (CSP), X-Frame-Options DENY, X-Content-Type-Options nosniff.

### 8. Ekspor Massal Laporan (Unbuffered FastCGI Streaming)
- **Konsumsi RAM Konstan (< 20MB)**: Menggunakan `DB::table('pendaftaran_tte')->leftJoin(...)` berbasis cursor streaming untuk mengalirkan ratusan ribu data transaksi langsung ke format CSV tanpa *memory exhaustion* atau query storm N+1.
- **FastCGI Buffering Bypass**: Konfigurasi `fastcgi_buffering off` dan batas waktu 300 detik pada Nginx memastikan unduhan langsung mengalir tanpa kendala *504 Gateway Timeout*.
- **Kompatibilitas UTF-8 BOM**: Dilengkapi penanda byte `\xEF\xBB\xBF` agar simbol dan karakter khusus nama ASN terbaca rapi di Microsoft Excel.

---

## 🌐 Arsitektur & Topologi Jaringan

```
[ INTERNET PUBLIK ]
        │
        ├──> (Port 80 HTTP -> 301 Redirect ke HTTPS)
        └──> (Port 443 HTTPS) ──> [ NGINX 1.25 Reverse Proxy (SSL/TLS) ]
                                             │
                       ┌─────────────────────┴─────────────────────┐
                       │                                           │
             [ Rute Publik: / ]                          [ Rute Terproteksi: /admin/* ]
             Formulir & Status Mandiri                   Zero-Trust VPN Gateway Guard
                       │                                           │
                       │                             (Cek Subnet IP Whitelist VPN)
                       │                             ├── Di luar VPN ──> HTTP 404 (Stealth)
                       │                             └── Di dalam VPN ─> Lolos ke Auth / TOTP
                       │                                           │
                       └─────────────────────┬─────────────────────┘
                                             │
                              [ PHP 8.3-FPM (Laravel 13) ]
                                 (AES-256-GCM / HMAC-SHA256)
                                             │
                       ┌─────────────────────┴─────────────────────┐
                       ▼                                           ▼
          [ PostgreSQL 16 Alpine ]                       [ Redis 7 Alpine ]
          - Partisi Audit Bulanan                        - Cache Aggregator
          - Kolom Enkripsi & Blind Index                 - Queue Asinkron
          - Foreign Keys & UUID v4                       - Rate Limiter
```

---

## 💻 Prasyarat Sistem (System Requirements)

### Pilihan A: Menggunakan Docker (Direkomendasikan)
- **Docker Engine**: Versi 24.0.0 atau lebih baru
- **Docker Compose**: Versi 2.20.0 atau lebih baru
- **Arsitektur Host**: Linux (Ubuntu 22.04 / 24.04 LTS, Debian 12), Windows Server, atau macOS
- **RAM Minimum**: 2 GB (Disarankan 4 GB untuk beban produksi tinggi)
- **Penyimpanan Bebas**: Minimal 10 GB

### Pilihan B: Bare Metal / Server Non-Docker
- **PHP**: Versi 8.2 atau 8.3
- **Ekstensi PHP Wajib**: `pdo_pgsql`, `pgsql`, `redis`, `bcmath`, `mbstring`, `gd`, `opcache`, `sodium`, `curl`, `xml`, `zip`
- **Composer**: Versi 2.6+
- **Database**: PostgreSQL 16+
- **In-Memory Cache**: Redis 7+
- **Web Server**: Nginx 1.24+ (disarankan) atau Apache 2.4+

---

## 🚀 Panduan Deployment Cepat via Docker Compose

Paket ini sudah dilengkapi konfigurasi Docker multi-container siap pakai yang mencakup Nginx HTTPS, PHP 8.3-FPM, PostgreSQL 16 Alpine, Redis 7 Alpine, dan Adminer DB Manager.

### Langkah 1: Persiapan Environment
Ekstrak arsip sistem ke direktori server Anda, lalu periksa berkas konfigurasi `.env`:
```bash
cp .env.example .env
```
Pastikan variabel kunci terisi (jika belum ada, generate menggunakan artisan pada langkah berikutnya).

### Langkah 2: Jalankan Container Docker
Bangun (*build*) dan jalankan seluruh container di latar belakang:
```bash
docker compose up -d --build
```
Periksa status container yang berjalan:
```bash
docker compose ps
```
Seluruh container (`tte_nginx`, `tte_app`, `tte_postgres`, `tte_redis`, `tte_adminer`) harus berstatus *Up*.

### Langkah 3: Inisialisasi Database & Kunci Enkripsi
Jalankan perintah berikut di dalam container `app`:

```bash
# 1. Generate Application Key (jika belum terisi di .env)
docker compose exec app php artisan key:generate

# 2. Buat symlink storage
docker compose exec app php artisan storage:link

# 3. Jalankan migrasi tabel basis data (termasuk partisi bulanan)
docker compose exec app php artisan migrate --force

# 4. Eksekusi data seeder (Akun Superadmin & Master SKPD/Jabatan Kota Cirebon)
docker compose exec app php artisan db:seed --force

# 5. Optimasi cache produksi
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

Sistem kini siap digunakan melalui peramban web di `https://localhost` atau alamat IP server Anda.

---

## 🖥️ Panduan Instalasi Manual / Bare-Metal (Non-Docker)

Jika Anda ingin menjalankan sistem langsung pada server fisik tanpa Docker:

```bash
# 1. Pastikan dependensi PHP dan composer telah terpasang
composer install --no-dev --optimize-autoloader

# 2. Siapkan file .env dan generate key
cp .env.example .env
php artisan key:generate

# 3. Buat database PostgreSQL di PostgreSQL Server Anda:
# psql -U postgres -c "CREATE DATABASE tte_monitoring;"

# 4. Buat symlink storage publik
php artisan storage:link

# 5. Jalankan migrasi dan seeder
php artisan migrate --force
php artisan db:seed --force

# 6. Jalankan optimasi cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## ⚙️ Konfigurasi Background Worker & Otomatisasi

Untuk memastikan fitur pemantauan masa kedaluwarsa sertifikat H-7, pembaruan data statistik agregator, dan pencatatan audit log berjalan lancar di latar belakang:

### 1. Crontab Scheduler (Host Linux)
Tambahkan entri scheduler pada crontab server (`crontab -e`):

**Untuk Docker:**
```bash
* * * * * cd /path/to/sismon-tte-V6 && docker compose exec -T app php artisan schedule:run >> /dev/null 2>&1
```

**Untuk Non-Docker:**
```bash
* * * * * cd /path/to/sismon-tte-V6 && php artisan schedule:run >> /dev/null 2>&1
```

**Tugas Otomatis yang Terjadwal:**
- **Pukul 00:01 WIB (Setiap Hari):** `UpdateSertifikatStatusJob` memindai masa berlaku seluruh sertifikat aktif. Sertifikat dalam rentang <= 7 hari otomatis diubah ke status `WARNING_H7`, dan sertifikat melewati masa aktif diubah ke `EXPIRED`.
- **Setiap 30 Menit:** `RefreshDailyAggregatorJob` menghitung ulang metrik transaksi harian ke cache Redis.

### 2. Konfigurasi Supervisor untuk Queue Worker (Audit Logs & Antrean)
Buat berkas konfigurasi Supervisor di `/etc/supervisor/conf.d/tte-worker.conf`:

```ini
[program:tte-audit-worker]
process_name=%(program_name)s_%(process_num)02d
command=docker compose -f /path/to/sismon-tte-V6/docker-compose.yml exec -T app php artisan queue:work redis --queue=audit-logs,default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=root
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/tte-worker.log
```

Muat ulang konfigurasi Supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start tte-audit-worker:*
```

---

## 🔑 Kredensial Default & Rute Akses

### 1. Pemetaan Port Jaringan
| Layanan | Port Host (Default) | Variabel `.env` | Keterangan |
| :--- | :---: | :--- | :--- |
| **HTTP Nginx** | `80` | `HTTP_PORT=80` | Redirect otomatis 301 ke HTTPS |
| **HTTPS Nginx** | `443` | `APP_PORT=443` | Portal Web Utama SSL/TLS |
| **PostgreSQL** | `5432` | `DB_PORT=5432` | Basis Data Utama |
| **Redis** | `6379` | `REDIS_PORT=6379` | Cache & Antrean Asinkron |
| **Adminer** | `8081` | `ADMINER_PORT=8081` | DB Management GUI (Khusus Dev/Testing) |

### 2. Pemetaan Rute Web
| Rute URL | Akses Jaringan | Fungsi & Deskripsi |
| :--- | :--- | :--- |
| `https://localhost/` | **Publik (Internet)** | Beranda Landing Page & Formulir Pendaftaran ASN DKIS Kota Cirebon |
| `https://localhost/pendaftaran` | **Publik (Internet)** | Endpoint submission formulir pendaftaran (Rate limit: 5 req/min) |
| `https://localhost/gateway/login` | **Isolasi VPN Only** | Portal Login Administrator & Verifikator Persandian |
| `https://localhost/admin/dashboard` | **Isolasi VPN + Auth** | Dashboard Eksekutif Statistik Transaksi & Sertifikat Aktif |
| `https://localhost/admin/capaian-skpd` | **Isolasi VPN + Auth** | Analitik Adopsi TTE SKPD (Breakdown Sub-Kategori & Sub-Unit) |
| `https://localhost/admin/verifikasi` | **Isolasi VPN + Auth** | Panel Verifikasi Berkas Tahap 1 & Rekapitulasi Email Dinas |
| `https://localhost/admin/penerbitan` | **Isolasi VPN + Auth** | Panel Penerbitan Sertifikat Tahap 2 BSrE |
| `https://localhost/admin/users` | **Isolasi VPN + Superadmin** | Manajemen Akun Administrator (Safeguard min 1 superadmin aktif) |
| `https://localhost/admin/export/csv` | **Isolasi VPN + Auth** | Unduh Transaksi (Streaming RAM < 20MB + BOM UTF-8) |

### 3. Kredensial Akun Default (Hasil Seeder)
| Nama Akun | Email Login | Password Default | Peran (Role) | Keterangan |
| :--- | :--- | :--- | :---: | :--- |
| **Superadmin Utama** | `admin@cirebonkota.go.id` | `SuperAdmin2026!@#` | `superadmin` | Akun resmi operasional DKIS |
| **Akun Verifikator** | `operator@cirebonkota.go.id` | `Operator!2026#Sandi` | `superadmin` | Operator verifikasi berkas tahap 1 |
| **Akun Penguji** | `ajriajundan@gmail.com` | `Jundan12012005!@#` | `superadmin` | Akun pengujian sistem |

> [!IMPORTANT]
> - Segera ganti kata sandi default dan aktifkan **Multi-Factor Authentication (TOTP)** melalui menu Manajemen Akun saat penerapan di server produksi.
> - Pastikan IP gateway administrator terdaftar pada variabel `VPN_ALLOWED_SUBNETS` di berkas `.env` agar tidak terkena proteksi Stealth 404.

---

## 🛠️ Matriks Troubleshooting & Penanganan Error

```
+-----+-------------------------------+-----------------------------------+-----------------------------------------------------+
| No  | Gejala / Pesan Error          | Akar Masalah (Root Cause)         | Solusi & Tindakan Perbaikan                         |
+-----+-------------------------------+-----------------------------------+-----------------------------------------------------+
| E-01| Error 404 Not Found saat buka | Akses berasal dari IP di luar     | 1. Sambungkan perangkat ke VPN resmi DKIS.          |
|     | /gateway/login atau /admin/*  | whitelist subnet VPN Gateway      | 2. Atau tambahkan subnet IP ke .env:                |
|     | (Stealth Defense Aktif)       | (EnsureVpnAccess aktif).          |    VPN_ALLOWED_SUBNETS="127.0.0.1/32,IP_ANDA/CIDR"  |
|     |                               |                                   | 3. Jalankan: docker compose exec app php artisan    |
|     |                               |                                   |    config:clear                                     |
+-----+-------------------------------+-----------------------------------+-----------------------------------------------------+
| E-02| bind: address already in use  | Port 80, 443, 5432, atau 6379     | 1. Hentikan layanan web lokal (Apache/XAMPP/IIS).   |
|     | saat docker compose up        | telah digunakan aplikasi lain.    | 2. Atau ubah port di .env:                          |
|     |                               |                                   |    HTTP_PORT=8080                                   |
|     |                               |                                   |    APP_PORT=8443                                    |
+-----+-------------------------------+-----------------------------------+-----------------------------------------------------+
| E-03| SQLSTATE[08006] Connection    | Kontainer PostgreSQL belum siap   | 1. Periksa container database:                      |
|     | refused / could not connect   | atau DB_HOST salah konfigurasi.   |    docker compose ps tte_postgres                   |
|     |                               |                                   | 2. Pastikan DB_HOST=postgres (dalam Docker)         |
|     |                               |                                   |    atau DB_HOST=127.0.0.1 (di luar Docker).         |
+-----+-------------------------------+-----------------------------------+-----------------------------------------------------+
| E-04| InvalidArgumentException:     | Folder storage/framework belum    | Jalankan di terminal:                               |
|     | View path not found           | lengkap atau permission terkunci. | mkdir -p storage/framework/{views,sessions,cache}   |
|     |                               |                                   | chmod -R 775 storage bootstrap/cache                |
+-----+-------------------------------+-----------------------------------+-----------------------------------------------------+
| E-05| NOAUTH Authentication req.    | Redis memerlukan password tapi    | Pastikan REDIS_PASSWORD di .env sama persis         |
|     | pada antrean / cache          | nilai di .env belum sinkron.      | dengan nilai di docker-compose.yml (--requirepass). |
+-----+-------------------------------+-----------------------------------+-----------------------------------------------------+
| E-06| 419 Page Expired / CSRF Token | Sesi kedaluwarsa atau reverse     | 1. Pastikan APP_URL di .env menggunakan protokol    |
|     | Mismatch saat submit form     | proxy Nginx belum forward header. |    HTTPS (https://localhost atau https://domain).   |
|     |                               |                                   | 2. Muat ulang (Ctrl+F5) halaman browser.            |
+-----+-------------------------------+-----------------------------------+-----------------------------------------------------+
| E-07| Google reCAPTCHA Offline      | Koneksi keluar ke server Google   | Sistem otomatis beralih ke Local Math CAPTCHA       |
|     | atau kuota API habis          | terputus atau domain baru.        | (HMAC-SHA256). Pendaftaran publik tetap aman.       |
+-----+-------------------------------+-----------------------------------+-----------------------------------------------------+
| E-08| Karakter CSV Rusak di Excel   | Excel tidak mendeteksi UTF-8.     | Sistem otomatis menambahkan header UTF-8 BOM        |
|     |                               |                                   | (\xEF\xBB\xBF) pada streaming download.             |
+-----+-------------------------------+-----------------------------------+-----------------------------------------------------+
```

---

## 📁 Struktur Direktori Sistem

```
sismon-tte-V6/
├── app/
│   ├── Http/
│   │   ├── Controllers/             # Domain Controllers (Public, Admin, Verifikasi, User)
│   │   ├── Middleware/              # EnsureVpnAccess (Zero-Trust), SecurityHeaders, Sanitize
│   │   └── Requests/                # Aturan Validasi Form Request
│   ├── Jobs/                        # Background Queue Jobs (Aggregator, Audit Log, Scanner)
│   ├── Models/                      # Eloquent Entities dengan AES-256-GCM & Blind Indexing
│   ├── Policies/                    # Autorisasi Berkas & Sertifikat TTE
│   └── Services/                    # Domain Service (Crypto, Email Generator, Captcha)
├── bootstrap/                       # Inisialisasi Framework & Middleware Pipeline
├── config/                          # Konfigurasi Aplikasi (app.php, database.php, vpn.php)
├── database/
│   ├── factories/                   # Data Factories untuk Testing
│   ├── migrations/                  # Skema DDL PostgreSQL, Partisi Bulanan, Indeks GIN
│   └── seeders/                     # Master SKPD, Jabatan, Golongan, & Akun Superadmin
├── docker/
│   ├── nginx/                       # Dockerfile Nginx, nginx.conf Hardened, & SSL Certs
│   └── php/                         # Dockerfile PHP 8.3-FPM, php.ini, & Extensions
├── public/
│   ├── css/ & js/                   # Aset UI Lokal
│   ├── images/                      # Aset Branding Resmi DKIS Kota Cirebon, BSrE, & Hero
│   └── index.php                    # Web Application Entrypoint
├── resources/
│   └── views/                       # Blade Views (Landing Page Modern & Admin Dashboard)
├── routes/                          # web.php & console.php
├── storage/                         # Volume Berkas, Log, & Framework Cache Skeleton
├── tests/                           # PHPUnit Feature & Unit Test Suite (30 Files / 189 Tests)
├── vendor/                          # Dependensi Composer PHP Siap Pakai
├── .editorconfig                    # Konfigurasi Standar Editor
├── .env.example                     # Templat Variabel Lingkungan
├── artisan                          # Laravel CLI Tool
├── composer.json & composer.lock    # Definisi Dependensi PHP
├── docker-compose.yml               # Multi-Container Stack (Nginx, PHP, Postgres, Redis)
├── package.json & package-lock.json # Dependensi Frontend & Vite
├── phpunit.xml                      # Konfigurasi Pengujian Otomatis
└── vite.config.js                   # Konfigurasi Bundler Asset
```

---

## 🛡️ Lisensi & Hak Cipta

Hak Cipta © 2026 **Dinas Komunikasi, Informatika dan Statistik (DKIS) Kota Cirebon**.  
Bidang Persandian dan Keamanan Informasi.  
Seluruh hak cipta dilindungi undang-undang.
