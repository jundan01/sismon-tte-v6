# Laporan Pengujian Tolok Ukur (Benchmark Report) & Audit Kueri Basis Data
**Proyek:** Sistem Monitoring Penerbitan TTE (Sismon-TTE V6)  
**Branch:** `phase-1-query-and-index-audit` (Patch Fase 1b)  
**Lingkungan Eksekusi:** Docker Container `tte_app` (PHP 8.3 CLI) & `tte_postgres_bench` (PostgreSQL 16.15 Alpine Terisolasi)  
**Database Pengujian:** `tte_benchmark` (Port Host `5433:5432`, volume `pg_bench_data`, database operasional `tte_monitoring` 100% tidak tersentuh)  
**Dataset:** 100.000 rekaman sintetis pada tabel `pendaftaran_tte` dengan distribusi status:
- `MENUNGGU_VERIFIKASI`: 60.000 rekaman (60%)
- `DISETUJUI`: 20.000 rekaman (20%)
- `DITOLAK`: 20.000 rekaman (20%)
- **Total:** 100.000 rekaman  
**Tanggal Pengujian:** 15 November 2026  

---

## 1. Spesifikasi Lingkungan Server Basis Data Benchmark

Sesuai instruksi audit integritas dan replikabilitas pengujian ilmiah, pengujian tolok ukur dieksekusi pada kontainer PostgreSQL terisolasi (`tte_postgres_bench`) guna mencegah bias akibat aktivitas transaksi operasional. Konfigurasi runtime basis data dicatat secara programmatic ke dalam berkas `bench/results/phase1_pg_env.json`:

| Parameter Konfigurasi | Nilai Terkonfigurasi | Deskripsi / Catatan Perencana Kueri |
|---|---|---|
| **Engine / Versi** | `PostgreSQL 16.15` | `x86_64-pc-linux-musl, compiled by gcc (Alpine 15.2.0) 15.2.0, 64-bit` |
| **`shared_buffers`** | `128MB` | Alokasi memori buffer bersama untuk caching halaman data basis data |
| **`work_mem`** | `4MB` | Batas memori operasi pengurutan (*sort*) dan hash per operasi kueri |
| **`effective_cache_size`** | `4GB` | Estimasi kapasitas memori yang tersedia untuk disk caching OS |
| **`random_page_cost`** | `4` | Rasio biaya estimasi I/O pembacaan acak halaman data non-sekuensial |
| **Metode Optimasi** | **Natural Planner** | `enable_seqscan = on` (default); **tidak ada pemaksaan indeks melalui pengubahan parameter optimizer** |

---

## 2. Audit Kueri: Jalur Pencarian Lama vs. Jalur Terpisah (Temuan K1)

### 2.1. Permasalahan Jalur Pencarian Lama (Compound OR Anti-Pattern)
Pada arsitektur awal aplikasi (`app/Http/Controllers/VerifikasiController.php` dan `DashboardController.php`), seluruh kata kunci pencarian dilempar ke dalam satu kueri SQL tunggal yang menggabungkan pencarian teks parsial (`ILIKE '%...%'`) dan pencarian kesetaraan kolom terenkripsi (`nik = ? OR nip = ?`):

```sql
-- Jalur Lama: Algoritma 4 Compound OR (Kueri A & B)
SELECT * FROM pendaftaran_tte
WHERE status = 'MENUNGGU_VERIFIKASI'
  AND (
    nama_lengkap ILIKE '%3274000000030001%'
    OR nomor_referensi ILIKE '%3274000000030001%'
    OR deskripsi ILIKE '%3274000000030001%'
    OR nik = 'LY7AuiGCoGuT76o6AJ8Yr1/Wt44Hf2JXYZdVHc5BEk4='
    OR nip = 'LY7AuiGCoGuT76o6AJ8Yr1/Wt44Hf2JXYZdVHc5BEk4='
  )
ORDER BY created_at ASC
LIMIT 15;
```

