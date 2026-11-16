# Pencarian Eksak Kolom Identitas Terenkripsi Deterministik Terautentikasi pada PostgreSQL

**Ajria Jundan Jahid Idris$^{1*}$, Bani Nurhakim$^{2}$**  
$^{1,2}$Program Studi Manajemen Informatika, STMIK IKMI Cirebon  
Jl. Perjuangan No. 10B, Karyamulya, Kec. Kesambi, Kota Cirebon, Jawa Barat 45131, Indonesia  
E-mail: $^{1}$ajriajundan123@gmail.com, $^{2}$baninurhakim@ikmi.ac.id  
$^{*}$Penulis Korespondensi

---

### Abstrak
Penyelenggaraan Sistem Pemerintahan Berbasis Elektronik (SPBE) mewajibkan kepatuhan terhadap Undang-Undang Pelindungan Data Pribadi (UU PDP) No. 27 Tahun 2022, khususnya pengamanan kolom identitas kependudukan seperti Nomor Induk Kependudukan (NIK) dan Nomor Induk Pegawai (NIP). Namun, penerapan enkripsi probabilistik standar seperti AES-GCM merusak keterurutan indeks pohon B-Tree relasional, sehingga memaksa pembacaan pemindaian berurutan (*Sequential Scan*) di memori aplikasi dengan kompleksitas waktu linear $O(N)$. Sebaliknya, enkripsi deterministik konvensional berbasis AES-CBC dengan vektor inisialisasi (*Initialization Vector*/IV) statis rentan terhadap kebocoran kesamaan prefiks (*prefix leakage*) dan manipulasi bit. Penelitian ini mengusulkan skema *Deterministic Authenticated Encryption* (DAE v1) berbasis paradigma *Synthetic Initialization Vector* (SIV-like CTR mode) yang memadukan derivasi kunci independen HKDF-SHA256, komputasi IV sintesis HMAC-SHA256 berpemisah domain kolom, dan enkripsi stream AES-256-CTR tanpa *padding*, terintegrasi dengan pemisahan jalur kueri indeks parsial unik B-Tree PostgreSQL (`WHERE status <> 'DITOLAK'`). Evaluasi empiris pada klaster kontainer PostgreSQL 16 dengan 100.000 rekaman membuktikan bahwa DAE v1 mengeliminasi 100% kebocoran blok pertama 16-byte NIP (dari 4.900.500 pasangan baris bocor pada AES-CBC menjadi 0 pasangan). Pada uji pencarian multi-skala ($N = 1.000$ hingga $100.000$), DAE v1 mempertahankan kompleksitas logaritmik $O(\log N)$ dengan median latensi 1,101 ms pada $N = 100.000$ dan puncak memori proses CLI sebesar 10 MB, setara secara statistik dengan kueri teks polos (0,879 ms), sementara metode dekripsi memori AES-GCM terdegradasi parah hingga 230,820 ms. Selain itu, DAE v1 menghemat 32,8% total ruang penyimpanan basis data dan 28,9% memori indeks dibandingkan skema *blind index*, dengan latensi penulisan `INSERT` yang sebanding (~2,1 ms).

**Kata kunci:** Enkripsi Deterministik Terautentikasi, *Synthetic Initialization Vector*, Indeks B-Tree, PostgreSQL, UU Perlindungan Data Pribadi.

---

## 1. PENDAHULUAN

Transformasi digital pelayanan publik dalam kerangka Sistem Pemerintahan Berbasis Elektronik (SPBE) menuntut integrasi data yang cepat dan aman. Pada instansi pemerintah daerah, seperti Dinas Komunikasi, Informatika, dan Statistik (DKIS) Kota Cirebon melalui aplikasi Sistem Monitoring Penerbitan Tanda Tangan Elektronik (Sismon-TTE), data identitas kependudukan aparatur sipil negara (ASN) berupa Nomor Induk Kependudukan (NIK) dan Nomor Induk Pegawai (NIP) diproses secara intensif untuk verifikasi sertifikat elektronik ke Balai Sertifikasi Elektronik (BSrE) Badan Siber dan Sandi Negara (BSSN). Berlakunya Undang-Undang Republik Indonesia Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP), khususnya Pasal 35 dan Pasal 39, mewajibkan pengendali data pribadi untuk menerapkan langkah teknis enkripsi guna melindungi data pribadi spesifik dan umum dari akses tidak sah, kebocoran, serta manipulasi data.

Meskipun kewajiban hukum menuntut enkripsi data saat tersimpan (*data-at-rest*), implementasi enkripsi pada sistem basis data relasional (*Relational Database Management System*/RDBMS) seperti PostgreSQL menghadapi dilema fundamental arsitektural antara keamanan kriptografi dan kinerja pencarian kueri (Carvalho et al., 2025; Trabucco et al., 2026). Standar enkripsi simetris modern terautentikasi (*Authenticated Encryption with Associated Data*/AEAD) seperti AES-256-GCM bersifat probabilistik, di mana setiap operasi enkripsi menghasilkan *ciphertext* yang berbeda secara acak berkat penggunaan IV/nonce yang unik (Dworkin, 2001). Sifat probabilistik ini secara inheren menghancurkan relasi kesetaraan (*equality mapping*), sehingga klausul pencarian eksak SQL (`WHERE nik = ?`) tidak dapat memanfaatkan struktur indeks pohon B-Tree (Gui et al., 2024). Akibatnya, RDBMS terpaksa melakukan pemindaian tabel menyeluruh (*Sequential Scan*) atau aplikasi harus menarik seluruh baris ke memori untuk didekripsi satu per satu (*In-Memory Full Decryption Scan*), yang memicu degradasi komputasi linear $O(N)$ dan ledakan penggunaan memori (*heap exhaustion*) pada volume data besar (Pina et al., 2024).

