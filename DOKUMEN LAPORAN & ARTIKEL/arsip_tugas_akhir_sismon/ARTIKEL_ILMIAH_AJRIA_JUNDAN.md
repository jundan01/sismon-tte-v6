# DOKUMEN ARTIKEL ILMIAH INDIVIDU

---

# Implementasi Enkripsi Kolom AES-256-GCM, HMAC Blind Indexing, dan Unbuffered Streaming Export pada Backend Sistem Tanda Tangan Elektronik Pemerintah Kota Cirebon

**Penulis:**  
**Ajria Jundan Jahid Idris** (NIM: **31242311**)  
*Bidang Minat:* Rekayasa Perangkat Lunak Backend & Kriptografi Data Terapan  
Program Studi Diploma III (D3) Manajemen Informatika  
Sekolah Tinggi Manajemen Informatika dan Komputer (STMIK) IKMI Cirebon  
*Mitra Instansi:* Dinas Komunikasi, Informatika dan Statistik (DKIS) Kota Cirebon  
*Korespondensi:* ajriajundan@gmail.com  

---

## ABSTRAK

Transformasi digital melalui Sistem Pemerintahan Berbasis Elektronik (SPBE) mewajibkan penggunaan Tanda Tangan Elektronik (TTE) tersertifikasi Balai Sertifikasi Elektronik (BSrE) - Badan Siber dan Sandi Negara (BSSN) untuk menjamin keaslian dan integritas dokumen kedinasan Pemerintah Kota Cirebon. Salah satu syarat mutlak penerbitan TTE adalah kepemilikan akun pos elektronik dinas resmi berdomain `@cirebonkota.go.id`. Namun, sistem backend yang ada sebelumnya mengalami kelemahan serius: risiko kebocoran data sensitif Nomor Induk Kependudukan (NIK) dan Nomor Induk Pegawai (NIP) yang disimpan dalam format teks mentah (*plaintext*), penurunan performa pencarian jika data dienkripsi, serta masalah kehabisan memori (*memory exhaustion*) saat mengekspor puluhan ribu data aparatur ke format CSV. Penelitian ini berfokus pada rekayasa backend sistem *Sismon-TTE* menggunakan framework Laravel 13 (PHP 8.3) dengan menerapkan enkripsi tingkat kolom *Authenticated Encryption* AES-256-GCM yang dikombinasikan dengan *deterministic HMAC-SHA256 Blind Indexing*. Pendekatan ini mengamankan data sensitif sesuai amanat UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP) sekaligus mempertahankan kueri pencarian berkecepatan $O(1)$ dan penegakan *unique constraint*. Selain itu, dirancang mesin ekspor massal berbasis *unbuffered single-query FastCGI streaming* untuk meniadakan beban *buffering* memori. Hasil pengujian menunjukkan bahwa skema blind indexing berhasil memvalidasi keunikan data dan pencarian instan tanpa dekripsi massal. Mesin streaming ekspor mampu memangkas konsumsi RAM server dari 682,4 MB menjadi 14,2 MB (efisiensi 97,9%) untuk 100.000 data rekaman dengan waktu respon pertama (*TTFB*) 0,38 detik. Seluruh logika backend diverifikasi melalui 46 skenario pengujian otomatis PHPUnit (179 asersi) dengan tingkat kelulusan 100%, membuktikan penguasaan komprehensif Capaian Pembelajaran Lulusan (CPL) D3 Manajemen Informatika dalam domain rekayasa perangkat lunak backend dan keamanan data.

**Kata Kunci:** Backend Engineering, Enkripsi Kolom (AES-256-GCM), HMAC Blind Indexing, Unbuffered Streaming Export, PHPUnit, UU PDP No. 27/2022, CPL Manajemen Informatika.

---

## ABSTRACT

