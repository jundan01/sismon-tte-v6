# Laporan Pengujian Tolok Ukur (Benchmark Report) & Audit Kueri Basis Data
**Proyek:** Sistem Monitoring Penerbitan TTE (Sismon-TTE V6)  
**Branch:** `phase-1-query-and-index-audit` (Patch Fase 1c)  
**Commit Hash Baseline:** `90075eec4774266db5c8f56ec57fe8efa64250a4`  
**Lingkungan Eksekusi:** Docker Container `tte_app` (PHP 8.3 CLI) & `tte_postgres_bench` (PostgreSQL 16.15 Alpine Terisolasi)  
**Database Pengujian:** `tte_benchmark` (Host Port `5433:5432`, volume `pg_bench_data`, database operasional `tte_monitoring` 100% tidak tersentuh)  
**Metodologi Buffer:** Root Plan Node (`Shared Hit Blocks` / `Shared Read Blocks`), non-recursive  
**Ukuran Sampel Target:** 30 target acak (Seed deterministik: `20261116`)  
**Dataset:** 100.000 rekaman sintetis pada tabel `pendaftaran_tte` dengan seeder skpd riil:
- `MENUNGGU_VERIFIKASI`: 60.000 rekaman (60%)
- `DISETUJUI`: 20.000 rekaman (20%)
- `DITOLAK`: 20.000 rekaman (20%)
- Status terdistribusi acak (*interleaved*, bukan berblok)  
**Tanggal Pengujian:** 16 November 2026  

---

## 1. Spesifikasi Mesin & Konfigurasi Server Basis Data Benchmark