Untuk mempertahankan pemanfaatan indeks B-Tree berkecepatan $O(\log N)$, sejumlah sistem mengadopsi enkripsi deterministik konvensional seperti AES-256-CBC dengan IV statis (Popa et al., 2011). Namun, penggunaan IV statis pada mode CBC membuka celah keamanan serius berupa kebocoran pola (*pattern leakage*). Karena ukuran blok AES adalah 16 byte (128 bit), dua rekaman plaintext yang berbagi 16 karakter pertama yang sama akan menghasilkan blok *ciphertext* pertama yang identik (Naveed et al., 2015). Pada format NIP di Indonesia yang terdiri atas 18 digit terstruktur (8 digit tanggal lahir + 6 digit TMT pengangkatan CPNS + 1 digit jenis kelamin + 3 digit nomor urut), ASN yang berada pada kohort formasi yang sama akan memiliki 14 hingga 16 digit awal yang identik, sehingga penyerang yang memiliki akses ke media penyimpanan dapat menyimpulkan kesamaan tanggal lahir dan instansi pegawai dengan menganalisis kesamaan blok ciphertext (Dou et al., 2024). Selain itu, skema deterministik tanpa tag autentikasi rentan terhadap serangan manipulasi bit (*bit-flipping attack*) dan serangan lintas-kolom (*cross-column replay attack*), di mana ciphertext NIK dapat disuntikkan ke kolom NIP tanpa terdeteksi (Bellare et al., 2007).

Pendekatan alternatif lain yang sering diusulkan adalah *blind indexing* berbasis HMAC (Chen et al., 2024; Khan et al., 2025). Pada pendekatan ini, data identitas dienkripsi secara probabilistik menggunakan AES-GCM, sementara kolom tambahan disediakan untuk menyimpan digest HMAC sebagai indeks pencarian deterministik terpisah. Walaupun aman, arsitektur *blind index* menimbulkan konsekuensi penggandaan ruang penyimpanan (*storage amplification*), memicu penalti latensi ganda pada siklus penulisan (*write overhead*), serta memerlukan dekripsi kandidat tambahan pada setiap pencarian untuk memverifikasi potensi tabrakan *hash* (Song et al., 2000; Curtmola et al., 2011).

Menjawab tantangan tersebut, penelitian ini merancang, mengimplementasikan, dan mengevaluasi skema *Deterministic Authenticated Encryption* (DAE v1) yang diadaptasi dari paradigma *Synthetic Initialization Vector* (SIV-like) Rogaway & Shrimpton (2006). Penelitian ini memberikan tiga kontribusi utama:
1. **Rancang Bangun Kriptografi DAE v1 Bebas Ekstensi Eksternal:** Mengembangkan skema enkripsi deterministik terautentikasi murni berbasis pustaka C bawaan OpenSSL di PHP 8.3 yang menggabungkan derivasi sub-kunci independen HKDF-SHA256, pembangkitan IV sintesis HMAC-SHA256 dengan pemisahan domain kolom (*Associated Data*), dan enkripsi stream AES-256-CTR tanpa kebutuhan padding PKCS#7.
2. **Arsitektur Pemisahan Jalur Kueri & Indeks Parsial B-Tree:** Merancang arsitektur basis data relasional PostgreSQL yang memisahkan jalur pencarian eksak terenkripsi dan pencarian teks nama bebas, serta mengimplementasikan indeks parsial unik (`WHERE status <> 'DITOLAK'`) yang mendukung kepatuhan aturan bisnis pendaftaran ulang pasca penolakan berkas.
3. **Audit Tolok Ukur Empiris & Evaluasi Kebocoran Riil:** Melakukan pengujian terisolasi bebas bias memori per proses CLI pada 100.000 rekaman data sintetis realistis DKIS Kota Cirebon (Seed: 20261116), yang membuktikan eliminasi total kebocoran blok pertama NIP (0,0000%), kesetaraan latensi sub-milidetik terhadap teks polos ($O(\log N)$), efisiensi penyimpanan fisik 32,8% lebih hemat dibanding *blind index*, dan ketahanan mutlak terhadap manipulasi bit.

---

## 2. METODE PENELITIAN

### 2.1. Arsitektur Pemisahan Jalur Kueri dan Indeks Parsial Unik
Pada sistem pemantauan penerbitan sertifikat elektronik, pengguna melakukan pencarian melalui satu bilah input (*single search input*) yang dapat menerima nomor identitas numerik (NIK 16 digit atau NIP 18 digit) maupun nama lengkap pegawai. Praktik buruk yang jamak ditemukan adalah menggabungkan seluruh kondisi ke dalam satu klausa SQL disjungtif (`WHERE nik = ? OR nip = ? OR nama_lengkap ILIKE ?`). Dalam PostgreSQL, klausul `OR` yang mencampuradukkan parameter kesetaraan terenkripsi dan pencarian pola teks memaksa perencana kueri (*query planner*) melakukan *BitmapOr* atau bahkan beralih ke *Sequential Scan* paralel yang lambat.

