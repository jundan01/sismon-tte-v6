# Laporan Pelaksanaan Fase 1c: Patch Pengukuran, Kebersihan, dan Kontrol Eksperimen

**Proyek:** Sistem Monitoring Penerbitan TTE (Sismon-TTE V6)  
**Branch:** `phase-1-query-and-index-audit`  
**Baseline Git Commit:** `90075eec4774266db5c8f56ec57fe8efa64250a4`  
**Tanggal Pengujian:** 16 November 2026  
**Status Naskah Ilmiah:** **100% Tidak Disentuh** (Sesuai instruksi batasan)  

---

## 1. Ringkasan Tindakan & Kebersihan Repositori (Item 1)

1. **Integritas Working Tree:**
   - Seluruh perubahan diverifikasi terhadap `HEAD`.
   - Tidak ada duplikasi fungsi atau logika pada `RunPhase1ExplainBenchmark.php`, `VerifikasiController.php`, `DashboardController.php`, maupun `config/database.php`.
   - Pengujian sintaks via `php -l` dilakukan pada seluruh berkas PHP yang dimodifikasi tanpa ada kesalahan sintaks (*0 syntax errors*).
2. **Pencegahan Konflik Berkas:**
   - Tidak ada pengeditan berkas secara simultan dengan editor eksternal.

---

## 2. Spesifikasi Mesin & Konfigurasi Lingkungan (Item 3)

