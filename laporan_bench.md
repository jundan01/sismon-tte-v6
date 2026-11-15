# Laporan Pengujian Tolok Ukur (Benchmark Report) & Audit Kueri Basis Data
**Proyek:** Sistem Monitoring Penerbitan TTE (Sismon-TTE V6)  
**Lingkungan Eksekusi:** Docker Container `tte_app` (PHP 8.3 CLI) & `tte_postgres` (PostgreSQL 16.2 Alpine)  
**Database Pengujian:** `tte_benchmark` (Database Benchmark Terisolasi, data riil produksi `tte_monitoring` 100% tidak tersentuh)  
**Dataset:** 100.000 rekaman sintetis pada tabel `pendaftaran_tte` dengan seeder skpd riil dan distribusi status realistis (`MENUNGGU_VERIFIKASI` 60%, `DISETUJUI` 20%, `DITOLAK` 20%)  
**Tanggal Pengujian:** 15 November 2026  

---

## 1. Fase 1: Audit Penggunaan Indeks & Pemisahan Jalur Pencarian (Temuan K1)

### 1.1. Konfirmasi Temuan Audit K1
Pada naskah awal dan implementasi awal di `app/Http/Controllers/VerifikasiController.php` (serta `DashboardController.php`), kueri pencarian umum menyatukan pencarian teks non-indeks dan pencarian kolom terenkripsi ke dalam satu blok predikat majemuk `OR`:

```sql
-- Kueri Algoritma 4 Awal (Anti-Pattern Compound OR)
SELECT * FROM pendaftaran_tte
WHERE status = 'MENUNGGU_VERIFIKASI'
  AND (
    nama_lengkap ILIKE '%3274000000083335%'
    OR nomor_referensi ILIKE '%3274000000083335%'
    OR deskripsi ILIKE '%3274000000083335%'
    OR nik = 'vydaO8CDhLvIinm+8UMcx8nsqJA/jgxSU+jOdTIHTVA='
    OR nip = 'vydaO8CDhLvIinm+8UMcx8nsqJA/jgxSU+jOdTIHTVA='
  )
ORDER BY created_at ASC
LIMIT 15;
```

**Temuan Empiris:**
Meskipun PostgreSQL 16 memiliki indeks parsial unik `idx_pendaftaran_nik_active` pada `(nik) WHERE status != 'DITOLAK'`, penggabungan cabang wildcard `ILIKE '%...'` (yang tidak didukung indeks B-Tree standar) dengan klausa kesetaraan `OR nik = ?` memaksa perencana kueri PostgreSQL mengabaikan seluruh indeks B-Tree dan beralih ke **Parallel Sequential Scan** (`Parallel Seq Scan on pendaftaran_tte`).
Akibatnya:
- Seluruh 3.778 blok halaman data dipindai ke dalam memori (`Buffers: shared hit=3778`).
- Waktu eksekusi melonjak menjadi **200,236 ms – 342,608 ms**.
- Klaim naskah lama bahwa pencarian selalu berlangsung dalam kompleksitas $\mathcal{O}(\log N)$ terbantahkan pada kueri majemuk tersebut.

---

### 1.2. Solusi Rekayasa: Pemisahan Jalur Pencarian (Search Route Separation)
Kode pada `app/Http/Controllers/VerifikasiController.php` dan `app/Http/Controllers/DashboardController.php` direfaktor untuk memisahkan pencarian menjadi dua rute deterministik sebelum menyusun kueri SQL:

1. **Jalur 1: Pencarian Identitas Sensitif Terenkripsi (Exact-Match $\mathcal{O}(\log N)$)**
   - Mendeteksi apakah input pengguna berupa 16-digit angka murni (format standar NIK Indonesia) atau 18-digit angka murni (format standar NIP ASN).
   - Melakukan sanitasi karakter spasi/tanda hubung: `preg_replace('/\D/', '', $trimmed)`.
   - Mengenkripsi parameter menggunakan kunci deterministik (`CryptoHelper::encryptField()`).
   - Mengeksekusi kueri kesetaraan langsung:
     - NIK (16 digit): `$q->where('nik', $encryptedNik);`
     - NIP (18 digit): `$q->where('nip', $encryptedNip);`
   - Kueri ini memenuhi predikat indeks parsial `status != 'DITOLAK'` (baik secara langsung maupun melalui implikasi status aktif `status = 'MENUNGGU_VERIFIKASI'`), sehingga PostgreSQL mengeksekusi **Index Scan murni**.

2. **Jalur 2: Pencarian Teks / Deskriptif Non-Eksak (Wildcard Text Search)**
   - Jika input berupa teks nama, nomor referensi parsial, atau deskripsi (bukan 16/18 digit angka), kueri dialihkan secara eksklusif ke kolom teks tanpa menyertakan kolom identitas sensitif `nik`/`nip`.

---