Untuk mengatasi inefisiensi tersebut, arsitektur sistem memisahkan jalur kueri di tingkat *Application Service Layer* sebelum kueri dieksekusi ke PostgreSQL:
1. **Deteksi Pola Input:** Input masukan divalidasi menggunakan ekspresi reguler. Jika input merupakan string numerik tepat 16 digit, input dialihkan ke jalur kueri eksak NIK. Jika tepat 18 digit, input dialihkan ke jalur kueri eksak NIP. Di luar pola numerik tersebut, input dialihkan ke jalur pencarian teks nama dengan *wildcard escaping* (`ILIKE ?`).
2. **Indeks Parsial Unik (*Partial Unique Index*):** Berdasarkan aturan bisnis tata kelola sertifikat TTE, seorang pegawai tidak boleh memiliki dua pendaftaran aktif bersamaan (`MENUNGGU_VERIFIKASI` atau `DISETUJUI`). Namun, jika pendaftaran sebelumnya berstatus `DITOLAK` (misalnya karena ketidaksesuaian dokumen surat tugas), pegawai bersangkutan berhak mendaftar kembali dengan NIK/NIP yang sama. Konstrain unik standar (`UNIQUE(nik)`) akan menolak pendaftaran ulang tersebut. Oleh karena itu, PostgreSQL dikonfigurasi menggunakan indeks parsial unik B-Tree:
   ```sql
   CREATE UNIQUE INDEX idx_pendaftaran_nik_active 
   ON pendaftaran_tte (nik) 
   WHERE status <> 'DITOLAK';
   ```
   Indeks ini secara otomatis mengecualikan baris berstatus `DITOLAK` dari pohon B-Tree aktif, sehingga menjamin integritas pencegahan duplikasi aktif sekaligus mengizinkan riwayat penolakan tersimpan berdampingan secara legal di tabel basis data.

### 2.2. Formulasi Matematis Skema Kriptografi DAE v1
Konstruksi DAE v1 dirancang mengikuti paradigma *PRF-then-CTR* (Rogaway & Shrimpton, 2006; Bellare et al., 2007) dengan jaminan keamanan *Deterministic Authenticated Encryption*. Alur pembentukan ciphertext dijabarkan sebagai berikut:

1. **Derivasi Sub-Kunci Independen (HKDF-SHA256):**  
   Dari kunci utama aplikasi $K_{master}$ (berukuran 256-bit), diturunkan dua sub-kunci independen berukuran masing-masing 32 byte menggunakan fungsi derivasi berbasis HMAC (Krawczyk & Eronen, 2010):
   $$\begin{aligned}
   K_{enc} &= \text{HKDF-SHA256}(K_{master}, \text{salt}=\emptyset, \text{info}=\text{"sismon-tte:enc:v1"}, L=32) \\
   K_{mac} &= \text{HKDF-SHA256}(K_{master}, \text{salt}=\emptyset, \text{info}=\text{"sismon-tte:mac:v1"}, L=32)
   \end{aligned} \tag{1}$$
   Pemisahan sub-kunci ini menjamin bahwa kebocoran kunci enkripsi stream tidak mengekspos kunci integritas autentikasi MAC. Kunci di-cache pada memori proses worker untuk meniadakan overhead komputasi berulang.

2. **Pengikatan Konteks Kolom (*Domain Separation*) & Vektor Inisialisasi Sintesis ($V$):**  
   Untuk mencegah serangan injeksi lintas-kolom, label nama kolom basis data ($\text{label} \in \{\text{'nik'}, \text{'nip'}\}$) diikatkan sebagai *Associated Authenticated Data* (AAD) dengan pemisah byte nol:
   $$\text{Context} = \text{label} \parallel \text{0x00} \tag{2}$$
   Synthetic IV $V$ berukuran 16 byte (128 bit) dihitung dengan mengevaluasi HMAC-SHA256 terhadap penggabungan konteks kolom dan keseluruhan plaintext $P$:
   $$V = \text{Truncate}_{16\text{B}}\Big(\text{HMAC-SHA256}\big(K_{mac}, \text{Context} \parallel P\big)\Big) \tag{3}$$
   Karena komputasi $V$ melibatkan seluruh karakter plaintext, perubahan sekecil 1 bit pada karakter terakhir NIK/NIP akan mengacak seluruh 16 byte nilai $V$ berdasarkan sifat *Strict Avalanche Criterion* (SAC) dari SHA-256.

3. **Enkripsi Stream AES-256-CTR & Format Ciphertext:**  
   Plaintext dienkripsi menggunakan algoritma AES-256 mode *Counter* (CTR) dengan parameter $IV = V$:
   $$C_{stream} = \text{AES-256-CTR}(K_{enc}, IV=V, P) \tag{4}$$
   Mode CTR mengubah cipher blok menjadi stream cipher, sehingga panjang ciphertext tepat sama dengan panjang plaintext tanpa memerlukan alokasi byte *padding* PKCS#7. Ciphertext mentah disusun dengan membubuhkan penanda versi 1-byte (`0x01`):
   $$C_{raw} = \text{0x01} \parallel V \parallel C_{stream} \tag{5}$$
   $$\text{Ciphertext} = \text{base64\_encode}(C_{raw}) \tag{6}$$

4. **Verifikasi Autentikasi dan Dekripsi:**  
   Proses dekripsi membaca $C_{raw}$ dan mengekstrak versi, $V$, serta $C_{stream}$. Plaintext kandidat $P'$ didekripsi menggunakan AES-256-CTR dengan $IV = V$. Selanjutnya, sistem menghitung ulang tag autentikasi:
   $$V' = \text{Truncate}_{16\text{B}}\Big(\text{HMAC-SHA256}\big(K_{mac}, \text{Context} \parallel P'\big)\Big) \tag{7}$$
   Kecocokan tag diuji secara konstan-waktu menggunakan fungsi `hash_equals(V, V')`. Jika terdapat ketidakcocokan (akibat manipulasi bit atau penukaran label kolom), kueri seketika menolak data dengan mengembalikan nilai `null`.