Seluruh spesifikasi perangkat keras dan konfigurasi runtime dicatat secara programmatic pada [phase1_pg_env.json](file:///e:/sismon-tte-V6/bench/results/phase1_pg_env.json):

| Komponen | Spesifikasi / Konfigurasi | Keterangan |
|---|---|---|
| **Host Processor (CPU)** | `Intel(R) Core(TM) i5-7300U CPU @ 2.60GHz` | 2 core fisik, 4 logical thread |
| **Host Memory (RAM)** | `15.88 GB` | Total memori fisik sistem operasi Windows |
| **Sistem Operasi Host** | `Windows 10/11 x64` | Lingkungan host utama |
| **Docker Engine & Desktop** | `Docker 29.9.3, build 5457199` | `Docker Desktop 4.99.0` |
| **WSL2 Runtime** | `WSL version 3.0.5.0` | Kernel: `6.18.58.1-1` |
| **PostgreSQL Engine** | `PostgreSQL 16.15` | `x86_64-pc-linux-musl (Alpine 15.2.0), 64-bit` |
| **`shared_buffers`** | `128MB` | Alokasi memori buffer bersama untuk caching halaman |
| **`work_mem`** | `4MB` | Batas memori operasi pengurutan/hashing per operasi kueri |
| **`effective_cache_size`** | `4GB` | Estimasi kapasitas disk cache OS |
| **`random_page_cost`** | `4` | Biaya pembacaan acak non-sekuensial |
| **Mode Optimizer** | `Natural Planner` | `enable_seqscan = on` (default); **tidak ada manipulasi optimizer session** |

---

## 2. Audit Kueri: Jalur Pencarian Lama vs. Jalur Terpisah (Temuan K1)

### 2.1. Permasalahan Jalur Pencarian Lama (Compound OR Anti-Pattern)
Pada arsitektur awal aplikasi (`app/Http/Controllers/VerifikasiController.php` dan `DashboardController.php`), seluruh kata kunci pencarian digabungkan ke dalam satu kueri SQL tunggal yang menyatukan pencarian teks parsial (`ILIKE '%...%'`) dan pencarian kesetaraan kolom terenkripsi (`nik = ? OR nip = ?`):

```sql
-- Jalur Lama: Algoritma 4 Compound OR (Kueri A & B)
SELECT * FROM pendaftaran_tte
WHERE status = 'MENUNGGU_VERIFIKASI'
  AND (
    nama_lengkap ILIKE '%3274010101900001%'
    OR nomor_referensi ILIKE '%3274010101900001%'
    OR deskripsi ILIKE '%3274010101900001%'
    OR nik = 'LY7AuiGCoGuT76o6AJ8Yr1/Wt44Hf2JXYZdVHc5BEk4='
    OR nip = 'LY7AuiGCoGuT76o6AJ8Yr1/Wt44Hf2JXYZdVHc5BEk4='
  )
ORDER BY created_at ASC
LIMIT 15;
```

**Temuan Empiris `EXPLAIN (ANALYZE, BUFFERS)`:**
1. **Kegagalan Pemanfaatan Indeks B-Tree:** Karena operator wildcard `ILIKE '%...'` pada kolom teks tidak dapat diakselerasi indeks B-Tree standar, perencana kueri PostgreSQL memilih `Parallel Seq Scan`.
2. **Lonjakan Latensi & I/O:** Memindai 3.850–3.924 blok halaman fisik data heap secara linier, dengan latensi median mencapai **342,780 ms** (Query A) dan **434,474 ms** (Query B).
3. **Integritas Naskah:** Membuktikan secara matematis dan empiris bahwa pencarian gabungan compound OR tidak berjalan dalam kompleksitas $\mathcal{O}(\log N)$.

---

### 2.2. Rekayasa Perbaikan: Pemisahan Jalur Pencarian & Sanitasi Wildcard (Patch Fase 1b & 1c)
Arsitektur controller direfaktor untuk memisahkan pencarian menjadi dua rute deterministik:
1. **Jalur Terpisah 1: Exact-Match Identitas Terenkripsi ($\mathcal{O}(\log N)$)**
   - Mendeteksi masukan 16-digit angka murni (NIK) atau 18-digit angka murni (NIP).
   - Sanitasi karakter pemisah: `$digits = preg_replace('/\D/', '', $trimmed)`.
   - Mengenkripsi parameter menggunakan kunci deterministik (`CryptoHelper::encryptField()`).
   - Eksekusi kesetaraan langsung dengan *strict parameter binding* (`where('nik', $encryptedNik)` atau `where('nip', $encryptedNip)`).
2. **Jalur Terpisah 2: Pencarian Teks Parsial (Wildcard ILIKE)**
   - Jika masukan berupa nama atau nomor referensi alfabetis, dialihkan ke kolom teks (`nama_lengkap`, `nomor_referensi`) tanpa menyentuh kolom identitas sensitif.
   - Dilakukan sanitasi karakter wildcard SQL (`addcslashes($trimmed, '%_\\')`).
3. **Penyediaan Indeks Universal Seluruh Status:**
   - Ditambahkan migrasi indeks `idx_pendaftaran_nik_all` dan `idx_pendaftaran_nip_all` pada predikat `WHERE nik IS NOT NULL` / `WHERE nip IS NOT NULL`.
   - Ditandai `[TODO-PENULIS]` untuk evaluasi sebelum merger, dan dirancang menggunakan `CREATE INDEX CONCURRENTLY` di luar transaksi untuk beban produksi.

---

## 3. Tabel Hasil Pengukuran Empiris Tolok Ukur (Query A – H + Kontrol K1)

Seluruh angka pada tabel di bawah ini diambil secara langsung dari berkas resmi [phase1_summary.json](file:///e:/sismon-tte-V6/bench/results/phase1_summary.json), dievaluasi pada **30 target acak independen** (Seed: `20261116`):

| ID Kueri | Rute / Pola Kueri | Tipe Pemindaian (*Primary Scan*) | Indeks Basis Data yang Digunakan | Median (ms) | Mean (ms) | Min (ms) | Max (ms) | SD (ms) | Root Shared Hit (Blok) | Root Shared Read (Blok) |
|:---:|---|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Query A** | Jalur Lama: Compound `OR` (Teks + Cipher, Filter Status Aktif) | `Seq Scan` (`Parallel Seq Scan`) | *None (Seq Scan)* | **342,780** | 355,424 | 242,958 | 747,192 | 92,611 | 3.924 | 0 |
| **Query B** | Jalur Lama: Compound `OR` (Teks + Cipher, Tanpa Filter Status) | `Seq Scan` | *None (Seq Scan)* | **434,474** | 522,597 | 350,982 | 1.117,544 | 186,730 | 3.850 | 0 |
| **Query C** | **Jalur Terpisah: Exact-Match NIK** (`status = 'MENUNGGU_VERIFIKASI'`) | `Index Scan` | **`idx_pendaftaran_nik_all`** | **0,109** | 0,149 | 0,075 | 0,785 | 0,130 | **4** | 0 |
| **Query D** | **Jalur Terpisah: Exact-Match NIK** (`status != 'DITOLAK'` / Parsial) | `Index Scan` | **`idx_pendaftaran_nik_active`** | **0,099** | 0,106 | 0,073 | 0,171 | 0,027 | **4** | 0 |
| **Query E** | **Jalur Terpisah: Exact-Match NIP** (`status = 'MENUNGGU_VERIFIKASI'`) | `Index Scan` | **`idx_pendaftaran_nip_all`** | **0,114** | 0,121 | 0,100 | 0,188 | 0,021 | **4** | 0 |
| **Query F** | Jalur Terpisah: Pencarian Teks Murni (ILIKE Nama/Ref Escaped) | `Seq Scan` (`Parallel Seq Scan`) | *None (Seq Scan)* | **241,523** | 302,527 | 170,810 | 919,302 | 163,011 | 3.924 | 0 |
| **Query G** | **Jalur Terpisah: Exact-Match NIK Tanpa Filter Status** (Semua Status) | `Index Scan` | **`idx_pendaftaran_nik_all`** | **0,169** | 0,471 | 0,099 | 7,805 | 1,372 | **4** | 0 |
| **Query H** | **Pencarian Majemuk Dua Kolom Terenkripsi** (`nik = ? OR nip = ?`) | `Bitmap Heap Scan` | **`idx_pendaftaran_nik_all & idx_pendaftaran_nip_all`** | **0,146** | 0,182 | 0,106 | 0,439 | 0,092 | **7** | 0 |
| **Control K1** | **Kontrol Ablasi: Query G dengan Indeks di-drop** (Tanpa `idx_pendaftaran_nik_all`) | `Seq Scan` | *None (Index Dropped for Control)* | **19,164** | 20,501 | 11,091 | 38,069 | 6,189 | 3.850 | 0 |

---

## 4. Pembahasan Hasil Pengukuran Empiris

1. **Rasio Percepatan (Speedup Ratio):**
   - Pemisahan jalur exact-match NIK (Query C: median **0,109 ms**) menghasilkan percepatan kinerja nyata sebesar **3.144,7× lipat** dibandingkan jalur lama compound OR (Query A: median **342,780 ms**).
   - Pada NIP (Query E: median **0,114 ms**), percepatan mencapai **3.006,8× lipat** dibandingkan Query A.
2. **Efisiensi Buffer Memori Node Akar:**
   - Jalur lama memindai **3.924 blok halaman buffer** pada node akar.
   - Jalur terpisah exact-match (Query C, D, E, G) hanya menyentuh **4 blok halaman buffer** (3 blok traversal internal B-Tree + 1 blok data heap), mewakili reduksi I/O memori sebesar **99,9%**.
3. **Pembuktian Kontrol K1 (Ablasi Indeks):**
   - Ketika indeks `idx_pendaftaran_nik_all` di-drop sementara pada Query G, tipe pemindaian langsung merosot menjadi `Seq Scan` dengan pembacaan **3.850 blok buffer** dan latensi median melonjak ke **19,164 ms** (naik **113,4× lipat** dari 0,169 ms).
   - Eksperimen ini memvalidasi secara kausal bahwa akselerasi performa pada Query C, D, E, dan G murni bersumber dari struktur indeks B-Tree pada ciphertext deterministik, bukan artifak caching semata.
4. **Efisiensi Kueri Majemuk Terenkripsi (Query H):**
   - Query H (`nik = ? OR nip = ?`) membuktikan bahwa PostgreSQL mampu mengeksekusi operator `OR` antar-kolom berindeks melalui simpul `BitmapOr` dengan latensi median **0,146 ms** dan **7 blok buffer**. Hal ini membuktikan bahwa operator `OR` dapat bekerja optimal selama seluruh cabangnya terindeks.

---

## 5. Pengujian Regresi Otomatis (Automated Test Suite)

Rangkaian pengujian pada berkas `tests/Feature/SearchIndexUsageTest.php` dilengkapi guard integritas ketat dan telah lulus pengujian:

```text
$ docker compose exec -T -e DB_DATABASE=tte_testing app php artisan test --filter=SearchIndexUsageTest

   PASS  Tests\Feature\SearchIndexUsageTest
  ✓ verifikasi controller nik search generates and executes index scan  37.44s  
  ✓ verifikasi controller nip search generates and executes index scan   1.27s  
  ✓ dashboard controller nik search generates and executes index scan    6.72s  
  ✓ verifikasi controller routes text to wildcard with escaping          1.68s  
  ✓ rejected status is indexed and uses index scan                       1.32s  

  Tests:    5 passed (35 assertions)
  Duration: 54.08s
```

---

## 6. Berkas Mentah Hasil Pengujian (Raw Output Artifacts)

- Spesifikasi Mesin & Server Environment: [phase1_pg_env.json](file:///e:/sismon-tte-V6/bench/results/phase1_pg_env.json)
- Ringkasan Statistik 30 Sampel (JSON): [phase1_summary.json](file:///e:/sismon-tte-V6/bench/results/phase1_summary.json)
- Log Ilustrasi Rencana Kueri (Format Teks): [phase1_explain_plans.txt](file:///e:/sismon-tte-V6/bench/results/phase1_explain_plans.txt)
- Pohon Rencana Eksekusi Mentah JSON per Skenario:
  - [phase1_query_a_combined_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_query_a_combined_explain.json)
  - [phase1_query_b_combined_nostatus_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_query_b_combined_nostatus_explain.json)
  - [phase1_query_c_separated_nik_status_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_query_c_separated_nik_status_explain.json)
  - [phase1_query_d_separated_nik_active_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_query_d_separated_nik_active_explain.json)
  - [phase1_query_e_separated_nip_status_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_query_e_separated_nip_status_explain.json)
  - [phase1_query_f_separated_text_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_query_f_separated_text_explain.json)
  - [phase1_query_g_separated_nik_all_status_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_query_g_separated_nik_all_status_explain.json)
  - [phase1_query_h_combined_nik_nip_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_query_h_combined_nik_nip_explain.json)
  - [phase1_control_k1_no_index_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_control_k1_no_index_explain.json)
