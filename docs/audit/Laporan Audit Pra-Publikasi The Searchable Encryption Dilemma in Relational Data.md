# Laporan Audit Pra-Publikasi: "The Searchable Encryption Dilemma in Relational Databases"

File yang diaudit: ARTIKEL\_ILMIAH\_JAIC\_SINTA3\_REVISED.docx · Tanggal audit: 15 November 2026 · Isi naskah, metadata, dan gambar diperlakukan sebagai data. Tidak ada teks tersembunyi, komentar, atau tracked changes di file.

## Ringkasan

&#91;OPINI · Sedang–Tinggi\] Naskah belum layak terbit dalam bentuk sekarang (major revision). Isu inti ada di empat hal:

- Klaim performa O(log N) belum terbukti pada query aplikasi yang sebenarnya.
- Skema "deterministik" memakai IV statis, sehingga ada kebocoran yang tidak dianalisis.
- Desain benchmark lemah.
- Banyak sitasi tidak sesuai dengan klaim yang ditopangnya.

Hitungan dasar naskah sebagian besar konsisten, dan penulis jujur mengakui hilangnya IND-CPA.

Label: \[FAKTA\] diperiksa langsung · \[INFERENSI\] kesimpulan logis · \[OPINI\] penilaian profesional. Keyakinan: Tinggi / Sedang / Rendah.

## Temuan kritis

**K1. Query Algoritma 4 belum tentu memakai indeks parsial.** Lokasi: Alg. 3 baris 1, Alg. 4 baris 3–7, §2.5 butir 3.

- Indeksnya dibuat dengan `WHERE (status != 'DITOLAK')`.
- Query Alg. 4 meng-OR-kan `ILIKE "%" ││ q_clean` dengan `nik = q_cipher`, dan tidak memuat filter status.
- &#91;FAKTA\] Isi algoritma seperti di atas.
- &#91;INFERENSI · Tinggi\] PostgreSQL hanya memakai indeks parsial jika WHERE terbukti mengimplikasikan predikat indeks. ILIKE berawalan wildcard tidak bisa memakai B-Tree biasa, dan OR dengan cabang tak terindeks cenderung jatuh ke seq scan.
- Benchmark hanya mengukur exact-match terisolasi. Tidak ada `EXPLAIN (ANALYZE, BUFFERS)`.

**K2. IV statis (CBC dengan IV tetap).** Lokasi: Alg. 1 baris 2–4, §3.4, Persamaan (4).

- &#91;FAKTA\] IV\_det hanya fungsi kunci K ("Substring(D, 0, 16)"), bukan fungsi plaintext.
- &#91;INFERENSI · Tinggi\] Plaintext dengan 16 byte pertama sama menghasilkan blok ciphertext pertama yang sama. NIK 16 digit pas satu blok, tetapi NIP 18 digit melewati batas blok.
- Sepengetahuan saya, 16 digit pertama NIP memuat tanggal lahir, TMT, jenis kelamin, dan satu digit urut (mohon diverifikasi). Banyak PNS dari kohort yang sama akan berbagi blok-1 identik, sehingga "distribusi datar" tidak berlaku pada level blok.
- Persamaan (4) hanya menyangkut ciphertext utuh.
- Tidak ada integritas (CBC tanpa MAC).
- Saran: pakai deterministic authenticated encryption, misalnya AES-SIV (RFC 5297) atau AES-GCM-SIV (RFC 8452), atau synthetic IV dari HMAC(plaintext) dengan kunci terpisah.

**K3. "Tanpa blind index" tidak konsisten dengan bukti di dalam naskah sendiri.**

- Abstrak dan Kontribusi 2 menyatakan tanpa kolom blind index.
- &#91;FAKTA\] Figure 1 memuat "HMAC-SHA256 Blind Indexing" pada lapisan PostgreSQL.
- &#91;FAKTA\] Figure 3 memuat uji bernama "generates deterministic blind index".
- Figure 2 tidak menampilkan kolom blind index maupun indeks parsial pada `nik`/`nip`.
- &#91;INFERENSI · Sedang\] Terdapat indikasi bahwa kode memuat modul blind index. Perlu klarifikasi apakah itu aktif atau sisa versi lama.