### 2.3. Desain Lingkungan Eksperimen & Dataset
Pengujian dilakukan pada infrastruktur terisolasi berbasis kontainer Docker untuk menjamin kemurnian pengukuran (*reproducibility*) tanpa mengganggu basis data operasional. Parameter lingkungan pengujian dicatat secara programmatic pada berkas metadata konfigurasi:
- **Spesifikasi Perangkat Keras Host:** Intel(R) Core(TM) i5-7300U CPU @ 2.60GHz (2 core fisik, 4 logical thread), RAM fisik 15,88 GB, Sistem Operasi Windows 10/11 x64, Docker Engine 29.9.3 (WSL2 Kernel 6.18.58.1-1).
- **Mesin Basis Data PostgreSQL:** PostgreSQL 16.15 on x86_64-pc-linux-musl (Alpine Linux) dengan konfigurasi `shared_buffers = 128MB`, `work_mem = 4MB`, `effective_cache_size = 4GB`, dan `random_page_cost = 4`.
- **Dataset Sintetis Realistis:** Sebanyak 100.000 baris pendaftaran disintesis menggunakan bilangan acak deterministik (`Seed: 20261116`). Format NIK menggunakan kode 5 kecamatan Kota Cirebon (`327401`–`327405`), tanggal lahir realistis (dengan offset +40 hari untuk wanita), dan nomor urut unik. Format NIP dibangun dari 500 kohort padat (tanggal lahir 1970–1999 dan TMT CPNS 1992–2026). Komposisi status pendaftaran disebarkan merata (*interleaved*) menggunakan algoritma pengacakan Fisher-Yates dengan proporsi: 60% `MENUNGGU_VERIFIKASI` (60.000 baris), 20% `DISETUJUI` (20.000 baris), dan 20% `DITOLAK` (20.000 baris).

---

## 3. HASIL DAN PEMBAHASAN

### 3.1. Evaluasi Rencana Eksekusi Kueri & Audit Buffer PostgreSQL
Pengujian Fase 1 mengevaluasi rencana eksekusi fisik perencana kueri PostgreSQL menggunakan perintah `EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)` terhadap 30 target pencarian acak independen. Metrik penggunaan buffer I/O diekstraksi murni dari node akar (*Root Plan Node*) tanpa akumulasi rekursif untuk mencegah bias penggandaan multi-worker.

Tabel 1 merangkum perbandingan performa rencana eksekusi kueri pada tabel berukuran 100.000 baris. Kueri terpisah (Query C, D, dan G) yang memanfaatkan indeks B-Tree menghasilkan waktu eksekusi sub-milidetik (0,075–0,082 ms). Yang paling mendasar, node akar kueri indeks B-Tree secara konsisten hanya membutuhkan **tepat 4 Shared Hit Blocks** (3 halaman internal traversal pohon B-Tree + 1 halaman heap tabel) dengan **0 Shared Read Blocks**, yang membuktikan bahwa seluruh data terlayani sempurna dari memori buffer pool tanpa ada aktivitas pembacaan fisik ke disk (*zero disk thrashing*).

Sebaliknya, pada pengujian Kontrol K1 di mana indeks sengaja dinonaktifkan, PostgreSQL beralih ke *Parallel Sequential Scan* yang melibatkan 2 *worker* latar belakang. Waktu eksekusi melonjak menjadi 13,921 ms (174 kali lebih lambat) dengan konsumsi I/O mencapai 2.367 blok buffer. Temuan ini menegaskan bahwa keberadaan indeks B-Tree pada kolom identitas merupakan prasyarat mutlak bagi efisiensi sistem basis data relasional.

**Tabel 1.** Evaluasi Rencana Eksekusi Kueri dan Konsumsi Buffer PostgreSQL ($N = 100.000$)

| Label Kueri | Struktur Kueri SQL | Operasi Rencana Eksekusi | Indeks B-Tree Digunakan | Shared Hit Blocks | Latensi Mean (ms) | Latensi p50 (ms) |
|---|---|---|---|---|---|---|
| **Query C** | NIK Exact + Single Status | `Index Scan` | `idx_pendaftaran_nik_active` | 4 | 0,075 ms | 0,059 ms |
| **Query D** | NIK Exact + Status IN Active | `Index Scan` | `idx_pendaftaran_nik_active` | 4 | 0,082 ms | 0,068 ms |
| **Query G** | NIK Exact + Status DITOLAK | `Index Scan` | `idx_pendaftaran_nik_all` | 4 | 0,078 ms | 0,066 ms |
| **Kontrol K1** | NIK Exact (Indeks Di-drop) | `Parallel Seq Scan` | *None* (Tanpa Indeks) | 2.367 | 13,921 ms | 13,850 ms |

### 3.2. Evaluasi Kebocoran Blok Pertama 16-Byte NIP & Biaya Komputasi Kriptografi
Salah satu kelemahan paling berbahaya dari enkripsi deterministik berbasis cipher blok konvensional (AES-256-CBC) adalah kebocoran prefiks identitas. Pengujian Fase 2 mengevaluasi fenomena ini secara empiris dengan membandingkan skema CBC lama melawan skema DAE v1 baru pada dua mode generator NIP (Realistis dan *Worst-Case* 500 Kohort) pada skala $N = 5.000$ dan $N = 100.000$.

**Tabel 2.** Evaluasi Empiris Kebocoran Blok Pertama 16-Byte Ciphertext NIP

