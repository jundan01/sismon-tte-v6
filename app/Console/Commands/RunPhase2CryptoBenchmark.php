<?php

namespace App\Console\Commands;

use App\Services\Security\CryptoHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunPhase2CryptoBenchmark extends Command
{
    protected $signature = 'bench:phase2-crypto
                            {--eval-leakage : Jalankan evaluasi kebocoran blok pertama NIP (5k & 100k, realistis & worst-case)}
                            {--eval-ops : Jalankan tolok ukur biaya operasi kriptografi T_enc dan T_dec (100.000 op)}
                            {--verify-explain : Jalankan EXPLAIN (ANALYZE, BUFFERS) pasca migrasi pada Query C, D, G}
                            {--seed=20261116 : Seed bilangan acak untuk keterulangan benchmark}';

    protected $description = 'Fase 2: Evaluasi kebocoran blok pertama NIP, tolok ukur biaya operasi kriptografi, dan verifikasi EXPLAIN pasca-migrasi';

    public function handle(): int
    {
        $this->info("================================================================================");
        $this->info("  FASE 2: TOLOK UKUR KRIPTOGRAFI DETERMINISTIK TERAUTENTIKASI (DAE v1)");
        $this->info("================================================================================");

        $seed = (int) $this->option('seed');
        $runLeakage = (bool) $this->option('eval-leakage');
        $runOps = (bool) $this->option('eval-ops');
        $runExplain = (bool) $this->option('verify-explain');

        // Jika tidak ada flag spesifik yang dipilih, jalankan evaluasi kebocoran & tolok ukur operasi
        if (!$runLeakage && !$runOps && !$runExplain) {
            $runLeakage = true;
            $runOps = true;
        }

        $resultsDir = base_path('bench/results');
        if (!is_dir($resultsDir)) {
            mkdir($resultsDir, 0755, true);
        }

        if ($runLeakage) {
            $this->evaluateFirstBlockLeakage($seed, $resultsDir);
        }

        if ($runOps) {
            $this->benchmarkCryptoOperations($seed, $resultsDir);
        }

        if ($runExplain) {
            $this->verifyPostMigrationExplain($seed, $resultsDir);
        }

        $this->info("Semua tugas tolok ukur Fase 2 yang diminta telah selesai.");
        return 0;
    }

    /**
     * Bagian 1: Evaluasi Kebocoran Blok Pertama (16-Byte) NIP
     * Membandingkan Skema Lama (AES-256-CBC) vs Skema Baru (DAE CTR-SIV)
     * Pada Mode Realistis dan Worst-Case, untuk N = 5.000 dan N = 100.000.
     */
    protected function evaluateFirstBlockLeakage(int $seed, string $resultsDir): void
    {
        $this->info("\n--- [BAGIAN 1] Evaluasi Kebocoran Blok Pertama (16 Byte) NIP ---");

        $csvPath = $resultsDir . '/first_block_leakage.csv';
        $csvFile = fopen($csvPath, 'w');
        fputcsv($csvFile, [
            'generator_mode',
            'dataset_size',
            'scheme',
            'total_records',
            'unique_first_16b_blocks',
            'colliding_blocks_count',
            'max_rows_sharing_single_block',
            'total_sharing_pairs',
            'colliding_rows_count',
            'leakage_rate_pct'
        ]);

        $configs = [
            ['mode' => 'realistic',  'size' => 5000],
            ['mode' => 'realistic',  'size' => 100000],
            ['mode' => 'worst_case', 'size' => 5000],
            ['mode' => 'worst_case', 'size' => 100000],
        ];

        foreach ($configs as $cfg) {
            $mode = $cfg['mode'];
            $size = $cfg['size'];

            $this->line("Membangkitkan dataset NIP [Mode: {$mode}, N = " . number_format($size, 0, ',', '.') . "]...");
            $nips = $this->generateNipDataset($mode, $size, $seed);

            // 1. Evaluasi Skema Lama (AES-256-CBC)
            $this->line("  -> Menguji Skema Lama (AES-256-CBC Static IV)...");
            $oldStats = $this->analyzeSchemeLeakage($nips, 'legacy_cbc');
            fputcsv($csvFile, array_merge([$mode, $size, 'legacy_cbc'], $oldStats));

            // 2. Evaluasi Skema Baru (DAE CTR-SIV)
            $this->line("  -> Menguji Skema Baru (DAE CTR-SIV Deterministik)...");
            $newStats = $this->analyzeSchemeLeakage($nips, 'new_dae_siv');
            fputcsv($csvFile, array_merge([$mode, $size, 'new_dae_siv'], $newStats));

            $this->table(
                ['Skema', 'N', 'Blok 16B Unik', 'Blok Tabrakan', 'Max Baris/Blok', 'Total Pasangan Berbagi', 'Tingkat Kebocoran (%)'],
                [
                    [
                        'Legacy AES-CBC',
                        number_format($size),
                        number_format($oldStats['unique_first_16b_blocks']),
                        number_format($oldStats['colliding_blocks_count']),
                        number_format($oldStats['max_rows_sharing_single_block']),
                        number_format($oldStats['total_sharing_pairs']),
                        number_format($oldStats['leakage_rate_pct'], 4) . ' %'
                    ],
                    [
                        'New DAE CTR-SIV',
                        number_format($size),
                        number_format($newStats['unique_first_16b_blocks']),
                        number_format($newStats['colliding_blocks_count']),
                        number_format($newStats['max_rows_sharing_single_block']),
                        number_format($newStats['total_sharing_pairs']),
                        number_format($newStats['leakage_rate_pct'], 4) . ' %'
                    ],
                ]
            );
        }

        fclose($csvFile);
        $this->info("✓ Data mentah kebocoran disimpan di: {$csvPath}");
    }

    /**
     * Menganalisis frekuensi kemunculan blok pertama (16 byte pertama raw ciphertext)
     */
    protected function analyzeSchemeLeakage(array $nips, string $scheme): array
    {
        $totalRecords = count($nips);
        $blockCounts = [];

        foreach ($nips as $nip) {
            if ($scheme === 'legacy_cbc') {
                $ciphertextBase64 = CryptoHelper::encryptFieldLegacy($nip);
            } else {
                $ciphertextBase64 = CryptoHelper::encryptField($nip, 'nip');
            }

            $rawCiphertext = base64_decode($ciphertextBase64, true);
            // 16 byte pertama dari raw ciphertext
            $first16Bytes = substr($rawCiphertext, 0, 16);

            $blockCounts[$first16Bytes] = ($blockCounts[$first16Bytes] ?? 0) + 1;
        }

        $uniqueBlocks = count($blockCounts);
        $collidingBlocks = 0;
        $maxRowsSingleBlock = 0;
        $totalSharingPairs = 0;
        $collidingRowsCount = 0;

        foreach ($blockCounts as $cnt) {
            if ($cnt > $maxRowsSingleBlock) {
                $maxRowsSingleBlock = $cnt;
            }
            if ($cnt > 1) {
                $collidingBlocks++;
                $collidingRowsCount += $cnt;
                // Kombinatorika pasangan berbagi: c * (c - 1) / 2
                $totalSharingPairs += ($cnt * ($cnt - 1)) / 2;
            }
        }

        $leakageRate = ($totalRecords > 0) ? ($collidingRowsCount / $totalRecords) * 100.0 : 0.0;

        return [
            'total_records'                 => $totalRecords,
            'unique_first_16b_blocks'       => $uniqueBlocks,
            'colliding_blocks_count'        => $collidingBlocks,
            'max_rows_sharing_single_block' => $maxRowsSingleBlock,
            'total_sharing_pairs'           => $totalSharingPairs,
            'colliding_rows_count'          => $collidingRowsCount,
            'leakage_rate_pct'              => $leakageRate,
        ];
    }

    /**
     * Generator NIP Dua Mode Berformat Asli (18 Digit):
     * Format: YYYYMMDD (lahir) + YYYYMM (TMT CPNS) + G (jenis kelamin 1/2) + SSS (urut 001..999)
     */
    protected function generateNipDataset(string $mode, int $size, int $seed): array
    {
        mt_srand($seed);

        $nips = [];
        $seqTracker = [];

        if ($mode === 'realistic') {
            // Kohort bulanan berbobot untuk TMT CPNS (mengelompok pada rekrutmen massal)
            // Bobot bulan rekrutmen: Januari (15%), Maret (25%), Oktober (10%), Desember (30%), Lainnya (20%)
            $weightedMonths = [
                '01' => 15, '02' => 2, '03' => 25, '04' => 3, '05' => 2, '06' => 3,
                '07' => 5, '08' => 3, '09' => 2, '10' => 10, '11' => 0, '12' => 30
            ];
            $monthPool = [];
            foreach ($weightedMonths as $m => $w) {
                for ($i = 0; $i < $w; $i++) {
                    $monthPool[] = $m;
                }
            }

            for ($i = 0; $i < $size; $i++) {
                // Tanggal Lahir: 1970 - 2000
                $birthYear = mt_rand(1970, 2000);
                $birthMonth = str_pad((string) mt_rand(1, 12), 2, '0', STR_PAD_LEFT);
                $birthDay = str_pad((string) mt_rand(1, 28), 2, '0', STR_PAD_LEFT);
                $birthDate = "{$birthYear}{$birthMonth}{$birthDay}";

                // TMT CPNS: Usia 21 s.d. 30 tahun saat diangkat, tidak boleh melewati November 2026
                $minTmtYear = min(2026, $birthYear + 21);
                $maxTmtYear = min(2026, $birthYear + 30);
                $tmtYear = mt_rand($minTmtYear, $maxTmtYear);

                if ($tmtYear === 2026) {
                    // Maksimal bulan 11 (November) 2026
                    $tmtMonth = str_pad((string) mt_rand(1, 11), 2, '0', STR_PAD_LEFT);
                } else {
                    $tmtMonth = $monthPool[mt_rand(0, count($monthPool) - 1)];
                }
                $tmt = "{$tmtYear}{$tmtMonth}";

                // Jenis Kelamin: 1 (Laki-laki), 2 (Perempuan)
                $gender = (string) mt_rand(1, 2);

                // Nomor Urut Riil: mencerminkan distribusi ASN yang berbagi tanggal lahir + TMT + gender
                $prefixKey = "{$birthDate}{$tmt}{$gender}";
                $seqTracker[$prefixKey] = ($seqTracker[$prefixKey] ?? 0) + 1;
                $seqNumber = str_pad((string) ($seqTracker[$prefixKey] % 1000), 3, '0', STR_PAD_LEFT);
                if ($seqNumber === '000') {
                    $seqNumber = '001';
                }

                $nips[] = "{$birthDate}{$tmt}{$gender}{$seqNumber}";
            }
        } else {
            // Mode worst_case: 500 kohort padat tetap
            // Seluruh N pegawai dibagikan ke dalam 500 kohort ini
            $cohorts = [];
            for ($c = 0; $c < 500; $c++) {
                $bYear = 1970 + ($c % 31);
                $bMonth = str_pad((string) (($c % 12) + 1), 2, '0', STR_PAD_LEFT);
                $bDay = str_pad((string) (($c % 28) + 1), 2, '0', STR_PAD_LEFT);

                $tYear = min(2026, $bYear + 22 + ($c % 7));
                $tMonth = ($tYear === 2026) ? str_pad((string) (($c % 11) + 1), 2, '0', STR_PAD_LEFT)
                                             : str_pad((string) (($c % 12) + 1), 2, '0', STR_PAD_LEFT);

                $cohorts[] = [
                    'birth' => "{$bYear}{$bMonth}{$bDay}",
                    'tmt'   => "{$tYear}{$tMonth}",
                ];
            }

            for ($i = 0; $i < $size; $i++) {
                // Pilih salah satu dari 500 kohort secara padat
                $cohort = $cohorts[$i % 500];
                $gender = (string) (($i % 2) + 1);

                $prefixKey = "{$cohort['birth']}{$cohort['tmt']}{$gender}";
                $seqTracker[$prefixKey] = ($seqTracker[$prefixKey] ?? 0) + 1;
                $seqNumber = str_pad((string) ($seqTracker[$prefixKey] % 1000), 3, '0', STR_PAD_LEFT);
                if ($seqNumber === '000') {
                    $seqNumber = '001';
                }

                $nips[] = "{$cohort['birth']}{$cohort['tmt']}{$gender}{$seqNumber}";
            }
        }

        return $nips;
    }

    /**
     * Bagian 2: Tolok Ukur Biaya Operasi Kriptografi (100.000 Operasi)
     * Mengukur T_enc dan T_dec untuk Skema Lama vs Skema Baru.
     * Menghasilkan bench/results/crypto_benchmark_ops.csv dan bench/results/crypto_benchmark_summary.json.
     */
    protected function benchmarkCryptoOperations(int $seed, string $resultsDir): void
    {
        $this->info("\n--- [BAGIAN 2] Tolok Ukur Biaya Operasi Kriptografi (100.000 Operasi) ---");
        $iterations = 100000;

        mt_srand($seed);

        // Siapkan sampel plaintext NIP (18 karakter) dan NIK (16 karakter)
        $this->line("Mempersiapkan sampel data untuk 100.000 iterasi...");
        $plaintexts = [];
        for ($i = 0; $i < $iterations; $i++) {
            $plaintexts[] = '198' . mt_rand(0, 9) . str_pad((string) mt_rand(1, 12), 2, '0', STR_PAD_LEFT) .
                            str_pad((string) mt_rand(1, 28), 2, '0', STR_PAD_LEFT) . '201' . mt_rand(0, 9) .
                            str_pad((string) mt_rand(1, 12), 2, '0', STR_PAD_LEFT) . mt_rand(1, 2) .
                            str_pad((string) ($i % 999 + 1), 3, '0', STR_PAD_LEFT);
        }

        // Pre-enkripsi untuk uji dekripsi agar tidak membebani waktu pengukuran dekripsi
        $this->line("Membuat pre-encrypted ciphertexts untuk uji dekripsi murni...");
        $oldCiphertexts = [];
        $newCiphertexts = [];
        for ($i = 0; $i < $iterations; $i++) {
            $oldCiphertexts[] = CryptoHelper::encryptFieldLegacy($plaintexts[$i]);
            $newCiphertexts[] = CryptoHelper::encryptField($plaintexts[$i], 'nip');
        }

        $csvPath = $resultsDir . '/crypto_benchmark_ops.csv';
        $csvFile = fopen($csvPath, 'w');
        fputcsv($csvFile, ['iteration', 'old_enc_us', 'old_dec_us', 'new_enc_us', 'new_dec_us']);

        $oldEncTimes = [];
        $oldDecTimes = [];
        $newEncTimes = [];
        $newDecTimes = [];

        $this->line("Menjalankan 100.000 siklus enkripsi dan dekripsi (mikrodetik)...");
        $bar = $this->output->createProgressBar(100);

        $chunkSize = 1000;
        for ($chunk = 0; $chunk < 100; $chunk++) {
            $startIdx = $chunk * $chunkSize;
            $endIdx = $startIdx + $chunkSize;

            for ($i = $startIdx; $i < $endIdx; $i++) {
                $plain = $plaintexts[$i];
                $oldCt = $oldCiphertexts[$i];
                $newCt = $newCiphertexts[$i];

                // 1. Skema Lama Enkripsi
                $t0 = hrtime(true);
                $resOldEnc = CryptoHelper::encryptFieldLegacy($plain);
                $t1 = hrtime(true);
                $oldEncUs = ($t1 - $t0) / 1000.0;

                // 2. Skema Lama Dekripsi
                $t0 = hrtime(true);
                $resOldDec = CryptoHelper::decryptLegacy($oldCt);
                $t1 = hrtime(true);
                $oldDecUs = ($t1 - $t0) / 1000.0;

                // 3. Skema Baru Enkripsi
                $t0 = hrtime(true);
                $resNewEnc = CryptoHelper::encryptField($plain, 'nip');
                $t1 = hrtime(true);
                $newEncUs = ($t1 - $t0) / 1000.0;

                // 4. Skema Baru Dekripsi
                $t0 = hrtime(true);
                $resNewDec = CryptoHelper::decryptField($newCt, 'nip');
                $t1 = hrtime(true);
                $newDecUs = ($t1 - $t0) / 1000.0;

                $oldEncTimes[] = $oldEncUs;
                $oldDecTimes[] = $oldDecUs;
                $newEncTimes[] = $newEncUs;
                $newDecTimes[] = $newDecUs;

                fputcsv($csvFile, [$i + 1, $oldEncUs, $oldDecUs, $newEncUs, $newDecUs]);
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        fclose($csvFile);

        $this->info("✓ Data mentah operasi kriptografi disimpan di: {$csvPath}");

        // Hitung statistik deskriptif
        $calcStats = function (array $times): array {
            sort($times);
            $n = count($times);
            $min = $times[0];
            $max = $times[$n - 1];
            $sum = array_sum($times);
            $mean = $sum / $n;

            // Median (p50)
            $mid = (int) floor($n / 2);
            $median = ($n % 2 === 0) ? ($times[$mid - 1] + $times[$mid]) / 2.0 : $times[$mid];

            // p95 & p99
            $p95 = $times[(int) floor(0.95 * $n)];
            $p99 = $times[(int) floor(0.99 * $n)];

            // Standard Deviation
            $variance = 0.0;
            foreach ($times as $val) {
                $variance += pow($val - $mean, 2);
            }
            $sd = sqrt($variance / ($n - 1));

            return [
                'count'  => $n,
                'min_us' => round($min, 3),
                'max_us' => round($max, 3),
                'mean_us'=> round($mean, 3),
                'median_us' => round($median, 3),
                'p95_us' => round($p95, 3),
                'p99_us' => round($p99, 3),
                'sd_us'  => round($sd, 3),
            ];
        };

        $summary = [
            'benchmark_metadata' => [
                'iterations' => $iterations,
                'seed'       => $seed,
                'timestamp'  => date('c'),
            ],
            'legacy_cbc' => [
                'encryption' => $calcStats($oldEncTimes),
                'decryption' => $calcStats($oldDecTimes),
            ],
            'new_dae_siv' => [
                'encryption' => $calcStats($newEncTimes),
                'decryption' => $calcStats($newDecTimes),
            ],
        ];

        $summaryJsonPath = $resultsDir . '/crypto_benchmark_summary.json';
        file_put_contents($summaryJsonPath, json_encode($summary, JSON_PRETTY_PRINT));
        $this->info("✓ Ringkasan statistik operasi kriptografi disimpan di: {$summaryJsonPath}");

        $this->table(
            ['Operasi', 'Skema', 'Min (μs)', 'Mean (μs)', 'Median (μs)', 'p95 (μs)', 'p99 (μs)', 'Max (μs)', 'SD (μs)'],
            [
                [
                    'Enkripsi (T_enc)',
                    'Legacy CBC',
                    $summary['legacy_cbc']['encryption']['min_us'],
                    $summary['legacy_cbc']['encryption']['mean_us'],
                    $summary['legacy_cbc']['encryption']['median_us'],
                    $summary['legacy_cbc']['encryption']['p95_us'],
                    $summary['legacy_cbc']['encryption']['p99_us'],
                    $summary['legacy_cbc']['encryption']['max_us'],
                    $summary['legacy_cbc']['encryption']['sd_us'],
                ],
                [
                    'Enkripsi (T_enc)',
                    'New DAE (CTR-SIV)',
                    $summary['new_dae_siv']['encryption']['min_us'],
                    $summary['new_dae_siv']['encryption']['mean_us'],
                    $summary['new_dae_siv']['encryption']['median_us'],
                    $summary['new_dae_siv']['encryption']['p95_us'],
                    $summary['new_dae_siv']['encryption']['p99_us'],
                    $summary['new_dae_siv']['encryption']['max_us'],
                    $summary['new_dae_siv']['encryption']['sd_us'],
                ],
                [
                    'Dekripsi (T_dec)',
                    'Legacy CBC',
                    $summary['legacy_cbc']['decryption']['min_us'],
                    $summary['legacy_cbc']['decryption']['mean_us'],
                    $summary['legacy_cbc']['decryption']['median_us'],
                    $summary['legacy_cbc']['decryption']['p95_us'],
                    $summary['legacy_cbc']['decryption']['p99_us'],
                    $summary['legacy_cbc']['decryption']['max_us'],
                    $summary['legacy_cbc']['decryption']['sd_us'],
                ],
                [
                    'Dekripsi (T_dec)',
                    'New DAE (CTR-SIV)',
                    $summary['new_dae_siv']['decryption']['min_us'],
                    $summary['new_dae_siv']['decryption']['mean_us'],
                    $summary['new_dae_siv']['decryption']['median_us'],
                    $summary['new_dae_siv']['decryption']['p95_us'],
                    $summary['new_dae_siv']['decryption']['p99_us'],
                    $summary['new_dae_siv']['decryption']['max_us'],
                    $summary['new_dae_siv']['decryption']['sd_us'],
                ],
            ]
        );
    }

    /**
     * Bagian 3: Verifikasi Pasca-Migrasi EXPLAIN (ANALYZE, BUFFERS)
     * Menjalankan Query C, D, dan G terhadap database tte_benchmark yang telah dimigrasi ke v1.
     */
    public function verifyPostMigrationExplain(int $seed, string $resultsDir): void
    {
        $this->info("\n--- [BAGIAN 3] Verifikasi Query Pasca-Migrasi (Query C, D, G) ---");
        $db = DB::connection('pgsql_bench');

        if ($db->getDatabaseName() !== 'tte_benchmark') {
            $this->error("ABORT: Verifikasi EXPLAIN hanya boleh dijalankan pada database tte_benchmark!");
            return;
        }

        // Ambil 30 target pencarian acak
        mt_srand($seed);
        $activeRows = $db->table('pendaftaran_tte')
            ->where('status', 'MENUNGGU_VERIFIKASI')
            ->select('id', 'nik')
            ->get()
            ->toArray();

        $rejectedRows = $db->table('pendaftaran_tte')
            ->where('status', 'DITOLAK')
            ->select('id', 'nik')
            ->get()
            ->toArray();

        if (empty($activeRows) || empty($rejectedRows)) {
            $this->error("Tabel pendaftaran_tte di tte_benchmark kosong atau tidak memiliki variasi status!");
            return;
        }

        shuffle($activeRows);
        shuffle($rejectedRows);

        $sampleActive = array_slice($activeRows, 0, 30);
        $sampleRejected = array_slice($rejectedRows, 0, 30);

        // Dekripsi target untuk memastikan kita mengkueri dengan ciphertext v1 baru
        $targetActivePlain = [];
        foreach ($sampleActive as $row) {
            $targetActivePlain[] = CryptoHelper::decryptField($row->nik, 'nik');
        }

        $targetRejectedPlain = [];
        foreach ($sampleRejected as $row) {
            $targetRejectedPlain[] = CryptoHelper::decryptField($row->nik, 'nik');
        }

        // Warm up query cache
        $this->line("Melakukan warm-up cache basis data...");
        for ($i = 0; $i < 5; $i++) {
            $warmCipher = CryptoHelper::encryptField($targetActivePlain[$i], 'nik');
            $db->select("SELECT id FROM pendaftaran_tte WHERE nik = ? AND status = 'MENUNGGU_VERIFIKASI'", [$warmCipher]);
        }

        $queriesToTest = [
            'Query_C' => [
                'name'    => 'Query C (Separated NIK + Single Status)',
                'sql'     => 'SELECT * FROM pendaftaran_tte WHERE nik = ? AND status = ?',
                'targets' => array_map(fn($p) => [CryptoHelper::encryptField($p, 'nik'), 'MENUNGGU_VERIFIKASI'], $targetActivePlain),
            ],
            'Query_D' => [
                'name'    => 'Query D (Separated NIK + Active Status IN)',
                'sql'     => "SELECT * FROM pendaftaran_tte WHERE nik = ? AND status IN ('MENUNGGU_VERIFIKASI', 'DISETUJUI')",
                'targets' => array_map(fn($p) => [CryptoHelper::encryptField($p, 'nik')], $targetActivePlain),
            ],
            'Query_G' => [
                'name'    => 'Query G (Separated NIK + All Status / DITOLAK)',
                'sql'     => 'SELECT * FROM pendaftaran_tte WHERE nik = ? AND status = ?',
                'targets' => array_map(fn($p) => [CryptoHelper::encryptField($p, 'nik'), 'DITOLAK'], $targetRejectedPlain),
            ],
        ];

        $explainResults = [];

        foreach ($queriesToTest as $key => $qMeta) {
            $this->line("Mengevaluasi {$qMeta['name']} (30 iterasi target acak)...");
            $latencies = [];
            $planningTimes = [];
            $plans = [];
            $sharedHit = 0;
            $sharedRead = 0;
            $indexNameUsed = null;

            foreach ($qMeta['targets'] as $idx => $params) {
                $explainSql = "EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) " . $qMeta['sql'];
                $rawResult = $db->select($explainSql, $params);
                $json = json_decode($rawResult[0]->{'QUERY PLAN'}, true)[0];

                $execTime = (float) $json['Execution Time'];
                $planTime = (float) $json['Planning Time'];
                $latencies[] = $execTime;
                $planningTimes[] = $planTime;

                if ($idx === 0) {
                    $plans = $json;
                    $root = $json['Plan'];
                    $sharedHit = $root['Shared Hit Blocks'] ?? 0;
                    $sharedRead = $root['Shared Read Blocks'] ?? 0;
                    $indexNameUsed = $root['Index Name'] ?? ($root['Plans'][0]['Index Name'] ?? 'Index Scan');
                }
            }

            sort($latencies);
            $n = count($latencies);
            $mean = array_sum($latencies) / $n;
            $median = ($n % 2 === 0) ? ($latencies[$n/2 - 1] + $latencies[$n/2]) / 2.0 : $latencies[(int)floor($n/2)];
            $p95 = $latencies[(int) floor(0.95 * $n)];
            $p99 = $latencies[(int) floor(0.99 * $n)];

            $var = 0.0;
            foreach ($latencies as $l) {
                $var += pow($l - $mean, 2);
            }
            $sd = sqrt($var / ($n - 1));

            $explainResults[$key] = [
                'query_label'        => $qMeta['name'],
                'target_count'       => $n,
                'index_used'         => $indexNameUsed,
                'shared_hit_blocks'  => $sharedHit,
                'shared_read_blocks' => $sharedRead,
                'execution_time_ms'  => [
                    'min'    => round($latencies[0], 3),
                    'max'    => round($latencies[$n - 1], 3),
                    'mean'   => round($mean, 3),
                    'median' => round($median, 3),
                    'p95'    => round($p95, 3),
                    'p99'    => round($p99, 3),
                    'sd'     => round($sd, 3),
                ],
                'planning_time_ms'   => [
                    'mean'   => round(array_sum($planningTimes) / $n, 3),
                ],
                'sample_plan_json'   => $plans,
            ];
        }

        $outPath = $resultsDir . '/phase2_post_migration_explain.json';
        file_put_contents($outPath, json_encode($explainResults, JSON_PRETTY_PRINT));
        $this->info("✓ Hasil EXPLAIN pasca-migrasi disimpan di: {$outPath}");

        $this->table(
            ['Query', 'Indeks Digunakan', 'Hit Blocks', 'Read Blocks', 'Min (ms)', 'Mean (ms)', 'p50 (ms)', 'p95 (ms)', 'p99 (ms)', 'SD (ms)'],
            array_map(fn($k, $r) => [
                $k,
                $r['index_used'],
                $r['shared_hit_blocks'],
                $r['shared_read_blocks'],
                $r['execution_time_ms']['min'],
                $r['execution_time_ms']['mean'],
                $r['execution_time_ms']['median'],
                $r['execution_time_ms']['p95'],
                $r['execution_time_ms']['p99'],
                $r['execution_time_ms']['sd'],
            ], array_keys($explainResults), $explainResults)
        );
    }
}
