# RUNBOOK: Migrasi Re-Enkripsi Data Sensitif (v0 CBC -> v1 DAE CTR-SIV)

## 1. Ringkasan & Tujuan Operasional
Runbook ini mengatur prosedur teknis migrasi re-enkripsi data sensitif (NIK dan NIP) pada tabel `pendaftaran_tte` dari skema **v0 (AES-256-CBC dengan IV statis)** ke skema **v1 (Deterministic Authenticated Encryption / DAE CTR-SIV dengan HMAC-SHA256)**.

Tujuan utama migrasi:
1. **Mengeliminasi Kebocoran Blok Pertama AES-CBC**: Pada skema lama, dua pegawai dengan 16 digit pertama NIP yang sama menghasilkan 16 byte pertama ciphertext yang identik (kebocoran mencapai 99,5% pada mode worst-case 500 kohort). Skema v1 menggunakan $V = \text{Truncate}_{16}(\text{HMAC}(K_{mac}, \text{label} \parallel 0x00 \parallel \text{plaintext}))$ yang mengacak seluruh Synthetic IV secara seragam (0,0000% kebocoran).
2. **Autentikasi & Pemisahan Domain Kolom**: Menjamin integritas data terhadap bit-flipping/tampering dan mencegah cross-column replay attacks antara kolom `nik` dan `nip`.
3. **Zero-Downtime Indexing**: Rekonstruksi indeks B-Tree menggunakan `REINDEX INDEX CONCURRENTLY` tanpa memblokir pembacaan tabel secara operasional.
4. **Backward Compatibility (Dual-Read)**: Logika dekripsi mendukung identifikasi versi otomatis melalui byte penanda pertama (`0x01` untuk v1, fallback ke v0 jika gagal).

---

## 2. Analisis Dimensi Kolom & Skema Basis Data

| Kolom | Panjang Plaintext | Skema v1 (Raw Bytes) | Format Base64 | Tipe Kolom Aktual | Status Validasi |
|---|---|---|---|---|---|
| `nik` | 16 digit (16 byte) | 1B Versi + 16B V + 16B CTR = **33 byte** | **44 karakter** | `VARCHAR(255)` | **Aman** (Margin 211 karakter) |
| `nip` | 18 digit (18 byte) | 1B Versi + 16B V + 18B CTR = **35 byte** | **48 karakter** | `VARCHAR(255)` | **Aman** (Margin 207 karakter) |

> **Kesimpulan Arsitektur**: Tidak diperlukan perubahan DDL struktur kolom basis data (`ALTER TABLE`) karena panjang string ciphertext baru (44 & 48 karakter) sangat aman di dalam alokasi `VARCHAR(255)`.

---

## 3. Batasan Keamanan & Pre-requisite
1. **Pemisahan Database**: Script ini secara ketat dibatasi (`ABORT`) jika mendeteksi database produksi (`tte_monitoring`) tanpa otorisasi manual dan konfigurasi terisolasi.
2. **Kunci Master**: Pastikan nilai `APP_KEY` valid di environment. $K_{enc}$ dan $K_{mac}$ diturunkan secara deterministik menggunakan HKDF (`hash_hkdf`).
3. **Backup Data**: Lakukan pg_dump sebelum memulai migrasi live:
   ```bash
   pg_dump -U postgres -d tte_benchmark -t pendaftaran_tte > backup_pendaftaran_tte_pre_v1.sql
   ```

---

## 4. Prosedur Eksekusi Langkah-demi-Langkah

### Langkah 1: Simulasi Validasi 100% Baris (`--dry-run`)
Sebelum menulis perubahan apa pun ke basis data, jalankan validasi integritas round-trip:
```bash
php artisan bench:migrate-encryption --dry-run
```
**Kriteria Lolos**:
- Total record terverifikasi = 100% (contoh: 100.000 / 100.000 baris).
- Kesalahan Mismatch = 0.
- Kesalahan Ukuran Kolom = 0.
- Basis data tidak mengalami perubahan data sedikit pun.

### Langkah 2: Eksekusi Migrasi Live
Jalankan migrasi bertahap (chunked updates) dengan rekonstruksi indeks konkuren:
```bash
php artisan bench:migrate-encryption --chunk=1000
```
Proses ini akan:
1. Membaca record secara chunk (1.000 baris per iterasi).
2. Mendekripsi ciphertext lama (v0), memverifikasi dekripsi, dan mengenkripsi ke ciphertext v1 DAE dengan context label (`nik` dan `nip`).
3. Melakukan batch update ke tabel `pendaftaran_tte`.
4. Setelah seluruh chunk selesai, menjalankan:
   ```sql
   REINDEX INDEX CONCURRENTLY idx_pendaftaran_nik_all;
   REINDEX INDEX CONCURRENTLY idx_pendaftaran_nip_all;
   REINDEX INDEX CONCURRENTLY idx_pendaftaran_nik_active;
   REINDEX INDEX CONCURRENTLY idx_pendaftaran_nip_active;
   VACUUM ANALYZE pendaftaran_tte;
   ```

### Langkah 3: Verifikasi Pasca-Migrasi (Benchmark EXPLAIN)
Pastikan performa query pencarian exact-match tetap berada pada sub-milidetik (0,4–0,8 ms) menggunakan indeks B-Tree:
```bash
php artisan bench:phase2-crypto --verify-explain
```
Periksa file output di `bench/results/phase2_post_migration_explain.json` untuk memastikan `Index Scan` aktif pada Query C, D, dan G.

---

## 5. Prosedur Rollback Bencana (Disaster Recovery)

Jika ditemukan anomali fungsional pada aplikasi setelah migrasi:
1. Jalankan perintah rollback otomatis:
   ```bash
   php artisan bench:migrate-encryption --rollback --chunk=1000
   ```
2. Logika rollback akan:
   - Mendekripsi ciphertext v1 DAE (atau v0 jika ada data campur) menggunakan pembacaan aman.
   - Mengenksipsi ulang ke format v0 (AES-256-CBC dengan static IV).
   - Memperbarui kolom basis data dan menjalankan `REINDEX INDEX CONCURRENTLY` serta `VACUUM ANALYZE`.
3. Alternatif darurat jika instance terhenti:
   - Restore langsung dari dump sql:
     ```bash
     psql -U postgres -d tte_benchmark -f backup_pendaftaran_tte_pre_v1.sql
     ```