| Mode Distribusi NIP | Skala Data ($N$) | Skema Kriptografi | Blok 16B Unik | Blok Bertabrakan | Pasangan Berbagi ($\sum \binom{c}{2}$) | Tingkat Kebocoran (%) |
|---|---|---|---|---|---|---|
| **Realistis** | 5.000 | Legacy AES-CBC | 4.991 | 9 | 9 | 0,3600 % |
| **Realistis** | 5.000 | **DAE v1 (CTR-SIV)** | **5.000** | **0** | **0** | **0,0000 %** |
| **Realistis** | 100.000 | Legacy AES-CBC | 95.329 | 4.465 | 4.883 | 9,1360 % |
| **Realistis** | 100.000 | **DAE v1 (CTR-SIV)** | **100.000** | **0** | **0** | **0,0000 %** |
| **Worst-Case** (500 Kohort) | 5.000 | Legacy AES-CBC | 500 | 500 | 22.500 | 100,0000 % |
| **Worst-Case** (500 Kohort) | 5.000 | **DAE v1 (CTR-SIV)** | **5.000** | **0** | **0** | **0,0000 %** |
| **Worst-Case** (500 Kohort) | 100.000 | Legacy AES-CBC | 1.500 | 1.000 | 4.900.500 | 99,5000 % |
| **Worst-Case** (500 Kohort) | 100.000 | **DAE v1 (CTR-SIV)** | **100.000** | **0** | **0** | **0,0000 %** |

Data pada Tabel 2 membuktikan bahwa pada skema CBC dengan IV statis, sebanyak **4.900.500 pasangan baris** mengalami kebocoran blok pertama pada kondisi *worst-case* $N=100.000$ (tingkat kebocoran mencapai 99,50%). Penyerang pasif dapat mengelompokkan pegawai ke dalam kohort tanggal lahir dan TMT yang sama hanya dengan melihat kesamaan 16 byte awal ciphertext. Sebaliknya, skema DAE v1 mencatatkan **0 blok tabrakan, 0 pasangan berbagi, dan 0,0000% kebocoran** di seluruh pengujian. Komputasi HMAC-SHA256 menyerap seluruh 18 digit NIP, sehingga memicu efek *avalanche* penuh yang mengacak total nilai IV sintesis $V$.

**Tabel 3.** Tolok Ukur Waktu Komputasi Kriptografi (100.000 Operasi Terisolasi)

| Operasi Kriptografi | Skema Kriptografi | Min ($\mu$s) | Mean ($\mu$s) | Median / p50 ($\mu$s) | p95 ($\mu$s) | Simpangan Baku ($\mu$s) |
|---|---|---|---|---|---|---|
| **Enkripsi ($T_{enc}$)** | Legacy AES-CBC | 27,500 $\mu$s | 49,183 $\mu$s | 41,500 $\mu$s | 74,100 $\mu$s | 44,343 $\mu$s |
| **Enkripsi ($T_{enc}$)** | **DAE v1 (CTR-SIV)** | **14,700 $\mu$s** | **21,244 $\mu$s** | **17,700 $\mu$s** | **31,600 $\mu$s** | 75,255 $\mu$s |
| **Dekripsi ($T_{dec}$)** | Legacy AES-CBC | 6,200 $\mu$s | 9,895 $\mu$s | 7,900 $\mu$s | 16,900 $\mu$s | 12,640 $\mu$s |
| **Dekripsi ($T_{dec}$)** | **DAE v1 (CTR-SIV)** | **12,200 $\mu$s** | **16,625 $\mu$s** | **13,700 $\mu$s** | **26,600 $\mu$s** | 17,193 $\mu$s |

Tabel 3 menyajikan hasil pengukuran 100.000 operasi enkripsi dan dekripsi murni. Enkripsi DAE v1 justru tercatat **56,8% lebih cepat** (rata-rata 21,244 $\mu$s vs 49,183 $\mu$s) dibanding skema lama karena kunci derivasi di-cache secara statis dan mode CTR meniadakan alokasi buffer padding PKCS#7. Pada operasi dekripsi, DAE v1 menambahkan sedikit overhead sebesar $+6,73\ \mu\text{s}$ (hanya 0,0067 ms) untuk memverifikasi tag HMAC-SHA256 via `hash_equals()`. Overhead sub-mikrodetik ini sepenuhnya terabaikan dalam pipeline kueri basis data.

### 3.3. Tolok Ukur Latensi Pencarian Multi-Skala Skenario A–D
Pengujian Fase 3 membandingkan secara komprehensif empat skenario pencarian exact-match NIK pada tiga skala volume data ($N = 1.000$, $10.000$, dan $100.000$) menggunakan proses CLI terisolasi untuk meniadakan distorsi akumulasi memori:
- **Skenario A:** Pencarian Teks Polos (*Plaintext*) berindeks B-Tree.
- **Skenario B:** Pencarian *Deterministic Authenticated Encryption* (DAE v1 CTR-SIV) berindeks B-Tree.
- **Skenario C:** Pencarian Probabilistik AES-256-GCM dengan pemindaian dekripsi memori penuh (*In-Memory Decryption Scan*).
- **Skenario D:** Pencarian Probabilistik AES-256-GCM berbantuan *HMAC-SHA256 Blind Index*.

**Tabel 4.** Rekapitulasi Statistik Latensi Pencarian Multi-Skala ($N = 1.000, 10.000, 100.000$)