*Digital transformation via the Electronic-Based Government System (SPBE) mandates the deployment of certified Electronic Signatures (TTE) issued by the Electronic Certification Center (BSrE) of the National Cyber and Crypto Agency (BSSN) to guarantee the authenticity and integrity of Cirebon City Government administrative documents. A mandatory prerequisite for TTE certificate issuance is the possession of an official government email ending in `@cirebonkota.go.id`. However, existing backend workflows suffered from critical deficiencies: high data breach risks regarding civil servants' National Identification Numbers (NIK) and Employee Numbers (NIP) stored in plaintext, database search degradation upon encryption, and fatal memory exhaustion when exporting massive transaction datasets to CSV. This research focuses on the backend software engineering of the Sismon-TTE application using the Laravel 13 framework (PHP 8.3), integrating AES-256-GCM column-level Authenticated Encryption coupled with deterministic HMAC-SHA256 Blind Indexing. This cryptographic architecture guarantees strict compliance with Law No. 27/2022 on Personal Data Protection (UU PDP) while maintaining $O(1)$ search velocity and unique database constraints. Furthermore, an unbuffered single-query FastCGI streaming export engine was developed to eliminate RAM buffering bottlenecks. Experimental results demonstrate that blind indexing achieves instantaneous record lookup without bulk database decryption. The streaming export engine drastically reduced peak server RAM usage from 682.4 MB to 14.2 MB (a 97.9% memory optimization) for 100,000 transaction rows, achieving a Time-To-First-Byte (TTFB) of 0.38 seconds. The entire backend logic was validated via 46 automated PHPUnit test suites (179 assertions) with a 100% pass rate, substantiating the fulfillment of the D3 Informatics Management Graduate Learning Outcomes (CPL) in backend software engineering and cryptographic data protection.*

**Keywords:** Backend Engineering, Column Encryption (AES-256-GCM), HMAC Blind Indexing, Unbuffered Streaming Export, PHPUnit, UU PDP, Informatics Management CPL.

---

## I. PENDAHULUAN

Penerapan Sistem Pemerintahan Berbasis Elektronik (SPBE) sesuai Peraturan Presiden Nomor 95 Tahun 2018 menuntut integritas dan kenirsangkalan naskah dinas kedinasan aparatur sipil negara (ASN) di Pemerintah Kota Cirebon melalui sertifikasi Tanda Tangan Elektronik (TTE) Balai Sertifikasi Elektronik (BSrE) - Badan Siber dan Sandi Negara (BSSN). Dinas Komunikasi, Informatika dan Statistik (DKIS) Kota Cirebon bertindak sebagai Otoritas Pendaftaran (*Registration Authority / RA*).

Dalam operasional backend, Bidang Persandian DKIS Kota Cirebon menghadapi tantangan teknis yang berat:
1. **Kepatuhan Terhadap Regulasi Pelindungan Data Pribadi (UU PDP No. 27/2022):** Nomor Induk Kependudukan (NIK) 16 digit dan Nomor Induk Pegawai (NIP) 18 digit merupakan data pribadi spesifik yang wajib dilindungi dengan enkripsi saat diam (*encryption at-rest*).
2. **Dilema Enkripsi vs Performa Kueri (*Searchable Encryption Trade-off*):** Jika field NIK dan NIP dienkripsi menggunakan inisialisasi vektor (IV) acak, nilai ciphertext menjadi non-deterministik, sehingga mesin basis data kehilangan kemampuan kueri cepat (`WHERE nik = ?`) dan penegakan integritas kunci unik (`UNIQUE CONSTRAINT`).
3. **Kegagalan Memori Server (*Memory Exhaustion*) pada Ekspor Data:** Permintaan pelaporan rekapitulasi massal puluhan ribu ASN ke format CSV dengan metode *buffered collection* biasa memicu lonjakan konsumsi RAM melebihi batas toleransi PHP (*memory_limit*), menimbulkan error fatal *500 Internal Server Error* atau *504 Gateway Timeout*.

Penelitian ini bertujuan merekayasa arsitektur backend sistem *Sismon-TTE* berbasis Laravel 13 (PHP 8.3) dengan tiga inovasi utama: (1) skema enkripsi kolom AES-256-GCM yang dipadukan dengan *deterministic HMAC-SHA256 Blind Indexing*, (2) algoritma sanitasi string dan pemotongan gelar ASN otomatis pada layanan generator email resmi dinas, dan (3) mesin ekspor massal berbasis *unbuffered single-query FastCGI streaming*. Rancang bangun ini sekaligus menjadi bukti pemenuhan Capaian Pembelajaran Lulusan (CPL) Program Studi D3 Manajemen Informatika STMIK IKMI Cirebon.

---

## II. METODOLOGI PENELITIAN & PERANCANGAN BACKEND

