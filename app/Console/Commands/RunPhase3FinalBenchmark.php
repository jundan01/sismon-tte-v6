<?php

namespace App\Console\Commands;

use App\Benchmark\Security\AesGcmHelper;
use App\Benchmark\Security\BlindIndexService;
use App\Services\Security\CryptoHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class RunPhase3FinalBenchmark extends Command
{
    protected $signature = 'bench:phase3-benchmark
                            {--seed-datasets : Buat dan lakukan seeding pada dataset 1k, 10k, dan 100k}
                            {--run-search : Eksekusi benchmark pencarian terisolasi per proses CLI (Skenario A-D)}
                            {--run-write : Eksekusi benchmark write overhead (1.000 INSERT) dan catat ukuran storage}
                            {--all : Jalankan seluruh tahapan Fase 3 end-to-end}
                            {--seed=20261116 : Seed bilangan acak keterulangan}';

    protected $description = 'Fase 3: Tolok ukur komparatif pencarian multi-skala (1k-100k), write overhead, dan physical storage size';

    public function handle(): int
    {
        $db = DB::connection('pgsql_bench');

        $this->info("================================================================================");
        $this->info("  FASE 3: BENCHMARK KOMPARATIF FINAL & PENGUKURAN STORAGE BASIS DATA ");
        $this->info("  Database Target : " . $db->getDatabaseName() . " (tte_postgres_bench)");
        $this->info("================================================================================");

        if ($db->getDatabaseName() !== 'tte_benchmark') {
            $this->error("CRITICAL SAFETY ABORT: Perintah ini HANYA boleh dieksekusi pada database tte_benchmark!");
            return 1;
        }

        $seed = (int) $this->option('seed');
        $runAll = (bool) $this->option('all');
        $seedDatasets = $runAll || (bool) $this->option('seed-datasets');
        $runSearch = $runAll || (bool) $this->option('run-search');
        $runWrite = $runAll || (bool) $this->option('run-write');

        if (!$seedDatasets && !$runSearch && !$runWrite) {
            $seedDatasets = true;
            $runSearch = true;
            $runWrite = true;
        }

        $resultsDir = base_path('bench/results');
        if (!is_dir($resultsDir)) {
            mkdir($resultsDir, 0755, true);
        }

        // Tahap 1: Seeding Dataset Multi-Skala (1k, 10k, 100k)
        if ($seedDatasets) {
            $this->seedBenchmarkDatasets($seed, $db);
        }

        // Tahap 2: Benchmark Pencarian Terisolasi per Proses CLI (Task 1)
        if ($runSearch) {
            $this->executeSearchBenchmark($seed, $db, $resultsDir);
        }

        // Tahap 3: Benchmark Write Overhead & Ukuran Storage (Task 2)
        if ($runWrite) {
            $this->executeWriteAndStorageBenchmark($seed, $db, $resultsDir);
        }

        $this->info("\n✓ Seluruh pengujian Fase 3 telah berhasil diselesaikan.");
        return 0;
    }

    /**
     * Menyiapkan tabel dan mengisi dataset multi-skala (1k, 10k, 100k)
     */
    protected function seedBenchmarkDatasets(int $seed, $db): void
    {
        $this->info("\n--- [TAHAP 1] Penyiapan & Seeding Dataset Multi-Skala (1k, 10k, 100k) ---");

        $scales = [
            '1k'   => 1000,
            '10k'  => 10000,
            '100k' => 100000,
        ];

        foreach ($scales as $scale => $recordCount) {
            $this->line("\n[Skala {$scale}] Menyiapkan tabel untuk N = " . number_format($recordCount, 0, ',', '.') . " baris...");

            // 1. Buat Tabel
            $db->statement("DROP TABLE IF EXISTS bench_plain_{$scale} CASCADE;");
            $db->statement("DROP TABLE IF EXISTS bench_dae_{$scale} CASCADE;");
            $db->statement("DROP TABLE IF EXISTS bench_bidx_{$scale} CASCADE;");

            $db->statement("
                CREATE TABLE bench_plain_{$scale} (
                    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    nama_lengkap VARCHAR(255) NOT NULL,
                    nik VARCHAR(255) NOT NULL,
                    status VARCHAR(30) NOT NULL DEFAULT 'MENUNGGU_VERIFIKASI',
                    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
                );
            ");

            $db->statement("
                CREATE TABLE bench_dae_{$scale} (
                    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    nama_lengkap VARCHAR(255) NOT NULL,
                    nik VARCHAR(255) NOT NULL,
                    status VARCHAR(30) NOT NULL DEFAULT 'MENUNGGU_VERIFIKASI',
                    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
                );
            ");

            $db->statement("
                CREATE TABLE bench_bidx_{$scale} (
                    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    nama_lengkap VARCHAR(255) NOT NULL,
                    nik_gcm TEXT NOT NULL,
                    nik_bidx VARCHAR(64) NOT NULL,
                    status VARCHAR(30) NOT NULL DEFAULT 'MENUNGGU_VERIFIKASI',
                    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
                );
            ");

            // 2. Bangkitkan Data Sintetis Realistis
            mt_srand($seed);

            $cirebonKec = ['327401', '327402', '327403', '327404', '327405'];
            $statuses = array_merge(
                array_fill(0, (int) round($recordCount * 0.60), 'MENUNGGU_VERIFIKASI'),
                array_fill(0, (int) round($recordCount * 0.20), 'DISETUJUI'),
                array_fill(0, (int) round($recordCount * 0.20), 'DITOLAK')
            );
            // Sesuaikan panjang array jika ada pembulatan
            while (count($statuses) < $recordCount) {
                $statuses[] = 'MENUNGGU_VERIFIKASI';
            }
            while (count($statuses) > $recordCount) {
                array_pop($statuses);
            }

            // Fisher-Yates shuffle deterministik
            for ($i = count($statuses) - 1; $i > 0; $i--) {
                $j = mt_rand(0, $i);
                $tmp = $statuses[$i];
                $statuses[$i] = $statuses[$j];
                $statuses[$j] = $tmp;
            }

            $chunkSize = 2500;
            $chunks = ceil($recordCount / $chunkSize);
            $bar = $this->output->createProgressBar($chunks);
            $nikSeqMap = [];

            for ($c = 0; $c < $chunks; $c++) {
                $offset = $c * $chunkSize;
                $limit = min($chunkSize, $recordCount - $offset);

                $rowsPlain = [];
                $rowsDae = [];
                $rowsBidx = [];

                for ($i = 0; $i < $limit; $i++) {
                    $idx = $offset + $i + 1;
                    $kec = $cirebonKec[$idx % count($cirebonKec)];
                    $isFemale = ($idx % 2 === 0);
                    $dayInt = ($idx % 28) + 1 + ($isFemale ? 40 : 0);
                    $nikDay = str_pad((string)$dayInt, 2, '0', STR_PAD_LEFT);
                    $nikMonth = str_pad((string)(($idx % 12) + 1), 2, '0', STR_PAD_LEFT);
                    $yearInt = 1970 + ($idx % 30);
                    $nikYear2 = substr((string)$yearInt, 2, 2);
                    $prefix = "{$kec}{$nikDay}{$nikMonth}{$nikYear2}";
                    $nikSeqMap[$prefix] = ($nikSeqMap[$prefix] ?? 0) + 1;
                    $nikSeq = sprintf('%04d', $nikSeqMap[$prefix]);
                    $nikPlain = "{$prefix}{$nikSeq}";

                    $nikDae = CryptoHelper::encryptField($nikPlain, 'nik');
                    $nikGcm = AesGcmHelper::encrypt($nikPlain);
                    $nikBidx = BlindIndexService::generate($nikPlain);

                    $name = "Pegawai Skala {$scale} Ke-{$idx}";
                    $st = $statuses[$idx - 1];

                    $rowsPlain[] = [
                        'nama_lengkap' => $name,
                        'nik'          => $nikPlain,
                        'status'       => $st,
                    ];

                    $rowsDae[] = [
                        'nama_lengkap' => $name,
                        'nik'          => $nikDae,
                        'status'       => $st,
                    ];

                    $rowsBidx[] = [
                        'nama_lengkap' => $name,
                        'nik_gcm'      => $nikGcm,
                        'nik_bidx'     => $nikBidx,
                        'status'       => $st,
                    ];
                }

                $db->table("bench_plain_{$scale}")->insert($rowsPlain);
                $db->table("bench_dae_{$scale}")->insert($rowsDae);
                $db->table("bench_bidx_{$scale}")->insert($rowsBidx);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            // 3. Bangun Indeks Parsial Unik
            $this->line("  -> Membangun indeks B-Tree...");
            $db->statement("CREATE UNIQUE INDEX idx_plain_{$scale}_nik_active ON bench_plain_{$scale} (nik) WHERE status <> 'DITOLAK';");
            $db->statement("CREATE UNIQUE INDEX idx_dae_{$scale}_nik_active ON bench_dae_{$scale} (nik) WHERE status <> 'DITOLAK';");
            $db->statement("CREATE UNIQUE INDEX idx_bidx_{$scale}_nik_active ON bench_bidx_{$scale} (nik_bidx) WHERE status <> 'DITOLAK';");

            // 4. Update Statistik Planner
            $db->statement("VACUUM ANALYZE bench_plain_{$scale};");
            $db->statement("VACUUM ANALYZE bench_dae_{$scale};");
            $db->statement("VACUUM ANALYZE bench_bidx_{$scale};");

            $this->info("  ✓ Tabel dan indeks skala {$scale} tuntas disiapkan.");
        }
    }

    /**
     * Menjalankan Benchmark Pencarian Terisolasi per Proses CLI (Task 1)
     */
    protected function executeSearchBenchmark(int $seed, $db, string $resultsDir): void
    {
        $this->info("\n--- [TAHAP 2] Benchmark Pencarian Terisolasi per Proses CLI (Task 1) ---");

        $csvPath = $resultsDir . '/phase3_search_raw.csv';
        $csvFile = fopen($csvPath, 'w');
        fputcsv($csvFile, [
            'n_size',
            'scenario',
            'trial',
            'target_id',
            'wall_ms',
            'php_cpu_ms',
            'peak_mem_mb',
            'rows_returned'
        ]);

        $scales = [
            '1k'   => 1000,
            '10k'  => 10000,
            '100k' => 100000,
        ];

        // Counterbalanced scenario order per scale to prevent systematic database cache bias
        $scenarioOrders = [
            '1k'   => ['B', 'D', 'A', 'C'],
            '10k'  => ['C', 'A', 'D', 'B'],
            '100k' => ['D', 'B', 'C', 'A'],
        ];

        $allRawData = [];

        foreach ($scales as $scale => $recordCount) {
            $this->info("\n>>> Menguji Skala N = " . number_format($recordCount, 0, ',', '.') . " ({$scale}) <<<");

            // Ambil 30 target NIK aktif acak menggunakan seed deterministik
            mt_srand($seed);
            $activeRows = $db->table("bench_plain_{$scale}")
                ->where('status', 'MENUNGGU_VERIFIKASI')
                ->pluck('nik')
                ->toArray();

            shuffle($activeRows);
            $sample30 = array_slice($activeRows, 0, 30);
            $sample10 = array_slice($sample30, 0, 10);

            $order = $scenarioOrders[$scale];
            $this->line("Urutan Skenario (Counterbalanced): " . implode(' -> ', $order));

            foreach ($order as $scenario) {
                // Untuk Skenario C pada N=100.000, batasi ke 10 target agar tidak timeout
                $targetsToUse = ($scale === '100k' && $scenario === 'C') ? $sample10 : $sample30;
                $targetCount = count($targetsToUse);

                $scenarioDesc = match($scenario) {
                    'A' => 'Skenario A (Plaintext + B-Tree)',
                    'B' => 'Skenario B (New DAE v1 CTR-SIV + B-Tree)',
                    'C' => 'Skenario C (AES-256-GCM + In-Memory Full Decryption Scan)',
                    'D' => 'Skenario D (AES-256-GCM + Blind Index HMAC)',
                };

                $this->line("  -> Menjalankan {$scenarioDesc} ({$targetCount} percobaan terisolasi)...");

                // Jalankan proses CLI terisolasi
                $subResults = $this->runIsolatedWorker($scale, $scenario, $targetsToUse);

                if (empty($subResults)) {
                    $this->error("Gagal mendapatkan output dari worker untuk Skenario {$scenario} pada skala {$scale}!");
                    continue;
                }

                foreach ($subResults as $row) {
                    fputcsv($csvFile, [
                        $recordCount,
                        $scenario,
                        $row['trial'],
                        $row['target_id'],
                        $row['wall_ms'],
                        $row['php_cpu_ms'],
                        $row['peak_mem_mb'],
                        $row['rows_returned'],
                    ]);

                    $allRawData[$recordCount][$scenario][] = $row;
                }
            }
        }

        fclose($csvFile);
        $this->info("\n✓ Seluruh data mentah pencarian disimpan di: {$csvPath}");

        // Hitung dan Tampilkan Tabel Statistik (p50, Mean, SD, p95)
        $this->displaySearchSummaryTable($allRawData);
    }

    /**
     * Memanggil artisan bench:phase3-worker di proses PHP baru yang bersih
     */
    protected function runIsolatedWorker(string $scale, string $scenario, array $targets): array
    {
        $cmd = [
            PHP_BINARY,
            base_path('artisan'),
            'bench:phase3-worker',
            "--scale={$scale}",
            "--scenario={$scenario}",
            "--targets=" . json_encode($targets),
        ];

        $process = new Process($cmd);
        $process->setTimeout(600); // 10 menit batas waktu
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error("Subproses gagal: " . $process->getErrorOutput());
            return [];
        }

        $output = $process->getOutput();
        if (preg_match('/__WORKER_RESULT_START__(.*?)__WORKER_RESULT_END__/s', $output, $matches)) {
            return json_decode($matches[1], true) ?: [];
        }

        return [];
    }

    /**
     * Menghitung statistik dan menampilkan ringkasan pencarian
     */
    protected function displaySearchSummaryTable(array $allRawData): void
    {
        $this->info("\n================================================================================");
        $this->info("                 RINGKASAN STATISTIK PENCARIAN (FASE 3)                         ");
        $this->info("================================================================================");

        $calcMetrics = function(array $values): array {
            sort($values);
            $n = count($values);
            $mean = array_sum($values) / $n;
            $mid = (int) floor($n / 2);
            $median = ($n % 2 === 0) ? ($values[$mid - 1] + $values[$mid]) / 2.0 : $values[$mid];
            $p95 = $values[(int) floor(0.95 * $n)];
            $var = 0.0;
            foreach ($values as $v) {
                $var += pow($v - $mean, 2);
            }
            $sd = ($n > 1) ? sqrt($var / ($n - 1)) : 0.0;
            return [
                'mean'   => round($mean, 3),
                'median' => round($median, 3),
                'p95'    => round($p95, 3),
                'sd'     => round($sd, 3),
            ];
        };

        $tableRows = [];

        foreach ($allRawData as $nSize => $scenarios) {
            foreach (['A', 'B', 'C', 'D'] as $sc) {
                if (!isset($scenarios[$sc])) {
                    continue;
                }
                $rows = $scenarios[$sc];
                $wallTimes = array_column($rows, 'wall_ms');
                $cpuTimes = array_column($rows, 'php_cpu_ms');
                $peakMems = array_column($rows, 'peak_mem_mb');

                $wallStat = $calcMetrics($wallTimes);
                $cpuStat = $calcMetrics($cpuTimes);
                $memStat = $calcMetrics($peakMems);

                $label = match($sc) {
                    'A' => 'A: Plaintext + B-Tree',
                    'B' => 'B: DAE v1 (CTR-SIV) + B-Tree',
                    'C' => 'C: AES-GCM + In-Memory Decrypt',
                    'D' => 'D: AES-GCM + Blind Index',
                };

                $tableRows[] = [
                    number_format($nSize),
                    $label,
                    count($rows),
                    $wallStat['median'] . ' ms',
                    $wallStat['mean'] . ' ms',
                    $wallStat['p95'] . ' ms',
                    $wallStat['sd'] . ' ms',
                    $cpuStat['mean'] . ' ms',
                    $memStat['median'] . ' MB',
                ];
            }
        }

        $this->table(
            ['N', 'Skenario', 'Iterasi', 'Median (p50)', 'Mean', 'p95', 'SD', 'CPU Mean', 'Peak Mem (p50)'],
            $tableRows
        );
    }

    /**
     * Menjalankan Benchmark Write Overhead (1.000 INSERT) & Pengukuran Storage (Task 2)
     */
    protected function executeWriteAndStorageBenchmark(int $seed, $db, string $resultsDir): void
    {
        $this->info("\n--- [TAHAP 3] Benchmark Biaya Penulisan (Write Overhead) & Ukuran Storage (Task 2) ---");

        // 1. Benchmark Write Overhead (1.000 INSERT baru pada N=100.000)
        $this->line("Mempersiapkan 1.000 rekaman baru untuk pengujian Write Overhead pada N = 100.000...");
        $csvPath = $resultsDir . '/phase3_write_raw.csv';
        $csvFile = fopen($csvPath, 'w');
        fputcsv($csvFile, ['scheme', 'insert_index', 'wall_ms', 'php_cpu_ms']);

        mt_srand($seed + 9999);
        $insertCount = 1000;

        $newRecords = [];
        for ($i = 0; $i < $insertCount; $i++) {
            $newRecords[] = [
                'nama'  => "Pegawai Baru Write Bench {$i}",
                'nik'   => sprintf('327499%010d', $i + 1),
                'status'=> ($i % 5 === 0) ? 'DITOLAK' : 'MENUNGGU_VERIFIKASI',
            ];
        }

        $schemes = ['plaintext', 'dae_v1', 'blind_index'];
        $writeStats = [];

        foreach ($schemes as $scheme) {
            $this->line("  -> Mengukur biaya INSERT 1.000 baris [Skema: {$scheme}]...");
            $wallTimes = [];
            $cpuTimes = [];

            $bar = $this->output->createProgressBar($insertCount);

            for ($i = 0; $i < $insertCount; $i++) {
                $rec = $newRecords[$i];

                $cpuStart = getrusage();
                $t0 = hrtime(true);

                if ($scheme === 'plaintext') {
                    // Plaintext: insert langsung
                    $db->statement(
                        "INSERT INTO bench_plain_100k (nama_lengkap, nik, status) VALUES (?, ?, ?)",
                        [$rec['nama'], $rec['nik'], $rec['status']]
                    );
                } elseif ($scheme === 'dae_v1') {
                    // DAE v1: enkripsi lokal DAE v1 + insert
                    $nikCipher = CryptoHelper::encryptField($rec['nik'], 'nik');
                    $db->statement(
                        "INSERT INTO bench_dae_100k (nama_lengkap, nik, status) VALUES (?, ?, ?)",
                        [$rec['nama'], $nikCipher, $rec['status']]
                    );
                } elseif ($scheme === 'blind_index') {
                    // Blind Index: enkripsi AES-GCM + hitung HMAC blind index + insert
                    $nikGcm = AesGcmHelper::encrypt($rec['nik']);
                    $nikBidx = BlindIndexService::generate($rec['nik']);
                    $db->statement(
                        "INSERT INTO bench_bidx_100k (nama_lengkap, nik_gcm, nik_bidx, status) VALUES (?, ?, ?, ?)",
                        [$rec['nama'], $nikGcm, $nikBidx, $rec['status']]
                    );
                }

                $t1 = hrtime(true);
                $cpuEnd = getrusage();

                $wallMs = round(($t1 - $t0) / 1e6, 3);
                $cpuMs = round(
                    ((($cpuEnd['ru_utime.tv_sec'] - $cpuStart['ru_utime.tv_sec']) + ($cpuEnd['ru_stime.tv_sec'] - $cpuStart['ru_stime.tv_sec'])) * 1000.0)
                    + ((($cpuEnd['ru_utime.tv_usec'] - $cpuStart['ru_utime.tv_usec']) + ($cpuEnd['ru_stime.tv_usec'] - $cpuStart['ru_stime.tv_usec'])) / 1000.0),
                    3
                );

                $wallTimes[] = $wallMs;
                $cpuTimes[] = $cpuMs;

                fputcsv($csvFile, [$scheme, $i + 1, $wallMs, $cpuMs]);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            // Hitung statistik write
            sort($wallTimes);
            $n = count($wallTimes);
            $mean = array_sum($wallTimes) / $n;
            $mid = (int) floor($n / 2);
            $median = ($n % 2 === 0) ? ($wallTimes[$mid - 1] + $wallTimes[$mid]) / 2.0 : $wallTimes[$mid];
            $p95 = $wallTimes[(int) floor(0.95 * $n)];
            $p99 = $wallTimes[(int) floor(0.99 * $n)];

            $var = 0.0;
            foreach ($wallTimes as $w) {
                $var += pow($w - $mean, 2);
            }
            $sd = sqrt($var / ($n - 1));

            $writeStats[$scheme] = [
                'count'     => $n,
                'min_ms'    => round($wallTimes[0], 3),
                'mean_ms'   => round($mean, 3),
                'median_ms' => round($median, 3),
                'p95_ms'    => round($p95, 3),
                'p99_ms'    => round($p99, 3),
                'max_ms'    => round($wallTimes[$n - 1], 3),
                'sd_ms'     => round($sd, 3),
            ];
        }

        fclose($csvFile);
        $this->info("✓ Data mentah operasi INSERT disimpan di: {$csvPath}");

        $this->table(
            ['Skema', 'Operasi', 'Min (ms)', 'Mean (ms)', 'Median (ms)', 'p95 (ms)', 'p99 (ms)', 'Max (ms)', 'SD (ms)'],
            array_map(fn($k, $v) => [
                strtoupper($k),
                $v['count'] . ' INSERTs',
                $v['min_ms'],
                $v['mean_ms'],
                $v['median_ms'],
                $v['p95_ms'],
                $v['p99_ms'],
                $v['max_ms'],
                $v['sd_ms'],
            ], array_keys($writeStats), $writeStats)
        );

        // 2. Pengukuran Ukuran Fisik Storage (pg_relation_size)
        $this->info("\n--- Pengukuran Ukuran Fisik Storage (pg_relation_size pada N = 100.000) ---");

        $storageQueries = [
            'plaintext' => [
                'table' => 'bench_plain_100k',
                'index' => 'idx_plain_100k_nik_active',
            ],
            'dae_v1' => [
                'table' => 'bench_dae_100k',
                'index' => 'idx_dae_100k_nik_active',
            ],
            'blind_index' => [
                'table' => 'bench_bidx_100k',
                'index' => 'idx_bidx_100k_nik_active',
            ],
        ];

        $storageData = [
            'measured_at'      => date('c'),
            'database'         => $db->getDatabaseName(),
            'dataset_records'  => 100000,
            'schemes'          => [],
        ];

        foreach ($storageQueries as $key => $meta) {
            $tableBytes = (int) $db->selectOne("SELECT pg_relation_size(?) as sz", [$meta['table']])->sz;
            $tableTotalBytes = (int) $db->selectOne("SELECT pg_total_relation_size(?) as sz", [$meta['table']])->sz;
            $indexBytes = (int) $db->selectOne("SELECT pg_relation_size(?) as sz", [$meta['index']])->sz;

            $storageData['schemes'][$key] = [
                'table_name'        => $meta['table'],
                'table_heap_bytes'  => $tableBytes,
                'table_heap_mb'     => round($tableBytes / (1024 * 1024), 2),
                'index_name'        => $meta['index'],
                'index_bytes'       => $indexBytes,
                'index_mb'          => round($indexBytes / (1024 * 1024), 2),
                'total_relation_mb' => round($tableTotalBytes / (1024 * 1024), 2),
            ];
        }

        $storageJsonPath = $resultsDir . '/phase3_storage_sizes.json';
        file_put_contents($storageJsonPath, json_encode($storageData, JSON_PRETTY_PRINT));
        $this->info("✓ Data ukuran fisik storage disimpan di: {$storageJsonPath}");

        $this->table(
            ['Skema Arsitektur', 'Tabel (Heap)', 'Ukuran Tabel (MB)', 'Indeks B-Tree', 'Ukuran Indeks (MB)', 'Total Relasi (MB)'],
            array_map(fn($k, $v) => [
                strtoupper($k),
                $v['table_name'],
                $v['table_heap_mb'] . ' MB',
                $v['index_name'],
                $v['index_mb'] . ' MB',
                $v['total_relation_mb'] . ' MB',
            ], array_keys($storageData['schemes']), $storageData['schemes'])
        );
    }
}