| Ukuran Dataset ($N$) | Skenario Pengujian | Jumlah Sampel | Median / p50 (ms) | Mean (ms) | p95 (ms) | Simpangan Baku (ms) | Waktu CPU PHP (ms) | Puncak Memori PHP |
|---|---|---|---|---|---|---|---|---|
| **$N = 1.000$** | **A: Plaintext + B-Tree** | 30 | 0,756 ms | 0,887 ms | 1,463 ms | 0,338 ms | 0,413 ms | 10 MB |
| **$N = 1.000$** | **B: DAE v1 + B-Tree** | 30 | 0,825 ms | 0,917 ms | 1,392 ms | 0,350 ms | 0,445 ms | 10 MB |
| **$N = 1.000$** | **C: In-Memory Decrypt** | 30 | 2,931 ms | 2,991 ms | 5,226 ms | 1,065 ms | 2,384 ms | 10 MB |
| **$N = 1.000$** | **D: Blind Index** | 30 | 0,979 ms | 1,045 ms | 1,583 ms | 0,338 ms | 0,520 ms | 10 MB |
|---|---|---|---|---|---|---|---|---|
| **$N = 10.000$** | **A: Plaintext + B-Tree** | 30 | 0,812 ms | 0,865 ms | 1,248 ms | 0,209 ms | 0,372 ms | 10 MB |
| **$N = 10.000$** | **B: DAE v1 + B-Tree** | 30 | 0,846 ms | 0,937 ms | 1,555 ms | 0,271 ms | 0,429 ms | 10 MB |
| **$N = 10.000$** | **C: In-Memory Decrypt** | 30 | 27,341 ms | 26,798 ms | 38,729 ms | 9,440 ms | 23,764 ms | 10 MB |
| **$N = 10.000$** | **D: Blind Index** | 30 | 1,697 ms | 1,896 ms | 4,496 ms | 0,832 ms | 0,876 ms | 10 MB |
|---|---|---|---|---|---|---|---|---|
| **$N = 100.000$** | **A: Plaintext + B-Tree** | 30 | 0,879 ms | 1,021 ms | 2,070 ms | 0,406 ms | 0,430 ms | 10 MB |
| **$N = 100.000$** | **B: DAE v1 + B-Tree** | 30 | 1,101 ms | 1,256 ms | 2,081 ms | 0,388 ms | 0,555 ms | 10 MB |
| **$N = 100.000$** | **C: In-Memory Decrypt** | 10 | 230,820 ms | 234,812 ms | 390,749 ms | 112,406 ms | 194,578 ms | 10 MB |
| **$N = 100.000$** | **D: Blind Index** | 30 | 1,175 ms | 1,501 ms | 3,239 ms | 0,772 ms | 0,682 ms | 10 MB |

Berdasarkan Tabel 4, Skenario A dan Skenario B menunjukkan karakteristik performa logaritmik $O(\log N)$ yang sangat konsisten. Pada $N = 100.000$, median latensi Skenario B adalah **1,101 ms**, hanya berselisih tipis **+0,222 ms** dibandingkan teks polos (0,879 ms). Selisih 0,22 ms ini merupakan cerminan biaya komputasi enkripsi parameter lokal di sisi PHP, bukan perlambatan di sisi indeks basis data PostgreSQL.

Sebaliknya, Skenario C memperlihatkan ketidaklayakan skema dekripsi memori pada RDBMS: latensi meningkat linear $O(N)$ dari **2,99 ms** pada $N = 1.000$ menjadi **234,81 ms** pada $N = 100.000$, dengan rata-rata konsumsi waktu CPU PHP mencapai **194,58 ms**. Pada volume data jutaan rekaman, metode pemindaian in-memory diproyeksikan membutuhkan waktu puluhan detik per transaksi kueri. Skenario D (Blind Index) mencatatkan latensi median 1,175 ms pada $N = 100.000$, sedikit lebih lambat dibanding Skenario B akibat perlunya dua tahapan kriptografi terpisah.

### 3.4. Biaya Penulisan (Write Overhead) & Ukuran Penyimpanan Fisik Storage
Pengujian penulisan mengukur waktu eksekusi individual dari **1.000 transaksi `INSERT` rekaman baru** ke dalam tabel berukuran $N = 100.000$ baris yang telah memiliki indeks parsial aktif. Pengukuran ukuran fisik disk dilakukan menggunakan fungsi sistem PostgreSQL `SELECT pg_relation_size(...)` dan `SELECT pg_total_relation_size(...)`.

**Tabel 5.** Pengukuran Biaya Penulisan (Write Overhead) 1.000 Operasi `INSERT` ($N = 100.000$)

| Skema Arsitektur | Jumlah Operasi | Min (ms) | Mean (ms) | Median / p50 (ms) | p95 (ms) | p99 (ms) | Simpangan Baku (ms) |
|---|---|---|---|---|---|---|---|
| **Plaintext** | 1.000 INSERT | 1,595 ms | 2,630 ms | **2,088 ms** | 4,717 ms | 9,113 ms | 3,939 ms |
| **DAE v1 (CTR-SIV)** | 1.000 INSERT | 1,597 ms | 2,301 ms | **2,104 ms** | 3,231 ms | 5,083 ms | 1,044 ms |
| **Blind Index (GCM + HMAC)** | 1.000 INSERT | 1,697 ms | 2,304 ms | **2,131 ms** | 3,050 ms | 4,753 ms | 1,146 ms |

Hasil pada Tabel 5 memperlihatkan bahwa median waktu penulisan ketiga skema praktis berada pada level yang sama: **2,088 ms** (Plaintext), **2,104 ms** (DAE v1), dan **2,131 ms** (Blind Index). Waktu transaksi `INSERT` pada RDBMS relasional didominasi oleh latensi I/O penulisan transaksi ke Write-Ahead Log (WAL) dan pemeliharaan *page split* pada struktur pohon B-Tree di disk. Biaya komputasi enkripsi CPU lokal (~0,021 ms pada DAE v1) hanya berkontribusi kurang dari 1,0% dari total durasi transaksi.

