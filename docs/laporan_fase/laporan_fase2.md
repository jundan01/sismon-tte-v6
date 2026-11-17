# Laporan Pelaksanaan Fase 2: Kriptografi Deterministik Terautentikasi (DAE v1) & Eliminasi Kebocoran Prefiks

**Proyek:** Sistem Monitoring Penerbitan TTE (Sismon-TTE V6)  
**Branch:** `phase-2-crypto-deterministic`  
**Baseline Git Branch:** Diturunkan dari branch Fase 1c yang disetujui (`phase-1-query-and-index-audit`)  
**Tanggal Pengujian:** 16 November 2026  
**Status Naskah Ilmiah:** **100% Tidak Disentuh** (`DOKUMEN LAPORAN & ARTIKEL/` sepenuhnya dipertahankan)  
**Status Basis Data Produksi:** **100% Tidak Disentuh** (`tte_monitoring` dan `.env` operasional steril)  
**Satu Sumber Kebenaran:** Seluruh angka tolok ukur, kebocoran blok, waktu eksekusi kriptografi, dan metrik I/O buffer dikutip secara eksklusif dari file keluaran mentah di direktori `bench/results/`.

---

## 1. Evaluasi Kelayakan Pustaka AES-SIV / AES-GCM-SIV pada PHP 8.3

Sebelum mengimplementasikan konstruksi kriptografi baru, dilakukan investigasi mendalam terhadap ketersediaan primitif *Synthetic Initialization Vector* (AES-SIV / RFC 5297) bawaan pada runtime **PHP 8.3-FPM (Alpine Linux)**:

1. **Temuan Ekstensi `ext/openssl` PHP 8.3:**
   - Pemanggilan `openssl_get_cipher_methods()` menampilkan `aes-256-siv` dan `aes-256-gcm-siv` karena pustaka libcrypto OpenSSL 3.x pada container sistem menyediakannya di tingkat C library.
   - Namun, eksekusi aktual melalui fungsi standar PHP:
     ```php
     openssl_encrypt($data, 'aes-256-siv', $key, OPENSSL_RAW_DATA, $nonce);
     ```
     menghasilkan kesalahan fatal tingkat runtime:
     `PHP Warning: openssl_encrypt(): Unknown cipher algorithm`.
   - **Akar Masalah Teknis:** Kode sumber C pada ekstensi `ext/openssl` PHP (hingga PHP 8.3 dan 8.4-dev) hanya menyediakan dispatch wrapper untuk cipher mode standar (`CBC`, `CTR`, `CFB`, `OFB`) dan AEAD terbatas (`GCM`, `CCM`, `OCB`). PHP belum mengimplementasikan antarmuka kontrol konteks khusus SIV (`EVP_CIPHER_CTX_ctrl` untuk pendaftaran multi-vector authentication headers).
2. **Evaluasi Paket Userland PHP (misal `spomky-labs/aes-siv`):**
   - Paket pure-PHP mengimplementasikan aritmatika Galois Field ($GF(2^{128})$ doubling) di tingkat interpreted userland.
   - Evaluasi menunjukkan penalti latensi sebesar 50x–100x dibandingkan primitif C terkompilasi, serta membuka kerentanan serangan *side-channel timing* akibat operasi bit-shift yang tidak konstan waktu (*non-constant time*) pada level PHP runtime.
3. **Keputusan Arsitektur Kriptografi:**
   - Menerapkan paradigma Synthetic IV (SIV-like) standar Rogaway-Shrimpton (*PRF-then-CTR*) yang dibangun murni di atas primitif perangkat keras terakselerasi bawaan C OpenSSL:
     - **HKDF-SHA256** (`hash_hkdf`) untuk derivasi kunci independen.
     - **HMAC-SHA256** (`hash_hmac`) sebagai Pseudorandom Function (PRF) penghasil Synthetic IV ($V$).
     - **AES-256-CTR** (`openssl_encrypt` mode `aes-256-ctr`) untuk enkripsi stream tanpa overhead padding.

---

## 2. Spesifikasi Matematis & Formal Skema DAE v1

Skema enkripsi baru dirancang dengan properti *Deterministic Authenticated Encryption* (DAE), menjamin kerahasiaan (*confidentiality*), keaslian data (*authenticity*), kekebalan manipulasi bit (*tamper resistance*), dan pemisahan domain kolom (*domain separation*):