**Temuan Analisis `EXPLAIN (ANALYZE, BUFFERS)`:**
1. **Kegagalan Pemanfaatan Indeks B-Tree:** Karena operator wildcard `ILIKE '%...'` pada kolom teks tidak dapat dipetakan ke pohon B-Tree konvensional, PostgreSQL *cost-based optimizer* menyimpulkan bahwa evaluasi gabungan klausa `OR` teks dan indeks deterministik lebih murah dilakukan dengan memindai seluruh tabel.
2. **Lonjakan Latensi & I/O:** Eksekusi kueri memicu `Parallel Seq Scan` dengan 2 worker latar belakang, memindai seluruh 3.704–3.778 blok halaman fisik (setara 15.038 shared hits agregat antar-worker), dan menghasilkan latensi median sebesar **409,379 ms** (Query A) dan **276,456 ms** (Query B).
3. **Integritas Klaim Ilmiah:** Temuan ini secara formal membantah klaim pada draf naskah sebelumnya bahwa pencarian compound OR selalu berjalan dalam $\mathcal{O}(\log N)$.

---

### 2.2. Rekayasa Perbaikan: Pemisahan Jalur Pencarian & Sanitasi Wildcard (Patch Fase 1b)
Untuk memulihkan kompleksitas pencarian logaritmik $\mathcal{O}(\log N)$ dan kepatuhan terhadap UU PDP No. 27/2022, diterapkan arsitektur **Pemisahan Jalur Pencarian (Search Route Separation)** pada layer aplikasi controller sebelum menyusun pernyataan SQL:

1. **Jalur Terpisah 1: Exact-Match Identitas Terenkripsi ($\mathcal{O}(\log N)$)**
   - Mendeteksi masukan 16-digit angka murni (format NIK Indonesia) atau 18-digit angka murni (format NIP ASN).
   - Menghapus karakter spasi atau tanda hubung: `$digits = preg_replace('/\D/', '', $trimmed)`.
   - Mengenkripsi nilai pencarian menggunakan algoritma deterministik (`CryptoHelper::encryptField()`).
   - Melakukan query kesetaraan langsung dengan *strict parameter binding*:
     - NIK (16 digit): `$q->where('nik', $encryptedNik);`
     - NIP (18 digit): `$q->where('nip', $encryptedNip);`
2. **Jalur Terpisah 2: Pencarian Teks Parsial (Wildcard ILIKE)**
   - Jika masukan berupa nama atau nomor referensi alfabetis, kueri dialihkan secara eksklusif ke kolom teks (`nama_lengkap`, `nomor_referensi`) tanpa membebani kolom `nik` dan `nip`.
   - Dilakukan sanitasi karakter wildcard SQL (`addcslashes($trimmed, '%_\\')`) untuk mencegah injeksi pola wildcard tidak disengaja.
3. **Penyediaan Indeks Universal Seluruh Status:**
   - Ditambahkan migrasi indeks `idx_pendaftaran_nik_all` (`CREATE INDEX idx_pendaftaran_nik_all ON pendaftaran_tte (nik) WHERE nik IS NOT NULL`) dan `idx_pendaftaran_nip_all` agar data dengan status `DITOLAK` maupun data arsip historis tetap terlindungi indeks pencarian cepat.

---

## 3. Tabel Hasil Pengukuran Empiris Tolok Ukur (Query A – H)

Pengukuran dilakukan dengan prosedur:
- **Warm-up:** 1× pemanasan kueri sebelum pencatatan data.
- **Repetisi:** 5× pengulangan independen untuk setiap kueri.
- **Statistik:** Median, Rata-rata (Mean), Nilai Minimum, dan Nilai Maksimum.
- **Metrik Buffer:** Blok halaman yang diambil dari cache PostgreSQL (`Shared Hit`) dan dari disk storage (`Shared Read`).