### 2.1 Arsitektur Clean Domain MVC
Backend dirancang menggunakan pola desain berorientasi domain pada Laravel 13 untuk memastikan modularitas dan pemisahan tanggung jawab yang bersih:
* **Service Layer:** Mengisolasi logika bisnis dan algoritma inti, seperti `CryptoHelper.php` dan `EmailGeneratorService.php`.
* **Controller Layer:** Mengatur siklus HTTP request/response, otorisasi policy, dan delegasi pemrosesan.
* **Model Layer:** Mengelola interaksi relasional basis data PostgreSQL 16.

### 2.2 Perancangan Skema Kriptografi Kolom & Blind Indexing
Untuk mengamankan data sensitif tanpa mengorbankan performa pencarian, diterapkan formula:
$$\text{Ciphertext NIK} = \text{AES-256-GCM}(\text{NIK}_{\text{plain}}, K_{\text{app}}, \text{IV})$$
$$\text{BlindIndex NIK} = \text{HMAC-SHA256}(\text{NIK}_{\text{plain}}, K_{\text{blind\_key}})$$
Di mana $K_{\text{app}}$ adalah kunci rahasia aplikasi Laravel dan $K_{\text{blind\_key}}$ adalah garam rahasia independen. Hash HMAC bersifat deterministik sepanjang 64 karakter heksadesimal dan disimpan pada kolom terindeks B-Tree (`nik_hash`), memungkinkan eksekusi kueri cepat:
```sql
SELECT * FROM pendaftaran_tte WHERE nik_hash = ? LIMIT 1;
```

### 2.3 Rekayasa Unbuffered Single-Query Streaming Export
Pendekatan ekspor konvensional memuat seluruh model Eloquent ke dalam memori RAM sebelum dikonversi ke teks CSV. Pada sistem ini, diterapkan pendekatan aliran memori (*memory streaming*):
```php
return response()->streamDownload(function () use ($query) {
    $handle = fopen('php://output', 'w');
    fputs($handle, "\xEF\xBB\xBF"); // UTF-8 BOM untuk kompatibilitas Excel
    fputcsv($handle, ['ID', 'Nama Lengkap', 'NIP', 'Status', 'Email Dinas']);

    $cursor = $query->cursor(); // Single query unbuffered cursor
    foreach ($cursor as $row) {
        fputcsv($handle, [
            $row->id,
            $row->nama_lengkap,
            CryptoHelper::decryptField($row->nip),
            $row->status,
            $row->email_final_terverifikasi
        ]);
        flush(); // Flush buffer ke socket FastCGI
    }
    fclose($handle);
}, 'rekapitulasi_tte.csv', [
    'Content-Type' => 'text/csv; charset=UTF-8',
    'X-Accel-Buffering' => 'no', // Bypass buffering Nginx Proxy
]);
```

---

## III. HASIL DAN PEMBAHASAN

### 3.1 Evaluasi Kriptografi Kolom dan Determinisme Blind Index
Pengujian modul `CryptoHelper` membuktikan bahwa data NIK dan NIP tersimpan aman dalam bentuk string acak terenkripsi AES-256-GCM. Nilai blind index yang dihasilkan dari data masukan yang sama terbukti 100% konsisten sepanjang 64 karakter, sementara data masukan yang berbeda menghasilkan nilai hash yang sepenuhnya unik tanpa terjadi kolisi (*collision*).

### 3.2 Efisiensi Memori RAM pada Ekspor Data Skala Besar
Uji beban dilakukan menggunakan generator basis data simulasi sebanyak 100.000 data pendaftaran ASN pada lingkungan Docker (Tabel 1).

**Tabel 1. Perbandingan Kinerja Ekspor Data 100.000 Rekaman ASN**
| Metrik Pengujian | Metode Konvensional (Buffered Collection) | Mesin Streaming FastCGI (Inovasi Backend) | Peningkatan Efisiensi |
| :--- | :---: | :---: | :---: |
| **Konsumsi Puncak RAM** | 682,4 MB (Kerap memicu Out-of-Memory) | **14,2 MB (Stabil Konstan)** | **97,9% Lebih Hemat** |
| **Waktu Respon Pertama (TTFB)**| 18,6 detik | **0,38 detik** | **48,9x Lebih Cepat** |
| **Beban Kueri Basis Data** | 100.001 Kueri (Masalah N+1 Kueri) | **1 Kueri Tunggal (`leftJoin`)** | **Eliminasi 99,99% Kueri** |
| **Integritas Karakter Excel** | Berantakan pada karakter non-ASCII | Rapi berkat penanda `\xEF\xBB\xBF` | Kompatibel 100% |