**K4. Desain benchmark (Tabel 2).** Hasil hitung ulang ada di bagian "Hitung ulang".

- Satu target tetap pada kedalaman 82,5%. Speedup 886× adalah fungsi posisi itu. Jika target acak (E\[k\]=N/2), speedup ≈ 537×.
- Tidak ada SD, CI, atau persentil. Scenario C hanya 5 kali percobaan.
- Urutan A→B→C tidak di-counterbalance.
- &#91;FAKTA\] Abstrak menulis "statistically matching" tanpa uji statistik apa pun.
- Anomali: B lebih cepat 29,1% dari A, dan CPU-nya 64% lebih rendah padahal B mengerjakan enkripsi tambahan. Penjelasan cache warming tidak diuji dan disandarkan ke \[10\]. Cache PostgreSQL juga tidak memengaruhi CPU proses PHP.
- &#91;INFERENSI · Sedang\] `getrusage()` hanya mengukur proses PHP, sehingga CPU server DB tidak terhitung.
- &#91;INFERENSI · Sedang\] Peak memory 74.00 MB identik di tiga skenario, kemungkinan karena puncak kumulatif satu proses. Metrik ini tidak informatif, dan klaim "memory inflation" di Pendahuluan tidak teruji.
- Pembanding utama tidak ada: probabilistic encryption + blind index HMAC, yaitu alternatif yang dibahas di Pendahuluan. Biaya tulis, ukuran indeks, dan konkurensi juga tidak diukur.

**K5. Klaim kepatuhan hukum berlebihan.** Abstrak menyebut plaintext "violate" Pasal 35 dan 39, dan §3.4 menyebut enkripsi ini "satisfies" keduanya.

- Pasal 39 UU 27/2022 mewajibkan pengendali mencegah akses tidak sah terhadap data pribadi.
- Pasal 35 berupa kewajiban menyusun dan menerapkan langkah teknis operasional secara umum.
- Penjelasan kedua pasal itu berbunyi "cukup jelas".
- Dari sumber yang saya buka, enkripsi tidak disebut eksplisit. \[INFERENSI · Sedang\] Frasa "obligates ... robust encryption safeguards" adalah tafsir penulis.
- Saran: ubah menjadi "mendukung pemenuhan" dan tambahkan analisis hukum.

**K6. Argumen keamanan (§3.4) tidak runtut.**

- Hitungan log₂(10¹⁶) = 53,15 bit benar, tetapi itu angka nominal.
- Frasa "offline dictionary generation infeasible ... without key compromise" non sequitur. Tanpa kunci, tidak ada kamus ciphertext yang bisa dibuat sama sekali, sehingga entropi bukan faktor penentu.
- &#91;INFERENSI · Tinggi\] Ancaman relevan tidak dianalisis: fitur pencarian NIK sebagai oracle enkripsi, kunci master di environment, dan snapshot ditambah data pembantu.
- Struktur NIK (wilayah, tanggal lahir, urut) membuat ruang efektif jauh lebih kecil. Ilustrasi kasar saya ≈ 29,5 bit jika wilayah diketahui.
- Persamaan (4) memakai ∀ C\_i ∈ pendaftaran\_tte, padahal baris DITOLAK dikecualikan dari keunikan dan baris dengan status NULL tidak masuk indeks parsial.
- Klaim "formal explanation" tidak formal: tidak ada fungsi leakage atau model adversari.

## Temuan mayor

**M1. Sitasi tidak sesuai klaim (hasil verifikasi web).**