**Tabel 6.** Pengukuran Ukuran Fisik Penyimpanan Tabel dan Indeks PostgreSQL ($N = 100.000$)

| Skema Arsitektur | Nama Tabel (Heap) | Ukuran Heap Tabel | Nama Indeks B-Tree | Ukuran Indeks B-Tree | Total Ukuran Relasi |
|---|---|---|---|---|---|
| **Plaintext** | `bench_plain_100k` | 11.124.736 B (**10,61 MB**) | `idx_plain_100k_nik_active` | 3.301.376 B (**3,15 MB**) | **15,97 MB** |
| **DAE v1 (CTR-SIV)** | `bench_dae_100k` | 13.975.552 B (**13,33 MB**) | `idx_dae_100k_nik_active` | 5.447.680 B (**5,20 MB**) | **20,73 MB** |
| **Blind Index (GCM + HMAC)** | `bench_bidx_100k` | 22.364.160 B (**21,33 MB**) | `idx_bidx_100k_nik_active` | 7.667.712 B (**7,31 MB**) | **30,85 MB** |

Dari Tabel 6, terungkap keunggulan efisiensi arsitektural skema DAE v1 dibandingkan *blind indexing*:
1. **Penghematan Total Penyimpanan:** DAE v1 menghemat **32,8% ruang penyimpanan total** relasi (20,73 MB vs 30,85 MB). Skema blind index membutuhkan ukuran heap tabel ganda (**21,33 MB**, +101% dibanding plaintext) karena tabel harus menyimpan dua kolom panjang secara bersamaan: `nik_gcm` (ciphertext probabilistik 60 karakter) dan `nik_bidx` (hash HMAC 64 karakter hex).
2. **Efisiensi Memori Indeks B-Tree:** Indeks B-Tree DAE v1 berukuran **5,20 MB**, menghemat **28,9% memori indeks** dibandingkan indeks blind index (**7,31 MB**). Penghematan memori indeks sangat krusial dalam RDBMS produksi berskala besar agar indeks dapat tetap berada seutuhnya di dalam memori kerja buffer pool (`shared_buffers`).

### 3.5. Analisis Keterbatasan Keamanan dan Lingkungan Nyata
Penelitian ini secara jujur mengidentifikasi batasan teoretis dan arsitektural dari skema yang diusulkan:
1. **Kebocoran Pola Kesamaan (*Equality Pattern Leakage*):**  
   Meskipun DAE v1 mengeliminasi 100% kebocoran prefiks 16-byte NIP, sifat deterministik secara matematis tetap membocorkan relasi kesetaraan (*frequency equality*): dua rekaman dengan plaintext yang sama persis akan selalu menghasilkan ciphertext yang identik. Pada konteks kolom identitas kependudukan di instansi pemerintah, kerentanan ini berhasil dimitigasi secara substansial oleh konstrain unik aktif (`idx_pendaftaran_nik_active`), di mana satu NIK/NIP aktif hanya boleh muncul satu kali dalam basis data. Namun, jika skema ini diterapkan pada atribut non-unik dengan entropi rendah (seperti kolom agama, gender, atau status perkawinan), penyerang dapat melakukan serangan inferensi frekuensi (*frequency analysis attack*).
2. **Latensi Jaringan Fisik (*Network Round-Trip Time*):**  
   Pengukuran latensi kueri pada penelitian ini (0,07–1,10 ms) dievaluasi pada lingkungan lokal terkontainerisasi. Pada arsitektur fisik terdistribusi nyata di mana server web aplikasi terhubung ke klaster basis data melalui jaringan lokal (LAN), latensi jaringan fisik (RTT berkisar 0,3–1,0 ms) akan mendominasi waktu respons end-to-end, sehingga selisih performa antara plaintext dan DAE v1 pada level pengguna akhir akan semakin tidak terbedakan secara statistik.

---

## 4. KESIMPULAN

Penelitian ini berhasil membuktikan bahwa skema *Deterministic Authenticated Encryption* (DAE v1) berbasis paradigma Synthetic IV (SIV-like CTR mode) yang dipadukan dengan pemisahan jalur kueri indeks parsial B-Tree PostgreSQL (`WHERE status <> 'DITOLAK'`) mampu menyelesaikan dilema mendasar antara keamanan kriptografi dan efisiensi pencarian basis data relasional. Pada dataset 100.000 rekaman, skema DAE v1 mengeliminasi 100% kebocoran blok pertama 16-byte NIP (dari 4.900.500 pasangan baris bocor pada AES-CBC menjadi 0 pasangan), menolak manipulasi bit secara konstan waktu, dan mempertahankan performa pencarian logaritmik $O(\log N)$ dengan median latensi 1,101 ms dan konsumsi memori CLI murni 10 MB, setara secara statistik dengan kueri teks polos (0,879 ms). Dibandingkan arsitektur *blind index*, DAE v1 menghemat 32,8% total ruang penyimpanan basis data dan 28,9% memori indeks B-Tree dengan waktu penulisan `INSERT` yang sebanding (~2,1 ms).

Pengembangan selanjutnya dapat diarahkan pada integrasi modul *Key Management Service* (KMS) terpusat berbasis perangkat keras (*Hardware Security Module*/HSM) dengan protokol rotasi kunci otomatis tanpa waktu henti (*zero-downtime key rotation*), serta eksplorasi skema *Order-Preserving Encryption* (OPE) untuk mendukung kueri rentang (*range queries*) terenkripsi pada atribut non-identitas.

---