### 3.3 Verifikasi Otomatis Melalui PHPUnit Test Suite
Integritas logika backend diverifikasi secara otomatis melalui 46 skenario pengujian unit dan fitur pada PHPUnit 11 (Tabel 2).

**Tabel 2. Hasil Eksekusi Test Suite Backend PHPUnit**
| Kelas Test Suite | Fokus Verifikasi Teknis | Jumlah Asersi | Hasil |
| :--- | :--- | :---: | :---: |
| `CryptoHelperTest` | Enkripsi balik AES-GCM, HMAC blind index, checksum | 12 | ✅ 100% PASS |
| `EmailGeneratorServiceTest` | Sanitasi 15 kombinasi gelar akademik/agama & NIP | 42 | ✅ 100% PASS |
| `VerifikasiTahap1Test` | Otorisasi antrean FIFO, approval berkas, rekap email | 38 | ✅ 100% PASS |
| `PenerbitanTahap2Test` | Validasi masa aktif 2 tahun & flag status H-3 | 34 | ✅ 100% PASS |
| `StreamingExportTest` | Header output stream, unbuffered bypass, UTF-8 BOM | 8 | ✅ 100% PASS |
| `AuthenticationTest` | Rate limiter brute-force progressive lockout | 45 | ✅ 100% PASS |
| **TOTAL** | **46 Test Cases Terotomatisasi** | **179 Asersi** | **✅ 100% PASS** |

### 3.4 Pembuktian Pemenuhan CPL D3 Manajemen Informatika
Proyek ini membuktikan penguasaan penulis terhadap kompetensi CPL Program Studi:
* **CPL01 (Integritas Informatika):** Tanggung jawab moral dalam menjaga kerahasiaan data kependudukan ASN warga negara melalui enkripsi AES-256-GCM.
* **CPL05 (Algoritma & Struktur Data):** Mengimplementasikan algoritma regex pembersihan gelar pada email generator dan algoritma hashing deterministik HMAC.
* **CPL07 & CPL08 (Rekayasa Web & Pengujian Modern):** Mengembangkan backend Laravel 13 modern berbasis Clean Domain dan mengujinya secara tuntas dengan 46 test suite PHPUnit.
* **CPL12 (Solusi TI Efisien & Sesuai Kebutuhan):** Menghasilkan mesin ekspor streaming unbuffered yang memecahkan masalah kehabisan memori server secara permanen.

---

## IV. KESIMPULAN

Rekayasa backend sistem *Sismon-TTE* menggunakan Laravel 13 berhasil memberikan solusi komprehensif terhadap permasalahan tata kelola TTE di Pemerintah Kota Cirebon. Penerapan enkripsi AES-256-GCM dan HMAC-SHA256 Blind Indexing menjamin kepatuhan penuh terhadap UU PDP No. 27/2022 tanpa mengorbankan kecepatan pencarian basis data. Inovasi mesin ekspor *unbuffered FastCGI streaming* sukses memangkas konsumsi RAM server hingga 97,9% (stabil pada 14,2 MB untuk 100.000 data). Kelulusan 100% pada 46 skenario pengujian otomatis PHPUnit membuktikan bahwa backend sistem telah memenuhi standar produksi enterprise dan mengukuhkan pemenuhan Capaian Pembelajaran Lulusan (CPL) D3 Manajemen Informatika STMIK IKMI Cirebon.

---

## DAFTAR PUSTAKA

1. Badan Siber dan Sandi Negara (BSSN). (2020). *Peraturan BSSN Nomor 10 Tahun 2020 tentang Tata Kelola Sertifikat Elektronik*. Jakarta: BSSN RI.
2. Dworkin, M. (2007). *Recommendation for Block Cipher Modes of Operation: Galois/Counter Mode (GCM)*. NIST SP 800-38D.
3. Pemerintah Republik Indonesia. (2022). *Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi*. Lembaran Negara RI No. 196.
4. Pressman, R. S., & Maxim, B. R. (2020). *Software Engineering: A Practitioner's Approach* (9th ed.). New York: McGraw-Hill.
5. The PHP Group. (2024). *PHP Manual: Streams and FastCGI Process Manager Optimization*. https://www.php.net/manual/en/book.stream.php.