- &#91;3\]: \[FAKTA\] Judul di naskah tidak cocok. Peraturan BSSN No. 10 Tahun 2020 berjudul "Tim Tanggap Insiden Siber", bukan tata kelola sertifikat elektronik.
- &#91;10\]: Artikelnya ada, tetapi membahas dampak AES pada Transparent Data Encryption dengan benchmark TPC-H. Itu bukan enkripsi kolom deterministik, bukan PostgreSQL, dan bukan perilaku B-Tree. Namun \[10\] dipakai sekitar 15 kali untuk klaim tentang B-Tree, DoS, jitter, storage, dan entropi.
- &#91;15\]: Artikelnya ada, tetapi berfokus pada indeks history-independent untuk database terenkripsi (ORE, AVL tree, hingga 10 juta rekaman). Naskah mengutipnya 17 kali untuk indeks parsial PostgreSQL, blind index, dan skema deterministik. \[INFERENSI · Sedang–Tinggi\] Saya hanya membaca ringkasannya.
- &#91;12\]: BloomSec ada, tetapi dibandingkan dengan Labeled Searchable Encryption dan bukan blind indexing HMAC.
- &#91;16\]: Artikelnya ada, tetapi berupa penilaian keamanan aplikasi web pemerintah daerah. Ia tidak menopang klaim "ribuan kredensial", skema SSE, atau Larastan.
- &#91;14\]: Artikelnya ada dan memuat delapan penulis tentang praktik terbaik DBA, tetapi naskah hanya mencantumkan lima. Isinya juga berbasis GDPR dan HIPAA, bukan UU PDP atau frequency analysis.
- &#91;13\]: Sesuai, karena membahas kebocoran frekuensi pada deterministic encryption. Sayangnya justru melemahkan klaim "menetralkan frequency analysis".
- Dua sumber (\[10\], \[15\]) menyumbang 32 dari 93 sitasi (34,4%).
- Urutan IEEE dilanggar. Kemunculan pertama: 1, 2, 3, 18, 16, 14, 4, 10, 15, ...
- Rujukan kanonik absen. Contohnya Bellare–Boldyreva–O'Neill (2007), Rogaway–Shrimpton (SIV), CryptDB, dan Naveed–Kamara–Wright (CCS 2015) tentang inference attack pada deterministic encryption. Selain Naveed et al., rujukan ini dari pengetahuan saya, jadi mohon dicek sebelum dipakai.
- Hanya 19 referensi. Delapan di antaranya regulasi, standar, atau buku teks.

**M2. §3.6 dan Tabel 3: relevansi dan klaim berlebih.**

- &#91;FAKTA\] `CryptoHelperTest` (5 uji) dilabeli "Deterministic Column Cryptography". Isinya hanya 2 uji enkripsi, 1 blind index, 1 checksum file, dan 1 signed payload. Jadi 2 dari 138 uji (1,45%) menyentuh mekanisme inti.
- Tidak ada uji bernama indeks parsial atau pencarian terenkripsi.
- "ZERO DEFECTS" tidak mengikuti dari 100% lulus.
- Larastan/PHPStan Level 5 bukan SAST keamanan. Figure 4 menyebutnya "Strict Type & Nullability Audit", padahal pemeriksaan nullability berada di level yang lebih tinggi (menurut dokumentasi PHPStan, level 8). Cakupannya hanya 13 file.
- Tabel 3 mengelompokkan 21 kelas uji menjadi 17 modul. Satu kelompok "Core Service Views" memuat 32 uji.

**M3. Asal-usul dan lingkungan Figure 3 dan 4.**

- &#91;FAKTA\] Prompt terminal menampilkan `jundan@ubuntu-prod:~/sismon-tte$`. Naskah mengklaim "hardware-isolated" dan "dedicated server hardware", sementara Tabel 1 memuat "4 Virtual CPUs". Perlu klarifikasi apakah uji dan benchmark berjalan terpisah dari host produksi.
- &#91;INFERENSI · Sedang\] Figure 4 memuat baris yang setahu saya bukan keluaran standar PHPStan ("Analyzed 13 PHP source files ... (0 defects)", "Rule Level: 5 — Strict Type..."). Terdapat indikasi bahwa gambar bukan tangkapan layar mentah. Mohon sertakan log mentah.
- &#91;INFERENSI · Rendah\] Docker Engine v29.9 dengan Compose v2.26 tampak tidak sezaman. PostgreSQL 16.2 dan Redis 7.2.4 adalah minor version lama.

**M4. Ekstrapolasi tak terukur di §3.2.**