| ID Kueri | Rute / Pola Kueri | Tipe Pemindaian (*Primary Scan*) | Indeks Basis Data yang Digunakan | Median (ms) | Mean (ms) | Min (ms) | Max (ms) | Shared Hit (Blok) | Shared Read (Blok) |
|:---:|---|---|---|:---:|:---:|:---:|:---:|:---:|:---:|
| **Query A** | Jalur Lama: Compound `OR` (Teks + Cipher, Filter Status Aktif) | `Seq Scan` (`Parallel Seq Scan`) | *None (Seq Scan)* | **409,379** | 387,770 | 254,669 | 491,358 | 15.038 | 0 |
| **Query B** | Jalur Lama: Compound `OR` (Teks + Cipher, Tanpa Filter Status) | `Seq Scan` | *None (Seq Scan)* | **276,456** | 276,533 | 252,123 | 307,204 | 7.408 | 0 |
| **Query C** | **Jalur Terpisah: Exact-Match NIK** (`status = 'MENUNGGU_VERIFIKASI'`) | `Index Scan` | **`idx_pendaftaran_nik_all`** | **0,168** | 0,189 | 0,127 | 0,334 | **8** | 0 |
| **Query D** | **Jalur Terpisah: Exact-Match NIK** (`status != 'DITOLAK'` / Parsial) | `Index Scan` | **`idx_pendaftaran_nik_active`** | **0,149** | 0,164 | 0,111 | 0,248 | **8** | 0 |
| **Query E** | **Jalur Terpisah: Exact-Match NIP** (`status = 'MENUNGGU_VERIFIKASI'`) | `Index Scan` | **`idx_pendaftaran_nip_all`** | **0,132** | 0,137 | 0,123 | 0,158 | **8** | 0 |
| **Query F** | Jalur Terpisah: Pencarian Teks Murni (ILIKE Nama/Ref Escaped) | `Seq Scan` (`Parallel Seq Scan`) | *None (Seq Scan)* | **181,576** | 170,589 | 106,332 | 264,385 | 15.038 | 0 |
| **Query G** | **Jalur Terpisah: Exact-Match NIK Tanpa Filter Status** (Semua Status) | `Index Scan` | **`idx_pendaftaran_nik_all`** | **0,145** | 0,159 | 0,124 | 0,236 | **8** | 0 |
| **Query H** | **Pencarian Majemuk Dua Kolom Terenkripsi** (`nik = ? OR nip = ?`) | `Bitmap Heap Scan` | **`idx_pendaftaran_nik_all & idx_pendaftaran_nip_all`** | **0,192** | 0,219 | 0,175 | 0,305 | **26** | 0 |

---

## 4. Analisis Teknis & Pembahasan Hasil Pengukuran

### 4.1. Efisiensi Komparasi Jalur Lama vs. Jalur Terpisah
1. **Percepatan Latensi Kueri (Speedup Ratio):**
   - Perbandingan antara **Query A** (Jalur Lama gabungan teks dan ciphertext: median **409,379 ms**) terhadap **Query C** (Jalur Terpisah exact-match NIK: median **0,168 ms**) menghasilkan percepatan kinerja nyata sebesar **2.436,8× lipat**.
   - Pada kueri NIP (Query E: **0,132 ms**), percepatan mencapai **3.101,4× lipat** dibandingkan Query A.
2. **Reduksi Beban I/O Memori (*Shared Buffer Hits*):**
   - Jalur lama (Query A) menyedot **15.038 blok buffer** karena perencana kueri harus melakukan dekomposisi data ke multi-proses worker dan memeriksa 100.000 baris secara linier.
   - Jalur terpisah exact-match (Query C, D, E, G) hanya membutuhkan **8 blok buffer** (3 blok traversal node internal B-Tree + 1 blok data leaf/heap). Hal ini mengeliminasi *cache contention* pada basis data produksi secara signifikan.

### 4.2. Perilaku Kueri G dan Kueri H (Temuan Kunci Tambahan)
1. **Query G (Pencarian NIK Lintas Status):**
   - Dengan adanya indeks `idx_pendaftaran_nik_all`, kueri exact-match tanpa predikat status berhasil dieksekusi melalui `Index Scan` murni dengan median latensi **0,145 ms**. Ini membuktikan bahwa pendaftaran berstatus `DITOLAK` dapat ditemukan secepat data aktif tanpa degradasi performa.