Dicatat secara programmatic pada [phase1_pg_env.json](file:///e:/sismon-tte-V6/bench/results/phase1_pg_env.json):

| Parameter | Nilai Terverifikasi | Sumber Data / Keterangan |
|---|---|---|
| **Host CPU** | `Intel(R) Core(TM) i5-7300U CPU @ 2.60GHz` | 2 core fisik, 4 logical thread |
| **Host RAM** | `15.88 GB` | Total Physical Memory Windows |
| **Sistem Operasi** | `Windows 10/11 x64` | Windows Host |
| **Versi Docker Desktop** | `Docker Desktop 4.99.0` | ProductVersion Launcher |
| **Versi Docker Engine** | `Docker 29.9.3, build 5457199` | Docker CLI Engine |
| **Versi WSL2** | `WSL version 3.0.5.0` | Kernel: `6.18.58.1-1` |
| **Git Commit Hash** | `90075eec4774266db5c8f56ec57fe8efa64250a4` | Commit ID branch `phase-1-query-and-index-audit` |
| **PostgreSQL Engine** | `PostgreSQL 16.15` | `x86_64-pc-linux-musl (Alpine 15.2.0)` |
| **`shared_buffers`** | `128MB` | Memory buffer pool PostgreSQL |
| **`work_mem`** | `4MB` | Memory limit sorting/hashing |
| **`effective_cache_size`** | `4GB` | Estimasi disk cache OS |
| **`random_page_cost`** | `4` | Cost ratio pembacaan acak |
| **Target Database** | `tte_benchmark` | Container terisolasi `tte_postgres_bench` (Port `5433:5432`) |

---

## 3. Metodologi Buffer Node Akar (Item 2)

Pada pengukuran sebelumnya (Fase 1b), penghitungan buffer menggunakan metode akumulasi rekursif antar-subnode plan, yang menyebabkan penggandaan hitungan (*multi-counting*) akibat kontribusi multi-worker.

Pada Fase 1c ini:
- Metrik **`Shared Hit Blocks`** dan **`Shared Read Blocks`** diambil **murni dari Node Akar (Root Plan Node)** tanpa rekursi (`$parsed['Plan']['Shared Hit Blocks'] ?? 0`).
- Pendekatan ini mencerminkan I/O buffer bersih yang dihabiskan oleh seluruh operasi kueri di tingkat agregat perencana PostgreSQL.
- Hasil: Kueri exact-match (Query C, D, E, G) mencatatkan **tepat 4 blok buffer** pada root node (3 blok traversal internal B-Tree + 1 blok data heap), dan Query H mencatatkan **7 blok buffer**.

---

## 4. Generator Data Sintetis Realistis (Item 6)

Dataset 100.000 rekaman pada tabel `pendaftaran_tte` dibangun ulang secara deterministik menggunakan generator bijejtif dengan **Seed: `20261116`**:

1. **Format NIP Indonesia (18 Digit):**
   - Menggunakan **500 kohort realistis** kombinasi `YYYYMMDD` (tanggal lahir, 1970–1999) dan `YYYYMM` (TMT pengangkatan CPNS, usia 22–29 tahun).
   - Format: `YYYYMMDD` (8 digit) + `YYYYMM` (6 digit) + `Gender` (1 digit: 1=Pria, 2=Wanita) + `NoUrut` (3 digit: 001–100 per kohort).
   - Memenuhi syarat realistis di mana ratusan ASN berbagi tanggal lahir dan TMT yang sama, sekaligus menjamin keunikan 100% pada konstrain unik `idx_pendaftaran_nip_active`.
2. **Format NIK Indonesia (16 Digit):**
   - Wilayah: 5 kode kecamatan Kota Cirebon (`327401` Kejaksan, `327402` Lemahwungkuk, `327403` Harjamukti, `327404` Pekalipan, `327405` Kesambi).
   - Tanggal lahir `DDMMYY` dengan aturan resmi Kemendagri (+40 pada nilai hari untuk wanita).
   - Nomor urut 4 digit (`0001`–`9999`).
3. **Distribusi Status Acak Interleaved:**
   - Proporsi: Tepat **60.000 `MENUNGGU_VERIFIKASI`** (60%), **20.000 `DISETUJUI`** (20%), dan **20.000 `DITOLAK`** (20%).
   - Status diacak secara merata menggunakan algoritma Fisher-Yates dengan seed deterministik, sehingga posisi status tersebar acak (*interleaved*) di seluruh tabel, bukan dalam blok besar.
4. **Target Pencarian Acak:**
   - Dipilih **30 target acak independen** (20 target aktif `MENUNGGU_VERIFIKASI` dan 10 target `DITOLAK`).
   - Setiap target dievaluasi satu per satu untuk menghitung statistik reliabel: Median, Mean, Nilai Minimum, Nilai Maksimum, dan Standar Deviasi (SD).

---

## 5. Tabel Hasil Pengukuran Empiris (Query A – H + Kontrol K1)

Seluruh angka diambil dari berkas mentah [phase1_summary.json](file:///e:/sismon-tte-V6/bench/results/phase1_summary.json):

| ID Kueri | Rute / Skenario Kueri | Tipe Pemindaian | Indeks yang Digunakan | Median (ms) | Mean (ms) | Min (ms) | Max (ms) | SD (ms) | Root Shared Hit | Root Shared Read |
|:---:|---|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Query A** | Jalur Lama: Compound `OR` (Teks + Cipher, Filter Status Aktif) | `Seq Scan` | *None (Seq Scan)* | **342,780** | 355,424 | 242,958 | 747,192 | 92,611 | 3.924 | 0 |
| **Query B** | Jalur Lama: Compound `OR` (Teks + Cipher, Tanpa Filter Status) | `Seq Scan` | *None (Seq Scan)* | **434,474** | 522,597 | 350,982 | 1.117,544 | 186,730 | 3.850 | 0 |
| **Query C** | **Jalur Terpisah: Exact-Match NIK** (`status = 'MENUNGGU_VERIFIKASI'`) | `Index Scan` | **`idx_pendaftaran_nik_all`** | **0,109** | 0,149 | 0,075 | 0,785 | 0,130 | **4** | 0 |
| **Query D** | **Jalur Terpisah: Exact-Match NIK** (`status != 'DITOLAK'` / Parsial) | `Index Scan` | **`idx_pendaftaran_nik_active`** | **0,099** | 0,106 | 0,073 | 0,171 | 0,027 | **4** | 0 |
| **Query E** | **Jalur Terpisah: Exact-Match NIP** (`status = 'MENUNGGU_VERIFIKASI'`) | `Index Scan` | **`idx_pendaftaran_nip_all`** | **0,114** | 0,121 | 0,100 | 0,188 | 0,021 | **4** | 0 |
| **Query F** | Jalur Terpisah: Pencarian Teks Murni (ILIKE Nama/Ref Escaped) | `Seq Scan` | *None (Seq Scan)* | **241,523** | 302,527 | 170,810 | 919,302 | 163,011 | 3.924 | 0 |
| **Query G** | **Jalur Terpisah: Exact-Match NIK Tanpa Filter Status** (Semua Status) | `Index Scan` | **`idx_pendaftaran_nik_all`** | **0,169** | 0,471 | 0,099 | 7,805 | 1,372 | **4** | 0 |
| **Query H** | **Pencarian Majemuk Dua Kolom Terenkripsi** (`nik = ? OR nip = ?`) | `Bitmap Heap Scan` | **`idx_pendaftaran_nik_all & idx_pendaftaran_nip_all`** | **0,146** | 0,182 | 0,106 | 0,439 | 0,092 | **7** | 0 |
| **Control K1** | **Kontrol Ablasi: Query G dengan Indeks di-drop** (Tanpa `idx_pendaftaran_nik_all`) | `Seq Scan` | *None (Index Dropped for Control)* | **19,164** | 20,501 | 11,091 | 38,069 | 6,189 | 3.850 | 0 |

---

## 6. Pembuktian Kausalitas Indeks melalui Kontrol K1 (Item 4)

Eksperimen kontrol negatif (*ablation study*) dijalankan secara khusus pada database benchmark `tte_benchmark`:
1. Indeks `idx_pendaftaran_nik_all` di-drop sementara: `DROP INDEX IF EXISTS idx_pendaftaran_nik_all;`.
2. Kueri G (`SELECT * FROM pendaftaran_tte WHERE nik = ? LIMIT 15`) dieksekusi pada seluruh 30 target acak.
3. **Hasil:**
   - Rencana kueri langsung berubah dari `Index Scan` menjadi **`Seq Scan`** ([phase1_control_k1_no_index_explain.json](file:///e:/sismon-tte-V6/bench/results/phase1_control_k1_no_index_explain.json)).
   - Beban buffer melonjak dari **4 blok** menjadi **3.850 blok** (seluruh tabel dipindai).
   - Latensi median meningkat dari **0,169 ms** menjadi **19,164 ms** (penurunan performa sebesar **113,4× lipat**).
4. Indeks `idx_pendaftaran_nik_all` dipulihkan kembali: `CREATE INDEX ...` diikuti `VACUUM ANALYZE`.
5. **Kesimpulan Kausal:** Ini membuktikan bahwa kinerja sub-milidetik pada jalur terpisah murni disebabkan oleh keberadaan indeks B-Tree pada kolom ciphertext, bukan karena efek caching memori.

---

## 7. Pemisahan Statistik dari Plan Teks Ilustrasi (Item 5)

Berkas [phase1_explain_plans.txt](file:///e:/sismon-tte-V6/bench/results/phase1_explain_plans.txt) ditandai dengan label penegasan:
- **`[ILUSTRASI QUERY PLAN - DITANGKAP DARI RUN TUNGGAL UNTUK VISUALISASI STRUKTUR POHON SINTAKS]`**
- **`[DISCLAIMER: Metrik resmi tolok ukur (median, mean, min, max, SD) dihitung dari evaluasi 30 target acak pada phase1_summary.json]`**

Ini memastikan bahwa pembaca atau reviewer akademis tidak menyamakan rencana kueri teks tunggal dengan distribusi statistik 30 sampel.

---

## 8. Guard Integritas Lingkungan Database (Item 7)

1. **Guard pada Test Suite (`SearchIndexUsageTest.php`):**
   ```php
   if (DB::getDatabaseName() !== 'tte_testing') {
       $this->markTestSkipped('Pengujian ini HANYA diizinkan berjalan pada database tte_testing, terdeteksi: ' . DB::getDatabaseName());
   }
   ```
2. **Guard pada Benchmark Command (`RunPhase1ExplainBenchmark.php`):**
   ```php
   if ($db->getDatabaseName() !== 'tte_benchmark') {
       $this->error("ABORT: Perintah ini HANYA boleh dijalankan pada database tte_benchmark di container tte_postgres_bench!");
       return 1;
   }
   ```
3. **Paksaan pada `phpunit.xml`:**
   ```xml
   <env name="DB_DATABASE" value="tte_testing" force="true"/>
   ```

---

## 9. Status Migrasi Indeks Universal Seluruh Status (Item 8)

Berkas migrasi [2026_11_15_000001_add_all_status_lookup_indexes_to_pendaftaran_tte.php](file:///e:/sismon-tte-V6/database/migrations/2026_11_15_000001_add_all_status_lookup_indexes_to_pendaftaran_tte.php) telah diperbarui:
1. Ditandai **`[TODO-PENULIS]`** untuk menunggu keputusan final penulis mengenai penggabungan permanen ke skema produksi.
2. Dikonfigurasi dengan `public bool $withinTransaction = false;` dan pernyataan `CREATE INDEX CONCURRENTLY IF NOT EXISTS` agar aman dieksekusi pada lingkungan produksi dengan volume data besar tanpa mengunci tabel.

---

## 10. Daftar Hal yang Belum Terverifikasi (TODO-PENULIS)

Sesuai aturan kerja ilmiah, berikut adalah daftar hal yang **belum terverifikasi** atau **menunggu keputusan penulis**:

1. **[TODO-PENULIS: Penerapan Indeks Universal di Database Produksi]:**
   - Migrasi `idx_pendaftaran_nik_all` dan `idx_pendaftaran_nip_all` saat ini baru diaplikasikan pada container pengujian (`tte_testing`) dan benchmark (`tte_benchmark`).
   - Eksekusi pada database produksi (`tte_monitoring`) menunggu persetujuan resmi dan harus dijalankan secara `CONCURRENTLY` saat jam operasional rendah.
2. **[TODO-PENULIS: Evaluasi Beban Tulis / Overhead Penyimpanan Indeks]:**
   - Penambahan dua indeks baru (`idx_pendaftaran_nik_all` dan `idx_pendaftaran_nip_all`) menambah jejak disk fisik sebesar ~4,8 MB per 100.000 rekaman. Biaya penalti *write amplification* saat operasi `INSERT`/`UPDATE` baru akan diukur pada **Fase 3**.
3. **[TODO-PENULIS: Analisis Kebocoran Pola Frekuensi Deterministik]:**
   - Karena enkripsi deterministik menghasilkan ciphertext identik untuk plaintext yang sama, distribusi frekuensi NIK/NIP secara teoritis bocor ke perencana basis data. Mitigasi kriptografi tingkat lanjut (seperti skema terautentikasi HMAC/KDF atau blind index bergaram) akan diverifikasi pada **Fase 2**.
4. **[TODO-PENULIS: Penyelarasan Naskah Ilmiah JAIC]:**
   - Seluruh angka baru hasil Fase 1c (Tabel Query A–H, 30 sampel, Kontrol K1) belum dimasukkan ke draf naskah artikel ilmiah `ARTIKEL_ILMIAH_JAIC_SINTA3.md` sesuai batasan ketat fase ini.