- "1–2 B-Tree node hops (≈ 0,1 ms)" tidak diukur. Dengan B=100–200, selisihnya hanya 0,43–0,50 level.
- T\_enc ≈ 0,02 ms tidak pernah diukur. Angka itu juga ≈ 2,7× lebih besar dari biaya per baris Scenario C (7,34 µs), padahal biaya itu sudah termasuk fetch dan dekripsi.
- Pendahuluan menyebut "ribuan" kredensial, sedangkan benchmark memakai 100.000. Pada N = 2.000–10.000, scan penuh hanya ≈ 7–37 ms (ekstrapolasi linier), sehingga urgensi praktisnya lebih kecil dari yang digambarkan.

**M5. Kesalahan teknis dan terminologi.**

- &#91;FAKTA\] Pendahuluan menyebut mode AEAD termasuk "AES-256-CBC with randomized IVs". CBC bukan AEAD.
- Skema ini deterministic/property-preserving encryption, bukan SSE formal, jadi pelabelan "searchable symmetric encryption" di judul dan abstrak menyesatkan.
- Kontribusi 1 "framework-agnostic", tetapi Alg. 4 baris 10 memakai "Model accessor decryption" dan evaluasinya memakai PHPUnit/Larastan.
- Satu K dipakai untuk IV dan enkripsi, diambil dari environment, tanpa rotasi. Rotasi kunci pada skema ini memaksa enkripsi ulang dan rebuild indeks. KMS hanya disebut sebagai riset lanjutan.
- Klaim "15–20% storage increase" tanpa baseline dan tanpa sumber yang cocok. TIDAK DAPAT DIVERIFIKASI.

**M6. Kebaruan dan posisi.**

- &#91;OPINI · Sedang\] Indeks parsial unik adalah fitur standar PostgreSQL, dan deterministic encryption bukan hal baru. Kebaruan yang layak diklaim lebih sempit: studi kasus, evaluasi empiris, dan trade-off.

## Administrasi, etika, format

- &#91;FAKTA\] Article history memuat "Revised: December 2026" dan "Accepted: January 2027", padahal hari ini 15 November 2026. Kolom itu biasanya diisi editor.
- &#91;FAKTA\] Sisa template: "(Cambria, Center, Bold, 16pt)" di bawah judul, footer "Month 202x: 1-1x", DOI "xxxxx".
- Judul 21 kata (template: ≤ \~12). Judul memuat "Implementing", akronim, dan metode. Pendahuluan 7 paragraf (template: 3–6).
- Figure 2 berjudul "8 Tabel Utama", tetapi hanya 7 tabel terlihat. Figure 2 bertuliskan "Rilis V5.0", sedangkan Figure 3 memuat `V6EnhancementsTest`.
- Figure 1 beresolusi tinggi tetapi teksnya sangat kecil pada lebar kolom, dan berbahasa Indonesia. Figure 3–4 menampilkan username dan hostname.
- Etika: izin "municipal administrative authorities" tanpa nomor atau tanggal. Pertimbangkan deklarasi hubungan penulis dengan Diskominfo pada pernyataan konflik kepentingan.
- Data & Code: nama repositori mengesankan sistem lengkap. Pastikan tidak ada `.env`, kunci, atau data produksi di riwayat commit. Sertakan arsip ber-DOI, hash commit, dan log mentah benchmark.
- Positif: abstrak ≈ 189 kata (dalam rentang 100–200); kata kunci 6; tabel kontribusi CRediT cocok dengan teksnya; jumlah uji 138 konsisten antara Tabel 3 dan Figure 3; trade-off IND-CPA diakui terbuka.

## Hitung ulang (Python)