2. **Query H (Kueri Gabungan Dua Kolom Terenkripsi):**
   - Kueri `WHERE (nik = ? OR nip = ?)` membuktikan kemampuan PostgreSQL dalam mengeksekusi operasi `OR` antar-kolom berindeks melalui simpul **`BitmapOr`** yang menggabungkan `Bitmap Index Scan on idx_pendaftaran_nik_all` dan `Bitmap Index Scan on idx_pendaftaran_nip_all`.
   - Latensi median Query H tetap berada pada orde sub-milidetik (**0,192 ms**) dengan hanya **26 blok buffer**. Hal ini menegaskan bahwa klausa `OR` itu sendiri bukanlah penyebab sequential scan; penyebab utama kegagalan indeks pada jalur lama adalah penyatuan klausa kesetaraan dengan klausa pola pencarian parsial `ILIKE '%...'`.

---

## 5. Pengujian Regresi Otomatis (Automated Test Suite)

Untuk menjamin kueri controller aplikasi benar-benar menghasilkan dan mengeksekusi rencana `Index Scan` secara alami tanpa manipulasi pengaturan optimizer, rangkaian pengujian pada berkas `tests/Feature/SearchIndexUsageTest.php` dirancang ulang dengan ketentuan:
1. **Larangan `enable_seqscan = off`:** Pengujian tidak melakukan *session override* pada variabel optimizer PostgreSQL. Penggunaan indeks divalidasi berdasarkan kalkulasi biaya alami (*cost calculation*) PostgreSQL.
2. **Penyadapan SQL Riil Controller via `DB::listen()`:** Pengujian memicu permintaan HTTP riil ke `route('admin.verifikasi.index')` dan `route('admin.dashboard')`, menyadap string SQL dan parameter binding yang dihasilkan Eloquent, lalu menjalankan `EXPLAIN (FORMAT JSON)` terhadap kueri aktual tersebut.

### Hasil Eksekusi Unit & Feature Test:
```text
$ docker compose exec -T -e DB_DATABASE=tte_testing app php artisan test --filter=SearchIndexUsageTest

   PASS  Tests\Feature\SearchIndexUsageTest
  ✓ verifikasi controller nik search generates and executes index scan  11.21s  
  ✓ verifikasi controller nip search generates and executes index scan   0.52s  
  ✓ dashboard controller nik search generates and executes index scan    1.49s  
  ✓ verifikasi controller routes text to wildcard with escaping          0.54s  
  ✓ rejected status is indexed and uses index scan                       0.52s  

  Tests:    5 passed (35 assertions)
  Duration: 17.37s
```

---

## 6. Daftar Berkas Mentah Hasil Pengujian (Raw Output Artifacts)

Seluruh data numerik, rencana kueri tekstual, dan pohon eksekusi JSON pada laporan ini telah diverifikasi dan tersimpan pada repositori lokal di direktori `bench/results/`:

1. **Konfigurasi Lingkungan Server PostgreSQL:**
   - [phase1_pg_env.json](file:///e:/sismon-tte-V6/bench/results/phase1_pg_env.json)
2. **Tabel Ringkasan Metrik Pengukuran (JSON):**
   - [phase1_summary.json](file:///e:/sismon-tte-V6/bench/results/phase1_summary.json)
3. **Log Lengkap Format Teks `EXPLAIN (ANALYZE, BUFFERS)` (A – H):**
   - [phase1_explain_plans.txt](file:///e:/sismon-tte-V6/bench/results/phase1_explain_plans.txt)
4. **Berkas Pohon Eksekusi Mentah JSON per Skenario Kueri:**
   - [phase1_query_a_combined_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_query_a_combined_explain.json)
   - [phase1_query_b_combined_nostatus_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_query_b_combined_nostatus_explain.json)
   - [phase1_query_c_separated_nik_status_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_query_c_separated_nik_status_explain.json)
   - [phase1_query_d_separated_nik_active_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_query_d_separated_nik_active_explain.json)
   - [phase1_query_e_separated_nip_status_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_query_e_separated_nip_status_explain.json)
   - [phase1_query_f_separated_text_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_query_f_separated_text_explain.json)
   - [phase1_query_g_separated_nik_all_status_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_query_g_separated_nik_all_status_explain.json)
   - [phase1_query_h_combined_nik_nip_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_query_h_combined_nik_nip_explain.json)