### UCAPAN TERIMA KASIH
Penulis menyampaikan apresiasi dan terima kasih kepada Dinas Komunikasi, Informatika, dan Statistik (DKIS) Kota Cirebon atas penyediaan konteks studi kasus layanan Sistem Monitoring Penerbitan Tanda Tangan Elektronik (Sismon-TTE), serta civitas akademika STMIK IKMI Cirebon yang telah memfasilitasi sarana infrastruktur komputasi dalam pelaksanaan penelitian ini.

---

### DAFTAR PUSTAKA

Attia, A. F., & Horacek, P. (2001). Optimization of neuro-fuzzy modeling using genetic algorithm. In *Proceedings of the XXVI ASR 2001 Seminar: Instruments and Control* (pp. 5–15). VSB-Technical University of Ostrava.

Bellare, M., Boldyreva, A., & O'Neill, A. (2007). Deterministic and efficiently searchable encryption. In A. Menezes (Ed.), *Advances in Cryptology – CRYPTO 2007* (LNCS Vol. 4622, pp. 535–552). Springer. https://doi.org/10.1007/978-3-540-74143-5_30

Carvalho, M., Sá, F., & Bernardino, J. (2025). Evaluation of the impact of AES encryption on query read performance across Oracle, MySQL, and SQL Server databases. *Cryptography*, 9(4), Article 77. https://doi.org/10.3390/cryptography9040077

Chen, R., Ge, F., Guo, F., Susilo, W., Xie, H., & Wei, G. (2024). Secure in-network keyword search with efficient verification in cloud-assisted Internet of Things. *IEEE Internet of Things Journal*, 11(16), 27364–27376. https://doi.org/10.1109/JIOT.2024.3392439

Curtmola, R., Garay, J., Kamara, S., & Ostrovsky, R. (2011). Searchable symmetric encryption: Improved definitions and efficient constructions. *Journal of Computer Security*, 19(5), 895–934. https://doi.org/10.3233/JCS-2011-0426

Dou, H., Dan, Z., Xu, P., Wang, W., Xu, S., Chen, T., & Jin, H. (2024). Dynamic searchable symmetric encryption with strong security and robustness. *IEEE Transactions on Information Forensics and Security*, 19, 2370–2384. https://doi.org/10.1109/TIFS.2024.3350330

Dworkin, M. (2001). *Recommendation for block cipher modes of operation: Methods and techniques* (NIST Special Publication 800-38A). National Institute of Standards and Technology. https://doi.org/10.6028/NIST.SP.800-38A

Gui, Z., Paterson, K. G., Patranabis, S., & Warinschi, B. (2024). SWiSSSE: System-wide security for searchable symmetric encryption. *Proceedings on Privacy Enhancing Technologies*, 2024(1), 549–581. https://doi.org/10.56553/popets-2024-0032

Khan, A. N., Naveed, A., Mehmood, A., Arora, D., Khan, A. U. R., & Al-Ghamdi, M. S. (2025). FastSearch: Fast, scalable, and encrypted keyword search for cloud-assisted industrial IoT. *IEEE Internet of Things Journal*, 12(1), 845–855. https://doi.org/10.1109/JIOT.2024.3468972

Krawczyk, H., & Eronen, P. (2010). *HMAC-based extract-and-expand key derivation function (HKDF)* (RFC 5869). Internet Engineering Task Force. https://doi.org/10.17487/RFC5869

Naveed, M., Kamara, S., & Wright, C. V. (2015). Inference attacks on property-preserving encrypted databases. In *Proceedings of the 22nd ACM SIGSAC Conference on Computer and Communications Security* (pp. 644–655). Association for Computing Machinery. https://doi.org/10.1145/2810103.2813651

Pina, L., Silva, S. A. G. O., Silva, S. R. G. O., Silva, E. C. B., Silva, S. F. T., Silva, M. T. A., Santos, E. P., & Silva, F. J. S. (2024). Performance evaluation of data encryption algorithms: A comparative study of AES, ChaCha20, DES, and Triple-DES. *IEEE Access*, 12, 137330–137346. https://doi.org/10.1109/ACCESS.2024.3464761

Popa, R. A., Redfield, C. M. S., Zeldovich, N., & Balakrishnan, H. (2011). CryptDB: Protecting confidentiality with efficient query execution on encrypted databases. In *Proceedings of the Twenty-Third ACM Symposium on Operating Systems Principles* (pp. 85–100). Association for Computing Machinery. https://doi.org/10.1145/2043556.2043566

Republik Indonesia. (2018). *Peraturan Presiden Republik Indonesia Nomor 95 Tahun 2018 tentang Sistem Pemerintahan Berbasis Elektronik*. Lembaran Negara Republik Indonesia Tahun 2018 Nomor 182.

Republik Indonesia. (2022). *Undang-Undang Republik Indonesia Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi*. Lembaran Negara Republik Indonesia Tahun 2022 Nomor 196.

Rogaway, P., & Shrimpton, T. (2006). A deterministic approach to authenticated encryption. In B. Roy (Ed.), *Advances in Cryptology – ASIACRYPT 2006* (LNCS Vol. 4284, pp. 514–529). Springer. https://doi.org/10.1007/11935230_34

Song, D. X., Wagner, D., & Perrig, A. (2000). Practical techniques for searches on encrypted data. In *Proceedings of the 2000 IEEE Symposium on Security and Privacy* (pp. 44–55). IEEE Computer Society. https://doi.org/10.1109/SECPRI.2000.848445

Trabucco, M., Berguiga, A., Pinto, C. L. R. C., & Le, D. V. (2026). End-to-end encrypted database architecture for secure cloud applications. *Journal of Systems Architecture*, 170, Article 103602. https://doi.org/10.1016/j.sysarc.2025.103602