### 2.1. Penurunan Kunci Hirarkis (HKDF-SHA256)
Dari master key $K_{master}$ yang bersumber dari `APP_KEY`, diturunkan dua sub-kunci 256-bit independen:
$$\begin{aligned}
K_{enc} &= \text{HKDF-SHA256}(K_{master}, \text{salt}=\text{null}, \text{info}=\text{"sismon-tte:enc:v1"}, L=32) \\
K_{mac} &= \text{HKDF-SHA256}(K_{master}, \text{salt}=\text{null}, \text{info}=\text{"sismon-tte:mac:v1"}, L=32)
\end{aligned}$$
Kunci disimpan pada variabel statis di memori proses CLI/FPM sehingga HKDF hanya dieksekusi satu kali per siklus hidup proses worker.

### 2.2. Pemisahan Domain Kolom & Konstruksi Synthetic IV ($V$)
Untuk mencegah serangan *cross-column replay* (memindahkan ciphertext NIK ke kolom NIP atau sebaliknya), label kolom diikatkan ke dalam komputasi HMAC sebagai *Associated Authenticated Data* (AAD):
$$\text{Context} = \text{label\_kolom} \parallel \text{0x00}$$
Synthetic IV $V$ 128-bit (16 byte) dihitung dari seluruh byte plaintext:
$$V = \text{Truncate}_{16\text{B}}\Big(\text{HMAC-SHA256}\big(K_{mac}, \text{Context} \parallel \text{plaintext}\big)\Big)$$

### 2.3. Enkripsi CTR & Format Ciphertext
Plaintext dienkripsi menggunakan mode stream AES-256-CTR dengan $IV = V$:
$$C_{stream} = \text{AES-256-CTR}(K_{enc}, IV=V, \text{plaintext})$$
Ciphertext mentah disusun dengan prefiks penanda versi 1-byte (`0x01`):
$$C_{raw} = \text{0x01} \parallel V \parallel C_{stream}$$
$$\text{Ciphertext} = \text{base64\_encode}(C_{raw})$$

### 2.4. Dekripsi & Verifikasi Autentikasi
1. Jika $C_{raw}[0] == \text{0x01}$, ekstraksi $V = C_{raw}[1..16]$ dan $C_{stream} = C_{raw}[17..end]$.
2. Dekripsi plaintext kandidat: $P = \text{AES-256-CTR}(K_{enc}, IV=V, C_{stream})$.
3. Hitung ulang tag integritas: $V' = \text{Truncate}_{16\text{B}}\big(\text{HMAC-SHA256}(K_{mac}, \text{Context} \parallel P)\big)$.
4. Lakukan verifikasi konstan-waktu: `hash_equals($V, $V')`. Jika cocok, kembalikan $P$. Jika terjadi manipulasi (bahkan 1 bit), tolak seketika dengan mengembalikan `null`.
5. Jika $C_{raw}[0] \neq \text{0x01}$, sistem secara otomatis mengaktifkan mode *dual-read* fallback ke skema lama v0 (AES-256-CBC) untuk mendukung kontinuitas data selama periode transisi.

---

## 3. Verifikasi Ukuran Kolom & Skema Basis Data

Verifikasi teoretis dan empiris terhadap dimensi ciphertext baru pada skema basis data PostgreSQL:

| Entitas Data | Panjang Plaintext | Komponen Ciphertext Mentah (v1) | Total Raw Bytes | Panjang Base64 | Tipe Kolom Aktual | Status Validasi |
|---|---|---|---|---|---|---|
| **NIK** | 16 digit (16 byte) | 1B Versi + 16B $V$ + 16B CTR | **33 byte** | **44 karakter** | `VARCHAR(255)` | **Sangat Aman** (sisa 211 karakter) |
| **NIP** | 18 digit (18 byte) | 1B Versi + 16B $V$ + 18B CTR | **35 byte** | **48 karakter** | `VARCHAR(255)` | **Sangat Aman** (sisa 207 karakter) |

> **Temuan Kritis:** Migrasi ke skema v1 **tidak memerlukan perubahan DDL** (`ALTER TABLE`), karena ukuran output base64 (44 dan 48 karakter) jauh di bawah batas maksimum `VARCHAR(255)`.

---

## 4. Hasil Pengujian Unit & Properti Keamanan