### 1.3. Tabel Hasil Pengukuran Empiris `EXPLAIN (ANALYZE, BUFFERS)`

Pengukuran dilakukan secara langsung di dalam kontainer `tte_postgres` menggunakan tabel `pendaftaran_tte` berisi 100.000 rekaman sintetis setelah dilakukan `VACUUM ANALYZE`.

| ID Skenario | Deskripsi Kueri | Tipe Pemindaian (Plan Node) | Indeks yang Digunakan | Waktu Eksekusi (ms) | Waktu Perencanaan (ms) | Buffer Shared Hit (Blok) | Buffer Shared Read (Blok) |
|---|---|---|---|---|---|---|---|
| **Query A** | Algoritma 4 Lama (Compound `OR`: ILIKE nama/ref/deskripsi `OR` nik/nip, filter status) | `Parallel Seq Scan` | *Tidak Ada (Seq Scan)* | **200,236** | 0,798 | 3.778 | 0 |
| **Query B** | Algoritma 4 Lama Tanpa Filter Status (Compound `OR` murni) | `Seq Scan` | *Tidak Ada (Seq Scan)* | **539,946** | 0,580 | 3.704 | 0 |
| **Query C** | **Jalur Terpisah: Exact-Match NIK** (`status = 'MENUNGGU_VERIFIKASI'`) | `Index Scan` | **`idx_pendaftaran_nik_active`** | **0,183** | 0,144 | **4** | 0 |
| **Query D** | **Jalur Terpisah: Exact-Match NIK** (`status != 'DITOLAK'` / Indeks Parsial) | `Index Scan` | **`idx_pendaftaran_nik_active`** | **0,121** | 0,255 | **4** | 0 |
| **Query E** | **Jalur Terpisah: Exact-Match NIP** (`status = 'MENUNGGU_VERIFIKASI'`) | `Index Scan` | **`idx_pendaftaran_nip_active`** | **0,487** | 0,169 | **4** | 0 |
| **Query F** | Jalur Terpisah: Pencarian Teks (Hanya ILIKE nama/nomor referensi) | `Parallel Seq Scan` | *Tidak Ada (Seq Scan)* | **129,505** | 0,369 | 3.778 | 0 |

> **Analisis Efisiensi:**
> - Pemisahan jalur exact-match menurunkan waktu eksekusi dari **200,236 ms** (Query A) menjadi **0,183 ms** (Query C), menghasilkan percepatan terverifikasi sebesar **1.094× lipat**.
> - Beban I/O memori basis data berkurang secara drastis dari **3.778 blok halaman** menjadi hanya **4 blok halaman** (3 blok traversal pohon B-Tree + 1 blok data baris pada heap).

---

### 1.4. Jalur Berkas Mentah (Raw Output Artifacts)
Seluruh angka dan rencana kueri di atas diperoleh dari berkas luaran mentah yang disimpan di direktori repositori:

1. Rencana Kueri Format Teks Lengkap:
   - `bench/results/phase1_explain_plans.txt`
2. Ringkasan Metrik Format JSON:
   - `bench/results/phase1_summary.json`
3. Rencana Kueri JSON per Skenario:
   - `bench/results/phase1_query_a_combined_explain.json`
   - `bench/results/phase1_query_b_combined_nostatus_explain.json`
   - `bench/results/phase1_query_c_separated_nik_status_explain.json`
   - `bench/results/phase1_query_d_separated_nik_active_explain.json`
   - `bench/results/phase1_query_e_separated_nip_status_explain.json`
   - `bench/results/phase1_query_f_separated_text_explain.json`

---

### 1.5. Pengujian Regresi Otomatis (Automated Index Verification Tests)
Sebuah berkas pengujian fitur baru telah ditambahkan ke repositori:
`tests/Feature/SearchIndexUsageTest.php`

Pengujian dijalankan dengan perintah:
```bash
docker compose exec -T -e DB_DATABASE=tte_testing app php artisan test --filter=SearchIndexUsageTest
```

**Hasil Pengujian:**
```text
   PASS  Tests\Feature\SearchIndexUsageTest
  ✓ exact nik search uses partial btree index                           11.69s  
  ✓ exact nip search uses partial btree index                            0.69s  
  ✓ verifikasi controller routes 16 digits to nik exact search           1.86s  
  ✓ verifikasi controller routes 18 digits to nip exact search           0.53s  
  ✓ verifikasi controller routes text to wildcard search                 0.55s  

  Tests:    5 passed (15 assertions)
  Duration: 18.16s
```

Pengujian ini menginspeksi struktur keluaran `EXPLAIN (FORMAT JSON)` secara programmatic untuk memastikan bahwa setiap kueri pencarian NIK/NIP selalu memanfaatkan simpul `Index Scan` dengan indeks `idx_pendaftaran_nik_active` atau `idx_pendaftaran_nip_active`.