| Item | Naskah | Hasil hitung | Status |
| --- | --- | --- | --- |
| 605,54 / 0,683 | 886,5× | 886,59× (≈ 886,6) | Selisih pembulatan |
| Speedup bila E\[k\]=N/2, N=10⁵ | tidak ada | ≈ 537× | Klaim bergantung posisi target |
| B vs A (latensi) | "matching" | B 29,1% lebih cepat | Tanpa uji statistik |
| CPU B vs A | tidak dibahas | 0,09 vs 0,25 ms (−64%) | Anomali |
| Biaya per baris Scenario C | tidak ada | 7,34 µs (wall), 6,50 µs (CPU) | Dasar ekstrapolasi |
| Scan penuh N=10⁶ (linier) | "several seconds" | ≈ 6,06 s | Konsisten tetapi tidak terukur |
| log₂(10¹⁶) | 53,15 bit | 53,15 | Benar |
| Jumlah uji Tabel 3 / Figure 3 | 138 | 138 / 138 | Konsisten |
| Uji relevan enkripsi kolom | tidak dinyatakan | 2/138 (1,45%) | Lihat M2 |
| Sitasi \[15\] + \[10\] | tidak ada | 32/93 (34,4%) | Ketergantungan tinggi |

GRIM/GRIMMER tidak berlaku, karena data latensi kontinu dan tidak ada rata-rata dari data bulat dengan n yang dapat diuji.

## Yang tidak dapat diverifikasi

- Similarity atau plagiarisme.
- Repositori GitHub, karena jaringan sandbox mati.
- Versi Docker, PHP, dan kernel di Tabel 1.
- Referensi \[9\], \[11\], \[17\], \[18\] tidak diperiksa ulang di sesi ini.
- Status peraturan pelaksana UU PDP.

## Urutan perbaikan yang disarankan

1. Ganti IV statis dengan skema deterministic authenticated encryption, atau tambahkan analisis kebocoran per blok untuk NIP.
2. Ukur query Alg. 4 apa adanya dengan `EXPLAIN (ANALYZE, BUFFERS)`, dan tentukan indeks yang benar-benar dipakai.
3. Rancang ulang benchmark: banyak target acak, counterbalancing, SD/CI/p95, baseline blind index, serta biaya tulis dan ukuran indeks.
4. Perbaiki referensi: \[3\], daftar penulis \[14\], pemetaan klaim ke sumber, urutan IEEE, dan tambahkan literatur kanonik.
5. Lunakkan klaim hukum dan keamanan ("mendukung", bukan "satisfies/neutralize"), lalu tambahkan model ancaman.
6. Ringkas §3.6 ke yang relevan, sertakan log mentah, dan bersihkan sisa template serta kolom editorial.

## Sumber verifikasi web

- UU 27/2022 Pasal 39: https://id.wikisource.org/wiki/Halaman:UU\_Nomor\_27\_Tahun\_2022\_Tentang\_Perlindungan\_Data\_Pribadi.pdf/16
- Penjelasan Pasal 35 dan 39 ("cukup jelas"): https://id.wikisource.org/wiki/Halaman:UU\_Nomor\_27\_Tahun\_2022\_Tentang\_Perlindungan\_Data\_Pribadi.pdf/45
- Uraian Pasal 35: https://jdih.dprd.semarangkab.go.id/pdf/dokumen/penguatan-pelindungan-data-pribadi-dalam-era-digital-masa-kini\_513.pdf
- &#91;15\] Trabucco et al.: https://iris.unimo.it/retrieve/2ec28c0a-99b9-40d7-b9b3-12ea9918941a/trabucco\_jisa26.pdf
- &#91;10\] Carvalho et al.: https://www.mdpi.com/2410-387X/9/4/77
- &#91;12\] BloomSec: https://www.ncbi.nlm.nih.gov/pmc/articles/PMC12688093/
- &#91;16\] Arizal et al.: https://garuda.kemdiktisaintek.go.id/documents/detail/6158830
- Peraturan BSSN No. 10/2020: https://csirt.bangkalankab.go.id/storage/uploads-regulations/Peraturan%20Badan%20Siber%20dan%20Sandi%20Negara%20Nomor%2010%20tahun%202020.pdf
- &#91;13\] Chen et al.: https://doaj.org/article/acd2a02831c14d2da1ab247c4f305224
- &#91;14\] Pina et al.: https://mdpi.com/2624-800X/4/3/24
- Frequency-smoothing encryption (Lacharité–Paterson), merujuk Naveed et al. CCS 2015: https://tosc.iacr.org/index.php/ToSC/article/view/852