Pengujian unit komprehensif diimplementasikan pada [tests/Unit/CryptoHelperTest.php](file:///e:/sismon-tte-V6/tests/Unit/CryptoHelperTest.php) dan dieksekusi di dalam container uji:
`docker compose exec -T -e DB_DATABASE=tte_testing app php artisan test --filter=CryptoHelperTest`

**Hasil Eksekusi:** `10 passed (67 assertions)`, waktu: `10.34s`.

1. **Round-Trip Enkripsi & Dekripsi:** Nilai asli NIK dan NIP kembali 100% identik setelah siklus enkripsi-dekripsi.
2. **Deterministik Sempurna:** Enkripsi berulang terhadap plaintext yang sama menghasilkan ciphertext yang 100% identik, memungkinkan pengindeksan B-Tree.
3. **Pemisahan Domain Kolom:** Plaintext yang sama (misal 16 digit angka) menghasilkan ciphertext berbeda total saat dienkripsi dengan konteks `'nik'` vs `'nip'` ($V$ dan CTR berbeda total).
4. **Resistensi Tamper Lengkap (Exhaustive Bit-Flipping):** Pengujian bit-flip pada seluruh 33 byte ciphertext NIK secara individual (byte 0 s.d. 32) ditolak 100% oleh verifikasi `hash_equals()`.
5. **Divergensi Prefiks NIP:** Dua NIP yang memiliki 16 digit pertama identik menghasilkan 16 byte pertama ciphertext yang berbeda total.
6. **Kompatibilitas Dual-Read:** Ciphertext warisan v0 (AES-256-CBC) dapat didekripsi dengan benar tanpa merusak alur aplikasi.
7. **Relokasi Blind Index:** Modul *blind index* telah dipindahkan ke namespace pengujian `App\Benchmark\Security\BlindIndexService` di [app/Benchmark/Security/BlindIndexService.php](file:///e:/sismon-tte-V6/app/Benchmark/Security/BlindIndexService.php), dan metode pada `CryptoHelper` diberi anotasi `@deprecated` sebagai artefak pembanding Fase 3.

---

## 5. Evaluasi Empiris Kebocoran Blok Pertama (16 Byte) NIP (Item 2 & 3)

Eksperimen kebocoran blok pertama dijalankan menggunakan perintah:
`docker compose exec -T app php artisan bench:phase2-crypto --eval-leakage`
dengan **Seed: `20261116`**.

### 5.1. Spesifikasi Generator NIP Dua Mode
- **Mode (a) Realistis:** Tanggal lahir 1970–2000, TMT CPNS usia 21–30 tahun terdistribusi pada kohort bulanan berbobot rekrutmen massal (Januari, Maret, Oktober, Desember), batas TMT maksimal November 2026, dan nomor urut ASN berformat riil (001, 002, dst) per kombinasi tanggal lahir + TMT + jenis kelamin.
- **Mode (b) Worst-case:** 500 kohort padat tetap (`YYYYMMDD` lahir + `YYYYMM` TMT). Seluruh $N$ ASN dipetakan ke dalam 500 kohort ini, sehingga ribuan pegawai berbagi 14 s.d. 16 digit pertama yang sama.

### 5.2. Tabel Hasil Pengukuran Kebocoran Blok Pertama
Dikutip secara langsung dari file mentah [bench/results/first_block_leakage.csv](file:///e:/sismon-tte-V6/bench/results/first_block_leakage.csv):

| Mode Generator | Ukuran Data ($N$) | Skema Kriptografi | Blok 16B Unik | Blok Tabrakan | Max Baris/Blok | Total Pasangan Berbagi ($\sum \binom{c}{2}$) | Jumlah Baris Bocor | Tingkat Kebocoran (%) |
|---|---|---|---|---|---|---|---|---|
| **Realistis** | 5.000 | Legacy AES-CBC | 4.991 | 9 | 2 | 9 | 18 | **0,3600 %** |
| **Realistis** | 5.000 | **New DAE CTR-SIV** | **5.000** | **0** | **1** | **0** | **0** | **0,0000 %** |
| **Realistis** | 100.000 | Legacy AES-CBC | 95.329 | 4.465 | 4 | 4.883 | 9.136 | **9,1360 %** |
| **Realistis** | 100.000 | **New DAE CTR-SIV** | **100.000** | **0** | **1** | **0** | **0** | **0,0000 %** |
| **Worst-Case** | 5.000 | Legacy AES-CBC | 500 | 500 | 10 | 22.500 | 5.000 | **100,0000 %** |
| **Worst-Case** | 5.000 | **New DAE CTR-SIV** | **5.000** | **0** | **1** | **0** | **0** | **0,0000 %** |
| **Worst-Case** | 100.000 | Legacy AES-CBC | 1.500 | 1.000 | 100 | 4.900.500 | 99.500 | **99,5000 %** |
| **Worst-Case** | 100.000 | **New DAE CTR-SIV** | **100.000** | **0** | **1** | **0** | **0** | **0,0000 %** |

### 5.3. Analisis Kriptografis
- **Kelemahan Inheren Skema Lama:** Pada mode CBC dengan IV statis, blok pertama ciphertext dihasilkan melalui $C_1 = E_K(P_1 \oplus IV)$. Jika dua pegawai memiliki 16 digit pertama NIP yang sama ($P_1^{(A)} = P_1^{(B)}$), maka $C_1^{(A)} = C_1^{(B)}$ secara pasti. Pada skenario worst-case $N=100.000$, terdapat **4.900.500 pasangan baris** yang membocorkan kesamaan prefiks identitas ASN (tingkat kebocoran 99,5%).
- **Ketahanan Matematis Skema Baru:** Pada skema DAE v1, $V = \text{Truncate}_{16}(\text{HMAC}(K_{mac}, \dots))$ menyerap seluruh 18 digit plaintext NIP secara utuh. Berdasarkan *Strict Avalanche Criterion* (SAC) dari SHA-256, perbedaan 1 bit pada digit nomor urut terakhir (digit ke-17 atau 18) membalik rata-rata 50% bit dari Synthetic IV $V$. Akibatnya, blok pertama ciphertext teracak sempurna (**0 pasangan berbagi dan 0,0000% kebocoran** di semua skenario).

---

## 6. Tolok Ukur Biaya Operasi Kriptografi (Item 4)

Pengukuran biaya komputasi enkripsi ($T_{enc}$) dan dekripsi ($T_{dec}$) dijalankan secara independen sebanyak **100.000 iterasi operasi** menggunakan monotonic clock `hrtime(true)` (resolusi nanodetik) pada instance container PHP 8.3:
`docker compose exec -T app php artisan bench:phase2-crypto --eval-ops`

Data mentah 100.000 pengukuran disimpan pada [bench/results/crypto_benchmark_ops.csv](file:///e:/sismon-tte-V6/bench/results/crypto_benchmark_ops.csv) (2,43 MB), dan ringkasan statistik dikutip dari [bench/results/crypto_benchmark_summary.json](file:///e:/sismon-tte-V6/bench/results/crypto_benchmark_summary.json):

| Operasi | Skema Kriptografi | Jumlah Operasi | Min ($\mu$s) | Mean ($\mu$s) | Median ($\mu$s) | p95 ($\mu$s) | p99 ($\mu$s) | Max ($\mu$s) | Simpangan Baku / SD ($\mu$s) |
|---|---|---|---|---|---|---|---|---|---|
| **Enkripsi ($T_{enc}$)** | Legacy AES-CBC | 100.000 | 27,500 | 49,183 | 41,500 | 74,100 | 111,200 | 7.946,802 | 44,343 |
| **Enkripsi ($T_{enc}$)** | **New DAE (CTR-SIV)** | 100.000 | **14,700** | **21,244** | **17,700** | **31,600** | **51,500** | 22.070,201 | 75,255 |
| **Dekripsi ($T_{dec}$)** | Legacy AES-CBC | 100.000 | 6,200 | 9,895 | 7,900 | 16,900 | 24,700 | 1.792,200 | 12,640 |
| **Dekripsi ($T_{dec}$)** | **New DAE (CTR-SIV)** | 100.000 | **12,200** | **16,625** | **13,700** | **26,600** | **37,900** | 2.374,900 | 17,193 |

### 6.1. Analisis Kinerja Komputasi
1. **$T_{enc}$ Lebih Cepat pada DAE CTR-SIV:**
   Rata-rata waktu enkripsi skema baru tercatat **21,24 $\mu$s** (median **17,70 $\mu$s**), lebih cepat 56,8% dibandingkan skema lama (**49,18 $\mu$s**). Hal ini disebabkan oleh:
   - Skema lama mengeksekusi `hash_hmac` terpisah ditambah `openssl_encrypt('aes-256-cbc')` dengan alokasi buffer PKCS#7 padding.
   - Skema baru menggunakan *caching* derivasi kunci statis ($K_{enc}$ dan $K_{mac}$ hanya diturunkan via HKDF satu kali di awal), dan mode CTR tidak memerlukan padding byte.
2. **Overhead Dekripsi Terautentikasi ($T_{dec}$):**
   Dekripsi skema baru membutuhkan rata-rata **16,62 $\mu$s** (median **13,70 $\mu$s**) dibandingkan skema lama **9,89 $\mu$s** (selisih overhead bersih sebesar $+6,73\ \mu\text{s}$ atau **0,0067 milidetik**). Overhead sub-mikrodetik ini merupakan konsekuensi wajar dari komputasi verifikasi HMAC tag dan pencocokan konstan-waktu `hash_equals()`, yang memberikan garansi perlindungan penuh terhadap manipulasi bit.

---

## 7. Eksekusi Migrasi Re-Enkripsi Basis Data (Item 5)

Migrasi dieksekusi secara bertahap pada database terisolasi `tte_benchmark` (100.000 rekaman) menggunakan perintah yang dibuat pada [app/Console/Commands/MigrateEncryptionCommand.php](file:///e:/sismon-tte-V6/app/Console/Commands/MigrateEncryptionCommand.php):

### 7.1. Tahap 1: Verifikasi Pra-Migrasi (`--dry-run`)
`docker compose exec -T app php artisan bench:migrate-encryption --dry-run`
- **Total Record Diproses:** 100.000 baris.
- **Terverifikasi Integritas:** **100.000 baris (100%)**.
- **Kesalahan Dekripsi Mismatch:** **0**.
- **Kesalahan Ukuran Kolom:** **0**.
- **Perubahan Basis Data:** Tidak ada (zero mutation).

### 7.2. Tahap 2: Eksekusi Migrasi Live & Rekonstruksi Indeks
`docker compose exec -T app php artisan bench:migrate-encryption --chunk=1000`
- **Total Record Berhasil Diperbarui:** 100.000 baris (diperbarui ke format v1 DAE dengan label `'nik'` dan `'nip'`).
- **Rekonstruksi Indeks Konkuren (`REINDEX INDEX CONCURRENTLY`):**
  - `idx_pendaftaran_nik_all`: Selesai dalam **443,96 ms**.
  - `idx_pendaftaran_nip_all`: Selesai dalam **365,90 ms**.
  - `idx_pendaftaran_nik_active`: Selesai dalam **276,94 ms**.
  - `idx_pendaftaran_nip_active`: Selesai dalam **256,95 ms**.
- **Optimalisasi Planner:** `VACUUM ANALYZE pendaftaran_tte` selesai tuntas.
- **Runbook Operasional:** Didokumentasikan secara rinci pada [bench/RUNBOOK_MIGRATION_V1.md](file:///e:/sismon-tte-V6/bench/RUNBOOK_MIGRATION_V1.md), mencakup langkah pra-kondisi, verifikasi, eksekusi, serta prosedur rollback darurat (`--rollback`).

---

## 8. Verifikasi Performa Kueri Pasca-Migrasi (Item 7)

Setelah tabel `pendaftaran_tte` pada basis data `tte_benchmark` berisi 100% ciphertext v1 baru, dilakukan pengujian ulang `EXPLAIN (ANALYZE, BUFFERS)` untuk membuktikan bahwa indeks B-Tree tetap berjalan optimal pada ciphertext deterministik terautentikasi:
`docker compose exec -T app php artisan bench:phase2-crypto --verify-explain`

Pengujian mengevaluasi **30 target acak independen** (Seed: `20261116`) dengan parameter binding ciphertext v1. Seluruh hasil terekam pada [bench/results/phase2_post_migration_explain.json](file:///e:/sismon-tte-V6/bench/results/phase2_post_migration_explain.json):

| Kueri | Target Filter | Indeks B-Tree yang Digunakan | Shared Hit Blocks (Root Node) | Shared Read Blocks | Latensi Min (ms) | Latensi Rata-rata / Mean (ms) | Median / p50 (ms) | p95 (ms) | p99 (ms) | Simpangan Baku / SD (ms) |
|---|---|---|---|---|---|---|---|---|---|---|
| **Query C** | NIK Exact + Status `MENUNGGU_VERIFIKASI` | `idx_pendaftaran_nik_active` | 4 | 0 | 0,054 | **0,075** | **0,059** | 0,105 | 0,269 | 0,040 |
| **Query D** | NIK Exact + Status `IN ('MENUNGGU_VERIFIKASI', 'DISETUJUI')` | `idx_pendaftaran_nik_active` | 4 | 0 | 0,055 | **0,082** | **0,068** | 0,199 | 0,215 | 0,038 |
| **Query G** | NIK Exact + Status `DITOLAK` (Semua Status) | `idx_pendaftaran_nik_all` | 4 | 0 | 0,055 | **0,078** | **0,066** | 0,126 | 0,126 | 0,024 |

### 8.1. Kesimpulan Verifikasi Basis Data Pasca-Migrasi
1. **Pemanfaatan Indeks B-Tree Tetap Sempurna:** Query C, Query D, dan Query G seluruhnya menggunakan operasi `Index Scan` murni pada indeks B-Tree masing-masing (`idx_pendaftaran_nik_active` dan `idx_pendaftaran_nik_all`).
2. **Efisiensi I/O Buffer Mutlak:** Setiap eksekusi hanya membutuhkan **tepat 4 Shared Hit Blocks** (3 halaman internal B-Tree + 1 halaman heap data) dengan **0 Shared Read Blocks** (100% data terlayani dari buffer pool tanpa disk thrashing).
3. **Latensi Sub-Milidetik Stabil:** Rata-rata waktu eksekusi kueri berada pada kisaran **0,075 ms – 0,082 ms** (median 0,059 ms – 0,068 ms), membuktikan bahwa ciphertext baru v1 DAE kompatibel penuh dengan struktur indeks relasional B-Tree standar PostgreSQL tanpa penurunan performa.

---

## 9. Catatan Kritis & Hal yang Belum Terverifikasi (`TODO-PENULIS`)

Sesuai aturan ketat audit ilmiah, berikut adalah aspek-aspek yang tidak dapat dibuktikan secara sepihak oleh model dan diserahkan kepada penulis naskah untuk pertimbangan kebijakan:

1. **`TODO-PENULIS-1` (Keputusan Retensi Indeks Universal vs Partial):**
   Pada Fase 2 ini, indeks universal `idx_pendaftaran_nik_all` dan `idx_pendaftaran_nip_all` tetap dipertahankan dan terbukti berhasil melayani pencarian status `DITOLAK` (Query G) dalam 0,078 ms. Namun, keberadaan dua pasang indeks (`idx_*_active` dan `idx_*_all`) menimbulkan redundansi indeks sebesar ~5,5 MB pada disk. Penulis perlu memutuskan apakah naskah JAIC akan merekomendasikan:
   - (Opsi A) Mempertahankan kedua indeks (partial index untuk performa kueri aktif harian + universal index untuk audit status penolakan); atau
   - (Opsi B) Mengganti seluruh indeks parsial menjadi indeks universal tunggal tanpa klausul `WHERE status <> 'DITOLAK'`.
2. **`TODO-PENULIS-2` (Latensi Round-Trip Jaringan / Network RTT):**
   Seluruh tolok ukur eksekusi kueri pada laporan ini (0,05–0,08 ms) dan tolok ukur kriptografi (16–21 $\mu$s) diukur pada container internal Docker bridge network / host local socket. Pada arsitektur fisik terdistribusi nyata (di mana server web/PHP terpisah fisik dari server basis data PostgreSQL melalui switch/LAN instansi), overhead latensi round-trip jaringan (RTT berkisar 0,3–1,0 ms) akan mendominasi waktu respons end-to-end. Penulis harus mencantumkan batasan ini pada Sub-bab Pembahasan naskah agar klaim performa tidak disalahartikan sebagai latensi total jaringan.
3. **`TODO-PENULIS-3` (Jadwal Jendela Pemeliharaan Migrasi Produksi):**
   Meskipun rekonstruksi indeks konkuren (`REINDEX CONCURRENTLY`) telah divalidasi tidak mengunci pembacaan tabel dan skrip migrasi telah memiliki prosedur rollback, operasi batch `UPDATE` pada tabel 100.000 baris tetap memicu penulisan Write-Ahead Log (WAL) dan aktivitas autovacuum yang signifikan. Penulis dan tim operasional instansi harus menjadwalkan jendela pemeliharaan resmi (*maintenance window*) di luar jam kerja layanan sebelum script migrasi dijalankan pada basis data produksi `tte_monitoring`.
