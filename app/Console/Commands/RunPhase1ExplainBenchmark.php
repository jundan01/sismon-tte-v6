<?php

namespace App\Console\Commands;

use App\Services\Security\CryptoHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RunPhase1ExplainBenchmark extends Command
{
    protected $signature = 'bench:phase1-explain {--records=100000 : Jumlah record sintetis untuk seeding} {--force-reseed : Bersihkan dan isi ulang tabel pendaftaran_tte di benchmark DB} {--reps=5 : Jumlah pengulangan pengukuran per kueri}';
    protected $description = 'Fase 1b: Menjalankan EXPLAIN (ANALYZE, BUFFERS) query pencarian A-H pada container PostgreSQL benchmark terisolasi (tte_postgres_bench)';

    public function handle(): int
    {
        $db = DB::connection('pgsql_bench');

        $this->info("================================================================================");
        $this->info("  FASE 1b: AUDIT PENGGUNAAN INDEKS & PENGUKURAN TOLOK UKUR BASIS DATA (A-H)     ");
        $this->info("  Database Target : " . $db->getDatabaseName() . " (ISOLATED CONTAINER: tte_postgres_bench)");
        $this->info("================================================================================");

        // Pastikan tidak menyentuh database produksi
        if ($db->getDatabaseName() !== 'tte_benchmark') {
            $this->error("ABORT: Perintah ini HANYA boleh dijalankan pada database tte_benchmark di container tte_postgres_bench!");
            return 1;
        }

        // Ambil spesifikasi server PostgreSQL terisolasi
        $pgVersion = $db->selectOne('SELECT version()')->version;
        $sharedBuffers = $db->selectOne('SHOW shared_buffers')->shared_buffers;
        $workMem = $db->selectOne('SHOW work_mem')->work_mem;
        $effectiveCacheSize = $db->selectOne('SHOW effective_cache_size')->effective_cache_size;
        $randomPageCost = $db->selectOne('SHOW random_page_cost')->random_page_cost;

        $pgEnv = [
            'pg_version'           => $pgVersion,
            'shared_buffers'       => $sharedBuffers,
            'work_mem'             => $workMem,
            'effective_cache_size' => $effectiveCacheSize,
            'random_page_cost'     => $randomPageCost,
        ];

        $this->line("Spesifikasi Server PostgreSQL Benchmark:");
        $this->line("  Versi Engine         : {$pgVersion}");
        $this->line("  shared_buffers       : {$sharedBuffers}");
        $this->line("  work_mem             : {$workMem}");
        $this->line("  effective_cache_size : {$effectiveCacheSize}");
        $this->line("  random_page_cost     : {$randomPageCost}");
        $this->newLine();

        $records = (int) $this->option('records');
        $forceReseed = $this->option('force-reseed');
        $reps = max(5, (int) $this->option('reps'));

        $currentCount = $db->table('pendaftaran_tte')->count();
        $this->info("Jumlah data saat ini pada pendaftaran_tte: " . number_format($currentCount, 0, ',', '.'));

        if ($currentCount < $records || $forceReseed) {
            $this->info("[1/4] Menyiapkan data sintetis " . number_format($records, 0, ',', '.') . " baris...");
            
            if ($forceReseed && $currentCount > 0) {
                $this->warn("Membersihkan data lama pendaftaran_tte di tte_benchmark...");
                $db->table('sertifikat_tte')->delete();
                $db->table('pendaftaran_tte')->delete();
            }

            $skpdIds = $db->table('skpd')->pluck('id')->toArray();
            if (empty($skpdIds)) {
                $this->error("Tabel skpd kosong! Jalankan seeder terlebih dahulu.");
                return 1;
            }

            $chunkSize = 1000;
            $chunks = ceil($records / $chunkSize);
            $bar = $this->output->createProgressBar($chunks);
            $bar->start();

            // Distribusi status tepat: 60.000 MENUNGGU_VERIFIKASI, 20.000 DISETUJUI, 20.000 DITOLAK
            for ($c = 0; $c < $chunks; $c++) {
                $rows = [];
                $offset = $c * $chunkSize;
                $limit = min($chunkSize, $records - $offset);

                for ($i = 0; $i < $limit; $i++) {
                    $idx = $offset + $i + 1;
                    $nikPlain = sprintf('3274%012d', $idx);
                    $nipPlain = sprintf('1985%08d%06d', $idx % 100000000, $idx % 1000000);
                    $nikCipher = CryptoHelper::encryptField($nikPlain);
                    $nipCipher = CryptoHelper::encryptField($nipPlain);

                    if ($idx <= 60000) {
                        $status = 'MENUNGGU_VERIFIKASI';
                    } elseif ($idx <= 80000) {
                        $status = 'DISETUJUI';
                    } else {
                        $status = 'DITOLAK';
                    }

                    $rows[] = [
                        'uuid'                     => (string) Str::uuid(),
                        'nomor_referensi'          => sprintf('REF-BENCH-%08d', $idx),
                        'nama_lengkap'             => "Pegawai Simulasi Ke-" . $idx,
                        'nik'                      => $nikCipher,
                        'nip'                      => $nipCipher,
                        'no_hp'                    => '08' . str_pad((string)$idx, 10, '0', STR_PAD_LEFT),
                        'skpd_id'                  => $skpdIds[$idx % count($skpdIds)],
                        'status'                   => $status,
                        'status_memiliki_email'    => false,
                        'is_email_recap_confirmed' => false,
                        'tipe_pegawai'             => 'ASN',
                        'deskripsi'                => "Deskripsi penempatan tugas ke-" . $idx,
                        'created_at'               => now()->subMinutes(100000 - $idx),
                        'updated_at'               => now(),
                    ];
                }

                $db->table('pendaftaran_tte')->insert($rows);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✓ Seeding data pendaftaran_tte tuntas.");
        }

        // Verifikasi distribusi status
        $statusCounts = $db->table('pendaftaran_tte')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
        $this->line("Distribusi Status Aktual:");
        foreach ($statusCounts as $st => $cnt) {
            $this->line("  - {$st}: " . number_format($cnt, 0, ',', '.') . " baris");
        }

        // Jalankan VACUUM ANALYZE agar statistik query planner 100% akurat
        $this->info("[2/4] Menjalankan VACUUM ANALYZE pendaftaran_tte...");
        $db->statement("VACUUM ANALYZE pendaftaran_tte;");
        $this->info("✓ VACUUM ANALYZE tuntas.");

        // Target pencarian aktif (MENUNGGU_VERIFIKASI di index ~30.000)
        $targetActiveRow = $db->table('pendaftaran_tte')
            ->where('status', 'MENUNGGU_VERIFIKASI')
            ->offset(30000)
            ->first();

        // Target pencarian ditolak (DITOLAK di index ~90.000)
        $targetRejectedRow = $db->table('pendaftaran_tte')
            ->where('status', 'DITOLAK')
            ->offset(10000)
            ->first();

        $targetNikCipher = $targetActiveRow->nik;
        $targetNipCipher = $targetActiveRow->nip;
        $targetNikPlain = CryptoHelper::decryptField($targetNikCipher);
        $targetNama = $targetActiveRow->nama_lengkap;
        $targetRef = $targetActiveRow->nomor_referensi;

        $resultsDir = base_path('bench/results');
        if (!is_dir($resultsDir)) {
            mkdir($resultsDir, 0755, true);
        }

        // Simpan konfigurasi server
        file_put_contents($resultsDir . '/phase1_pg_env.json', json_encode($pgEnv, JSON_PRETTY_PRINT));

        $this->info("[3/4] Data target benchmark:");
        $this->line("  ID Target Aktif  : {$targetActiveRow->id}");
        $this->line("  Nama Target      : {$targetNama}");
        $this->line("  NIK Plain Target : {$targetNikPlain}");
        $this->line("  NIK Cipher (b64) : " . substr($targetNikCipher, 0, 32) . "...");
        $this->line("  NIP Cipher (b64) : " . substr($targetNipCipher, 0, 32) . "...");
        $this->line("  Status Target    : {$targetActiveRow->status}");

        $this->info("[4/4] Mengeksekusi 8 skenario kueri (Query A - H) dengan 1x warm-up + {$reps}x repetisi...");

        $escapedNama = addcslashes($targetNama, '%_\\');
        $escapedRef = addcslashes($targetRef, '%_\\');

        $queries = [
            'phase1_query_a_combined' => [
                'id'          => 'Query A',
                'title'       => 'Query A: Jalur Lama (Algoritma 4 Compound OR: ILIKE nama/ref/deskripsi OR nik/nip, filter status)',
                'sql'         => "SELECT * FROM pendaftaran_tte WHERE status = 'MENUNGGU_VERIFIKASI' AND (nama_lengkap ILIKE ? OR nomor_referensi ILIKE ? OR deskripsi ILIKE ? OR nik = ? OR nip = ?) ORDER BY created_at ASC LIMIT 15",
                'bindings'    => ["%{$targetNikPlain}%", "%{$targetNikPlain}%", "%{$targetNikPlain}%", $targetNikCipher, $targetNikCipher],
                'description' => 'Jalur lama compound OR dengan filter status',
            ],
            'phase1_query_b_combined_nostatus' => [
                'id'          => 'Query B',
                'title'       => 'Query B: Jalur Lama Tanpa Filter Status (Compound OR murni teks + cipher)',
                'sql'         => "SELECT * FROM pendaftaran_tte WHERE (nama_lengkap ILIKE ? OR nomor_referensi ILIKE ? OR deskripsi ILIKE ? OR nik = ? OR nip = ?) LIMIT 15",
                'bindings'    => ["%{$targetNikPlain}%", "%{$targetNikPlain}%", "%{$targetNikPlain}%", $targetNikCipher, $targetNikCipher],
                'description' => 'Jalur lama compound OR tanpa filter status',
            ],
            'phase1_query_c_separated_nik_status' => [
                'id'          => 'Query C',
                'title'       => 'Query C: Jalur Terpisah Exact-Match NIK (status = MENUNGGU_VERIFIKASI)',
                'sql'         => "SELECT * FROM pendaftaran_tte WHERE status = 'MENUNGGU_VERIFIKASI' AND nik = ? LIMIT 15",
                'bindings'    => [$targetNikCipher],
                'description' => 'Jalur terpisah exact-match NIK pada data aktif',
            ],
            'phase1_query_d_separated_nik_active' => [
                'id'          => 'Query D',
                'title'       => 'Query D: Jalur Terpisah Exact-Match NIK (status != DITOLAK / Indeks Parsial)',
                'sql'         => "SELECT * FROM pendaftaran_tte WHERE status != 'DITOLAK' AND nik = ? LIMIT 15",
                'bindings'    => [$targetNikCipher],
                'description' => 'Jalur terpisah exact-match NIK dengan predikat status != DITOLAK',
            ],
            'phase1_query_e_separated_nip_status' => [
                'id'          => 'Query E',
                'title'       => 'Query E: Jalur Terpisah Exact-Match NIP (status = MENUNGGU_VERIFIKASI)',
                'sql'         => "SELECT * FROM pendaftaran_tte WHERE status = 'MENUNGGU_VERIFIKASI' AND nip = ? LIMIT 15",
                'bindings'    => [$targetNipCipher],
                'description' => 'Jalur terpisah exact-match NIP 18 digit pada data aktif',
            ],
            'phase1_query_f_separated_text' => [
                'id'          => 'Query F',
                'title'       => 'Query F: Jalur Terpisah Pencarian Teks Murni (ILIKE nama/ref dengan wildcard escaping)',
                'sql'         => "SELECT * FROM pendaftaran_tte WHERE status = 'MENUNGGU_VERIFIKASI' AND (nama_lengkap ILIKE ? OR nomor_referensi ILIKE ?) ORDER BY created_at ASC LIMIT 15",
                'bindings'    => ["%{$escapedNama}%", "%{$escapedRef}%"],
                'description' => 'Jalur terpisah pencarian teks dengan sanitasi wildcard',
            ],
            'phase1_query_g_separated_nik_all_status' => [
                'id'          => 'Query G',
                'title'       => 'Query G: Jalur Terpisah Exact-Match NIK Tanpa Filter Status (Mencakup Semua Status)',
                'sql'         => "SELECT * FROM pendaftaran_tte WHERE nik = ? LIMIT 15",
                'bindings'    => [$targetNikCipher],
                'description' => 'Jalur terpisah exact-match NIK tanpa predikat status (memanfaatkan idx_pendaftaran_nik_all)',
            ],
            'phase1_query_h_combined_nik_nip' => [
                'id'          => 'Query H',
                'title'       => 'Query H: Pencarian Gabungan Dua Kolom Terenkripsi (nik = ? OR nip = ?)',
                'sql'         => "SELECT * FROM pendaftaran_tte WHERE (nik = ? OR nip = ?) LIMIT 15",
                'bindings'    => [$targetNikCipher, $targetNipCipher],
                'description' => 'Pencarian majemuk dua identitas terenkripsi (menguji BitmapOr)',
            ],
        ];

        $summaryTable = [];
        $textReport = "HASIL EXPLAIN (ANALYZE, BUFFERS) - SISMON TTE POSTGRESQL 16 (CONTAINER TERISOLASI)\n";
        $textReport .= "Tanggal Pengujian: " . now()->toIso8601String() . "\n";
        $textReport .= "Container: tte_postgres_bench (Port 5433 host / 5432 internal)\n";
        $textReport .= "PostgreSQL Version: {$pgVersion}\n";
        $textReport .= "Server Config: shared_buffers={$sharedBuffers}, work_mem={$workMem}, effective_cache_size={$effectiveCacheSize}, random_page_cost={$randomPageCost}\n";
        $textReport .= "Dataset: " . number_format($records, 0, ',', '.') . " baris pendaftaran_tte (60.000 MENUNGGU_VERIFIKASI, 20.000 DISETUJUI, 20.000 DITOLAK)\n";
        $textReport .= str_repeat("=", 100) . "\n\n";

        foreach ($queries as $key => $qInfo) {
            $this->line("Menjalankan: " . $qInfo['title']);

            // 1. Eksekusi 1x Warm-Up Run
            $db->select("EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) " . $qInfo['sql'], $qInfo['bindings']);

            // 2. Eksekusi Repetisi Pengukuran
            $execTimes = [];
            $planTimes = [];
            $plansJson = [];
            $fullPlansText = [];

            for ($r = 0; $r < $reps; $r++) {
                $rawJson = $db->select("EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) " . $qInfo['sql'], $qInfo['bindings']);
                $parsed = json_decode($rawJson[0]->{'QUERY PLAN'}, true)[0];
                $plansJson[] = $parsed;
                $execTimes[] = (float) $parsed['Execution Time'];
                $planTimes[] = (float) $parsed['Planning Time'];
            }

            // Dapatkan text format representatif
            $rawText = $db->select("EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) " . $qInfo['sql'], $qInfo['bindings']);
            $textLines = array_map(fn($row) => $row->{'QUERY PLAN'}, $rawText);
            $fullPlanText = implode("\n", $textLines);

            // Hitung statistik (Median, Mean, Min, Max)
            $sortedExec = $execTimes;
            sort($sortedExec);
            $medianIndex = (int) floor($reps / 2);
            $medianExec = $sortedExec[$medianIndex];
            $meanExec = array_sum($execTimes) / count($execTimes);
            $minExec = min($execTimes);
            $maxExec = max($execTimes);

            $sortedPlan = $planTimes;
            sort($sortedPlan);
            $medianPlan = $sortedPlan[$medianIndex];

            // Cari plan JSON yang paling mendekati median untuk representasi raw file
            $representativePlan = $plansJson[0];
            $closestDiff = PHP_FLOAT_MAX;
            foreach ($plansJson as $p) {
                $diff = abs((float)$p['Execution Time'] - $medianExec);
                if ($diff < $closestDiff) {
                    $closestDiff = $diff;
                    $representativePlan = $p;
                }
            }

            // Simpan berkas JSON mentah
            $jsonFilePath = $resultsDir . "/{$key}_explain.json";
            file_put_contents($jsonFilePath, json_encode([$representativePlan], JSON_PRETTY_PRINT));

            // Parse scan node dan metrik buffer
            $rootPlan = $representativePlan['Plan'];
            $scanNode = $this->findScanNode($rootPlan);
            $primaryScan = $scanNode['Node Type'] ?? $rootPlan['Node Type'];
            $indexUsed = $this->extractIndexName($rootPlan);

            $sharedHit = $this->sumSharedHit($rootPlan);
            $sharedRead = $this->sumSharedRead($rootPlan);

            $textReport .= "### [" . $qInfo['id'] . "] " . $qInfo['title'] . "\n";
            $textReport .= "Deskripsi: " . $qInfo['description'] . "\n";
            $textReport .= "SQL: " . $qInfo['sql'] . "\n";
            $textReport .= "Repetisi: {$reps}x | Min: " . number_format($minExec, 3) . " ms | Max: " . number_format($maxExec, 3) . " ms | Mean: " . number_format($meanExec, 3) . " ms | Median: " . number_format($medianExec, 3) . " ms\n";
            $textReport .= "Buffers (Median Run): Shared Hit={$sharedHit}, Shared Read={$sharedRead}\n";
            $textReport .= "PLAN:\n" . $fullPlanText . "\n\n" . str_repeat("-", 100) . "\n\n";

            $summaryTable[] = [
                'Query ID'        => $qInfo['id'],
                'Scenario Key'    => $key,
                'Primary Scan'    => $primaryScan,
                'Index Used'      => $indexUsed,
                'Median (ms)'     => number_format($medianExec, 3),
                'Mean (ms)'       => number_format($meanExec, 3),
                'Min (ms)'        => number_format($minExec, 3),
                'Max (ms)'        => number_format($maxExec, 3),
                'Plan Time (ms)'  => number_format($medianPlan, 3),
                'Shared Hit'      => $sharedHit,
                'Shared Read'     => $sharedRead,
            ];
        }

        // Simpan file teks utuh
        file_put_contents($resultsDir . "/phase1_explain_plans.txt", $textReport);

        // Simpan file JSON ringkasan
        file_put_contents($resultsDir . "/phase1_summary.json", json_encode([
            'pg_environment' => $pgEnv,
            'dataset_records'=> $records,
            'summary_table'  => $summaryTable,
        ], JSON_PRETTY_PRINT));

        $this->newLine();
        $this->table(
            ['Query ID', 'Primary Scan', 'Index Used', 'Median (ms)', 'Mean (ms)', 'Min (ms)', 'Max (ms)', 'Shared Hit', 'Shared Read'],
            array_map(function ($row) {
                return [
                    $row['Query ID'],
                    $row['Primary Scan'],
                    $row['Index Used'],
                    $row['Median (ms)'],
                    $row['Mean (ms)'],
                    $row['Min (ms)'],
                    $row['Max (ms)'],
                    $row['Shared Hit'],
                    $row['Shared Read'],
                ];
            }, $summaryTable)
        );

        $this->info("✓ Seluruh output mentah dan laporan tolok ukur berhasil disimpan di: {$resultsDir}/");
        return 0;
    }

    private function findScanNode(array $plan): array
    {
        if (isset($plan['Node Type']) && (str_contains($plan['Node Type'], 'Scan') || str_contains($plan['Node Type'], 'Index'))) {
            if ($plan['Node Type'] !== 'Bitmap Index Scan') {
                return $plan;
            }
        }

        if (isset($plan['Plans']) && is_array($plan['Plans'])) {
            foreach ($plan['Plans'] as $subPlan) {
                $found = $this->findScanNode($subPlan);
                if (!empty($found)) {
                    return $found;
                }
            }
        }

        return $plan;
    }

    private function extractIndexName(array $plan): string
    {
        $indexes = [];
        $this->collectIndexNames($plan, $indexes);
        if (empty($indexes)) {
            return 'None (Seq Scan)';
        }
        return implode(' & ', array_unique($indexes));
    }

    private function collectIndexNames(array $plan, array &$indexes): void
    {
        if (isset($plan['Index Name'])) {
            $indexes[] = $plan['Index Name'];
        }
        if (isset($plan['Plans']) && is_array($plan['Plans'])) {
            foreach ($plan['Plans'] as $subPlan) {
                $this->collectIndexNames($subPlan, $indexes);
            }
        }
    }

    private function sumSharedHit(array $plan): int
    {
        $hit = $plan['Shared Hit Blocks'] ?? 0;
        if (isset($plan['Plans']) && is_array($plan['Plans'])) {
            foreach ($plan['Plans'] as $subPlan) {
                $hit += $this->sumSharedHit($subPlan);
            }
        }
        return $hit;
    }

    private function sumSharedRead(array $plan): int
    {
        $read = $plan['Shared Read Blocks'] ?? 0;
        if (isset($plan['Plans']) && is_array($plan['Plans'])) {
            foreach ($plan['Plans'] as $subPlan) {
                $read += $this->sumSharedRead($subPlan);
            }
        }
        return $read;
    }
}
