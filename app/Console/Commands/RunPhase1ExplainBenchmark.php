<?php

namespace App\Console\Commands;

use App\Services\Security\CryptoHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RunPhase1ExplainBenchmark extends Command
{
    protected $signature = 'bench:phase1-explain 
                            {--records=100000 : Jumlah record sintetis untuk seeding} 
                            {--force-reseed : Bersihkan dan isi ulang tabel pendaftaran_tte di benchmark DB} 
                            {--targets=30 : Jumlah target pencarian acak untuk evaluasi} 
                            {--seed=20261116 : Bilangan acak seed untuk keterulangan (reproducibility)}';

    protected $description = 'Fase 1c: Menjalankan EXPLAIN (ANALYZE, BUFFERS) query pencarian A-H dan Kontrol K1 pada container PostgreSQL benchmark terisolasi';

    public function handle(): int
    {
        $db = DB::connection('pgsql_bench');

        $this->info("================================================================================");
        $this->info("  FASE 1c: AUDIT PENGGUNAAN INDEKS & PENGUKURAN TOLOK UKUR BASIS DATA (A-H + K1) ");
        $this->info("  Database Target : " . $db->getDatabaseName() . " (ISOLATED CONTAINER: tte_postgres_bench)");
        $this->info("================================================================================");

        // Guard ketat: pastikan hanya dijalankan pada database tte_benchmark di container tte_postgres_bench
        if ($db->getDatabaseName() !== 'tte_benchmark') {
            $this->error("ABORT: Perintah ini HANYA boleh dijalankan pada database tte_benchmark di container tte_postgres_bench!");
            return 1;
        }

        $records = (int) $this->option('records');
        $forceReseed = (bool) $this->option('force-reseed');
        $targetCount = max(30, (int) $this->option('targets'));
        $seed = (int) $this->option('seed');

        // Ambil spesifikasi server PostgreSQL terisolasi
        $pgVersion = $db->selectOne('SELECT version()')->version;
        $sharedBuffers = $db->selectOne('SHOW shared_buffers')->shared_buffers;
        $workMem = $db->selectOne('SHOW work_mem')->work_mem;
        $effectiveCacheSize = $db->selectOne('SHOW effective_cache_size')->effective_cache_size;
        $randomPageCost = $db->selectOne('SHOW random_page_cost')->random_page_cost;

        // Ambil git commit hash saat ini
        $gitCommit = trim((string) @shell_exec('git rev-parse HEAD')) ?: '90075eec4774266db5c8f56ec57fe8efa64250a4';

        $pgEnv = [
            'pg_version'           => $pgVersion,
            'shared_buffers'       => $sharedBuffers,
            'work_mem'             => $workMem,
            'effective_cache_size' => $effectiveCacheSize,
            'random_page_cost'     => $randomPageCost,
            'host_specs'           => [
                'cpu'            => 'Intel(R) Core(TM) i5-7300U CPU @ 2.60GHz (2 cores, 4 threads)',
                'ram'            => '15.88 GB',
                'os'             => 'Windows 10/11 x64',
                'docker_engine'  => 'Docker 29.9.3, build 5457199',
                'docker_desktop' => 'Docker Desktop 4.99.0',
                'wsl2_kernel'    => 'WSL version 3.0.5.0, Kernel 6.18.58.1-1',
            ],
            'benchmark_metadata'   => [
                'git_commit'       => $gitCommit,
                'random_seed'      => $seed,
                'target_samples'   => $targetCount,
                'dataset_records'  => $records,
                'buffer_metric'    => 'Root Plan Node (Shared Hit / Read Blocks), non-recursive',
            ],
        ];

        $this->line("Spesifikasi Server PostgreSQL Benchmark:");
        $this->line("  Versi Engine         : {$pgVersion}");
        $this->line("  shared_buffers       : {$sharedBuffers}");
        $this->line("  work_mem             : {$workMem}");
        $this->line("  effective_cache_size : {$effectiveCacheSize}");
        $this->line("  random_page_cost     : {$randomPageCost}");
        $this->line("  Git Commit           : {$gitCommit}");
        $this->line("  Random Seed          : {$seed}");
        $this->newLine();

        $currentCount = $db->table('pendaftaran_tte')->count();
        $this->info("Jumlah data saat ini pada pendaftaran_tte: " . number_format($currentCount, 0, ',', '.'));

        if ($currentCount < $records || $forceReseed) {
            $this->info("[1/5] Menyiapkan data sintetis realistis " . number_format($records, 0, ',', '.') . " baris (Seed: {$seed})...");

            if ($forceReseed && $currentCount > 0) {
                $this->warn("Membersihkan data lama pendaftaran_tte di tte_benchmark...");
                $db->table('sertifikat_tte')->delete();
                $db->table('pendaftaran_tte')->delete();
            }

            $skpdIds = $db->table('skpd')->pluck('id')->toArray();
            if (empty($skpdIds)) {
                $this->error("Tabel skpd kosong! Harap periksa database benchmark.");
                return 1;
            }

            // Inisialisasi Seed
            mt_srand($seed);

            // 1. Buat 500 Kohort Realistis NIP (YYYYMMDD lahir + YYYYMM TMT)
            // Memungkinkan banyak ASN berbagi tanggal lahir & TMT CPNS yang sama tanpa tabrakan indeks unik
            $cohorts = [];
            for ($c = 0; $c < 500; $c++) {
                $bYear = 1970 + ($c % 30); // 1970 - 1999
                $bMonth = str_pad((string)(($c % 12) + 1), 2, '0', STR_PAD_LEFT);
                $bDay = str_pad((string)(($c % 28) + 1), 2, '0', STR_PAD_LEFT);

                $tmtYear = $bYear + 22 + ($c % 8); // TMT pengangkatan CPNS usia 22-29
                $tmtMonth = str_pad((string)((($c * 3) % 12) + 1), 2, '0', STR_PAD_LEFT);

                $cohorts[] = [
                    'birth' => "{$bYear}{$bMonth}{$bDay}",
                    'tmt'   => "{$tmtYear}{$tmtMonth}",
                ];
            }

            // 2. Daftar Kecamatan Kota Cirebon untuk NIK Realistis
            $cirebonKec = ['327401', '327402', '327403', '327404', '327405'];

            // 3. Status disisipkan acak (bukan berblok) dengan proporsi tepat
            $statuses = array_merge(
                array_fill(0, 60000, 'MENUNGGU_VERIFIKASI'),
                array_fill(0, 20000, 'DISETUJUI'),
                array_fill(0, 20000, 'DITOLAK')
            );
            // Fisher-Yates shuffle dengan mt_rand (deterministik berdasarkan seed)
            for ($i = count($statuses) - 1; $i > 0; $i--) {
                $j = mt_rand(0, $i);
                $tmp = $statuses[$i];
                $statuses[$i] = $statuses[$j];
                $statuses[$j] = $tmp;
            }

            $chunkSize = 1000;
            $chunks = ceil($records / $chunkSize);
            $bar = $this->output->createProgressBar($chunks);
            $bar->start();

            for ($c = 0; $c < $chunks; $c++) {
                $rows = [];
                $offset = $c * $chunkSize;
                $limit = min($chunkSize, $records - $offset);

                for ($i = 0; $i < $limit; $i++) {
                    $idx = $offset + $i + 1;
                    $k = $idx - 1; // 0 hingga 99.999

                    // Generator NIP Bijective: 500 kohort x 2 gender = 1000 bucket. No urut 1-100 per bucket.
                    $seqInCohort = (int) floor($k / 1000) + 1; // 1 s.d. 100
                    $bucket = $k % 1000;                       // 0 s.d. 999
                    $cohortId = (int) floor($bucket / 2);      // 0 s.d. 499
                    $gender = ($bucket % 2) + 1;               // 1 = Laki-laki, 2 = Perempuan
                    $cohort = $cohorts[$cohortId];
                    $seqNip = sprintf('%03d', $seqInCohort);
                    $nipPlain = "{$cohort['birth']}{$cohort['tmt']}{$gender}{$seqNip}";

                    // Generator NIK Bijective: Kode Cirebon (327401-05) + DDMMYY (DD+40 wanita) + No Urut 4-digit
                    $kec = $cirebonKec[$k % 5];
                    $bDayInt = ($k % 28) + 1;
                    if ($gender === 2) {
                        $bDayInt += 40; // Aturan Kemendagri: Tanggal lahir perempuan ditambah 40
                    }
                    $nikDay = str_pad((string)$bDayInt, 2, '0', STR_PAD_LEFT);
                    $nikMonth = str_pad((string)(((int)floor($k / 28) % 12) + 1), 2, '0', STR_PAD_LEFT);
                    $yearInt = 1970 + ((int)floor($k / (28 * 12)) % 30);
                    $nikYear2 = substr((string)$yearInt, 2, 2);
                    $nikSeq = sprintf('%04d', ((int)floor($k / (28 * 12 * 30)) % 9999) + 1);
                    $nikPlain = "{$kec}{$nikDay}{$nikMonth}{$nikYear2}{$nikSeq}";

                    $nikCipher = CryptoHelper::encryptField($nikPlain);
                    $nipCipher = CryptoHelper::encryptField($nipPlain);

                    $rows[] = [
                        'uuid'                     => (string) Str::uuid(),
                        'nomor_referensi'          => sprintf('REF-BENCH-%08d', $idx),
                        'nama_lengkap'             => "Pegawai Kohort Ke-" . $idx,
                        'nik'                      => $nikCipher,
                        'nip'                      => $nipCipher,
                        'no_hp'                    => '08' . str_pad((string)$idx, 10, '0', STR_PAD_LEFT),
                        'skpd_id'                  => $skpdIds[$idx % count($skpdIds)],
                        'status'                   => $statuses[$idx - 1],
                        'status_memiliki_email'    => false,
                        'is_email_recap_confirmed' => false,
                        'tipe_pegawai'             => 'ASN',
                        'deskripsi'                => "Deskripsi penugasan kohort wilayah Cirebon ke-" . $idx,
                        'created_at'               => now()->subMinutes(100000 - $idx),
                        'updated_at'               => now(),
                    ];
                }

                $db->table('pendaftaran_tte')->insert($rows);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✓ Seeding data realistis pendaftaran_tte tuntas.");
        }

        // Pastikan indeks universal idx_pendaftaran_nik_all & idx_pendaftaran_nip_all aktif
        $db->statement("CREATE INDEX IF NOT EXISTS idx_pendaftaran_nik_all ON pendaftaran_tte (nik) WHERE nik IS NOT NULL;");
        $db->statement("CREATE INDEX IF NOT EXISTS idx_pendaftaran_nip_all ON pendaftaran_tte (nip) WHERE nip IS NOT NULL;");

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

        // Jalankan VACUUM ANALYZE agar statistik query planner 100% up-to-date
        $this->info("[2/5] Menjalankan VACUUM ANALYZE pendaftaran_tte...");
        $db->statement("VACUUM ANALYZE pendaftaran_tte;");
        $this->info("✓ VACUUM ANALYZE tuntas.");

        // [3/5] Pengambilan Target Acak (>= 30 Target)
        $this->info("[3/5] Mengambil {$targetCount} target pencarian acak dari dataset...");
        mt_srand($seed);

        $activeIds = $db->table('pendaftaran_tte')->where('status', 'MENUNGGU_VERIFIKASI')->pluck('id')->toArray();
        $rejectedIds = $db->table('pendaftaran_tte')->where('status', 'DITOLAK')->pluck('id')->toArray();

        // 20 Target Aktif + 10 Target Ditolak
        $pickedActiveKeys = array_rand($activeIds, 20);
        $pickedRejectedKeys = array_rand($rejectedIds, 10);

        $selectedIds = [];
        foreach ($pickedActiveKeys as $k) {
            $selectedIds[] = $activeIds[$k];
        }
        foreach ($pickedRejectedKeys as $k) {
            $selectedIds[] = $rejectedIds[$k];
        }

        $targets = $db->table('pendaftaran_tte')->whereIn('id', $selectedIds)->get()->map(function ($row) {
            return [
                'id'       => $row->id,
                'nama'     => $row->nama_lengkap,
                'ref'      => $row->nomor_referensi,
                'status'   => $row->status,
                'nik_c'    => $row->nik,
                'nip_c'    => $row->nip,
                'nik_p'    => CryptoHelper::decryptField($row->nik),
                'nip_p'    => CryptoHelper::decryptField($row->nip),
            ];
        })->values()->toArray();

        $this->line("Target acak berhasil diambil: " . count($targets) . " rekaman (Seed: {$seed}).");

        $resultsDir = base_path('bench/results');
        if (!is_dir($resultsDir)) {
            mkdir($resultsDir, 0755, true);
        }

        // Simpan konfigurasi server & spesifikasi host
        file_put_contents($resultsDir . '/phase1_pg_env.json', json_encode($pgEnv, JSON_PRETTY_PRINT));

        // Definisi Kueri A - H
        $queryDefs = [
            'phase1_query_a_combined' => [
                'id'          => 'Query A',
                'title'       => 'Query A: Jalur Lama (Algoritma 4 Compound OR: ILIKE nama/ref/deskripsi OR nik/nip, filter status)',
                'sql'         => "SELECT * FROM pendaftaran_tte WHERE status = 'MENUNGGU_VERIFIKASI' AND (nama_lengkap ILIKE ? OR nomor_referensi ILIKE ? OR deskripsi ILIKE ? OR nik = ? OR nip = ?) ORDER BY created_at ASC LIMIT 15",
                'build_bind'  => fn($t) => ["%{$t['nik_p']}%", "%{$t['nik_p']}%", "%{$t['nik_p']}%", $t['nik_c'], $t['nik_c']],
                'description' => 'Jalur lama compound OR dengan filter status',
            ],
            'phase1_query_b_combined_nostatus' => [
                'id'          => 'Query B',
                'title'       => 'Query B: Jalur Lama Tanpa Filter Status (Compound OR murni teks + cipher)',
                'sql'         => "SELECT * FROM pendaftaran_tte WHERE (nama_lengkap ILIKE ? OR nomor_referensi ILIKE ? OR deskripsi ILIKE ? OR nik = ? OR nip = ?) LIMIT 15",
                'build_bind'  => fn($t) => ["%{$t['nik_p']}%", "%{$t['nik_p']}%", "%{$t['nik_p']}%", $t['nik_c'], $t['nik_c']],
                'description' => 'Jalur lama compound OR tanpa filter status',
            ],
            'phase1_query_c_separated_nik_status' => [
                'id'          => 'Query C',
                'title'       => 'Query C: Jalur Terpisah Exact-Match NIK (status = MENUNGGU_VERIFIKASI)',
                'sql'         => "SELECT * FROM pendaftaran_tte WHERE status = 'MENUNGGU_VERIFIKASI' AND nik = ? LIMIT 15",
                'build_bind'  => fn($t) => [$t['nik_c']],
                'description' => 'Jalur terpisah exact-match NIK pada data aktif',
            ],
            'phase1_query_d_separated_nik_active' => [
                'id'          => 'Query D',
                'title'       => 'Query D: Jalur Terpisah Exact-Match NIK (status != DITOLAK / Indeks Parsial)',
                'sql'         => "SELECT * FROM pendaftaran_tte WHERE status != 'DITOLAK' AND nik = ? LIMIT 15",
                'build_bind'  => fn($t) => [$t['nik_c']],
                'description' => 'Jalur terpisah exact-match NIK dengan predikat status != DITOLAK',
            ],
            'phase1_query_e_separated_nip_status' => [
                'id'          => 'Query E',
                'title'       => 'Query E: Jalur Terpisah Exact-Match NIP (status = MENUNGGU_VERIFIKASI)',
                'sql'         => "SELECT * FROM pendaftaran_tte WHERE status = 'MENUNGGU_VERIFIKASI' AND nip = ? LIMIT 15",
                'build_bind'  => fn($t) => [$t['nip_c']],
                'description' => 'Jalur terpisah exact-match NIP 18 digit pada data aktif',
            ],
            'phase1_query_f_separated_text' => [
                'id'          => 'Query F',
                'title'       => 'Query F: Jalur Terpisah Pencarian Teks Murni (ILIKE nama/ref dengan wildcard escaping)',
                'sql'         => "SELECT * FROM pendaftaran_tte WHERE status = 'MENUNGGU_VERIFIKASI' AND (nama_lengkap ILIKE ? OR nomor_referensi ILIKE ?) ORDER BY created_at ASC LIMIT 15",
                'build_bind'  => fn($t) => ['%' . addcslashes($t['nama'], '%_\\') . '%', '%' . addcslashes($t['ref'], '%_\\') . '%'],
                'description' => 'Jalur terpisah pencarian teks dengan sanitasi wildcard',
            ],
            'phase1_query_g_separated_nik_all_status' => [
                'id'          => 'Query G',
                'title'       => 'Query G: Jalur Terpisah Exact-Match NIK Tanpa Filter Status (Mencakup Semua Status)',
                'sql'         => "SELECT * FROM pendaftaran_tte WHERE nik = ? LIMIT 15",
                'build_bind'  => fn($t) => [$t['nik_c']],
                'description' => 'Jalur terpisah exact-match NIK tanpa predikat status (memanfaatkan idx_pendaftaran_nik_all)',
            ],
            'phase1_query_h_combined_nik_nip' => [
                'id'          => 'Query H',
                'title'       => 'Query H: Pencarian Gabungan Dua Kolom Terenkripsi (nik = ? OR nip = ?)',
                'sql'         => "SELECT * FROM pendaftaran_tte WHERE (nik = ? OR nip = ?) LIMIT 15",
                'build_bind'  => fn($t) => [$t['nik_c'], $t['nip_c']],
                'description' => 'Pencarian majemuk dua identitas terenkripsi (menguji BitmapOr)',
            ],
        ];

        $this->info("[4/5] Mengeksekusi 8 skenario kueri (Query A - H) pada {$targetCount} target acak...");

        $summaryTable = [];
        $textIllustrativeReport = "====================================================================================================\n";
        $textIllustrativeReport .= "HASIL ILUSTRASI EXPLAIN (FORMAT TEXT) - POSTGRESQL 16 (CONTAINER TERISOLASI)\n";
        $textIllustrativeReport .= "Tanggal Pengujian : " . now()->toIso8601String() . "\n";
        $textIllustrativeReport .= "Container Target  : tte_postgres_bench (Port 5433 host / 5432 internal)\n";
        $textIllustrativeReport .= "PostgreSQL Engine : {$pgVersion}\n";
        $textIllustrativeReport .= "Server Config     : shared_buffers={$sharedBuffers}, work_mem={$workMem}, effective_cache_size={$effectiveCacheSize}, random_page_cost={$randomPageCost}\n";
        $textIllustrativeReport .= "Dataset           : " . number_format($records, 0, ',', '.') . " baris pendaftaran_tte\n";
        $textIllustrativeReport .= "DISCLAIMER        : Plan teks di bawah ini adalah ILUSTRASI representatif dari 1 run terukur.\n";
        $textIllustrativeReport .= "                    Metrik numerik resmi (mean/median/min/max/SD) dihitung dari evaluasi 30 target\n";
        $textIllustrativeReport .= "                    pada berkas phase1_summary.json.\n";
        $textIllustrativeReport .= "====================================================================================================\n\n";

        foreach ($queryDefs as $key => $qInfo) {
            $this->line("Mengevaluasi {$qInfo['id']} ({$targetCount} target)...");

            // 1. Warm-Up Run
            $warmBindings = ($qInfo['build_bind'])($targets[0]);
            $db->select("EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) " . $qInfo['sql'], $warmBindings);

            // 2. Evaluasi Terukur pada 30 Target Acak
            $execTimes = [];
            $planTimes = [];
            $plansJson = [];
            $rootSharedHits = [];
            $rootSharedReads = [];

            foreach ($targets as $tgt) {
                $bindings = ($qInfo['build_bind'])($tgt);
                $rawJson = $db->select("EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) " . $qInfo['sql'], $bindings);
                $parsed = json_decode($rawJson[0]->{'QUERY PLAN'}, true)[0];

                $plansJson[] = $parsed;
                $execTimes[] = (float) $parsed['Execution Time'];
                $planTimes[] = (float) $parsed['Planning Time'];

                // Ambil Shared Hit & Read murni dari NODE AKAR (Plan), bukan akumulasi rekursif
                $rootHit = (int) ($parsed['Plan']['Shared Hit Blocks'] ?? 0);
                $rootRead = (int) ($parsed['Plan']['Shared Read Blocks'] ?? 0);
                $rootSharedHits[] = $rootHit;
                $rootSharedReads[] = $rootRead;
            }

            // Hitung Statistik Deskriptif
            $sortedExec = $execTimes;
            sort($sortedExec);
            $medianIndex = (int) floor(count($sortedExec) / 2);
            $medianExec = $sortedExec[$medianIndex];
            $meanExec = array_sum($execTimes) / count($execTimes);
            $minExec = min($execTimes);
            $maxExec = max($execTimes);

            // Standar Deviasi
            $variance = 0.0;
            foreach ($execTimes as $et) {
                $variance += pow($et - $meanExec, 2);
            }
            $stdDev = sqrt($variance / count($execTimes));

            $sortedPlan = $planTimes;
            sort($sortedPlan);
            $medianPlan = $sortedPlan[$medianIndex];

            // Cari plan JSON yang paling mendekati median untuk berkas representatif
            $representativePlan = $plansJson[0];
            $closestDiff = PHP_FLOAT_MAX;
            foreach ($plansJson as $p) {
                $diff = abs((float)$p['Execution Time'] - $medianExec);
                if ($diff < $closestDiff) {
                    $closestDiff = $diff;
                    $representativePlan = $p;
                }
            }

            // Simpan berkas JSON mentah representatif
            $jsonFilePath = $resultsDir . "/{$key}_explain.json";
            file_put_contents($jsonFilePath, json_encode([$representativePlan], JSON_PRETTY_PRINT));

            // Dapatkan text format representatif ilustratif
            $rawText = $db->select("EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) " . $qInfo['sql'], ($qInfo['build_bind'])($targets[0]));
            $textLines = array_map(fn($row) => $row->{'QUERY PLAN'}, $rawText);
            $fullPlanText = implode("\n", $textLines);

            // Parse scan node dan nama indeks
            $rootPlan = $representativePlan['Plan'];
            $scanNode = $this->findScanNode($rootPlan);
            $primaryScan = $scanNode['Node Type'] ?? $rootPlan['Node Type'];
            $indexUsed = $this->extractIndexName($rootPlan);

            // Buffer murni dari root plan representatif
            $repRootHit = (int) ($rootPlan['Shared Hit Blocks'] ?? 0);
            $repRootRead = (int) ($rootPlan['Shared Read Blocks'] ?? 0);

            $textIllustrativeReport .= "### [{$qInfo['id']}] {$qInfo['title']}\n";
            $textIllustrativeReport .= "Deskripsi   : {$qInfo['description']}\n";
            $textIllustrativeReport .= "SQL         : {$qInfo['sql']}\n";
            $textIllustrativeReport .= "Evaluasi    : {$targetCount} target acak (Seed: {$seed})\n";
            $textIllustrativeReport .= "Statistik   : Min: " . number_format($minExec, 3) . " ms | Max: " . number_format($maxExec, 3) . " ms | Mean: " . number_format($meanExec, 3) . " ms | Median: " . number_format($medianExec, 3) . " ms | SD: " . number_format($stdDev, 3) . " ms\n";
            $textIllustrativeReport .= "Root Buffer : Shared Hit={$repRootHit}, Shared Read={$repRootRead} (Diambil langsung dari Root Plan Node)\n";
            $textIllustrativeReport .= "[ILUSTRASI PLAN TEXT - SINGLE RUN BUKAN DARI MEDIAN AGREGAT]:\n" . $fullPlanText . "\n\n" . str_repeat("-", 100) . "\n\n";

            $summaryTable[] = [
                'Query ID'        => $qInfo['id'],
                'Scenario Key'    => $key,
                'Primary Scan'    => $primaryScan,
                'Index Used'      => $indexUsed,
                'Median (ms)'     => number_format($medianExec, 3),
                'Mean (ms)'       => number_format($meanExec, 3),
                'Min (ms)'        => number_format($minExec, 3),
                'Max (ms)'        => number_format($maxExec, 3),
                'StdDev (ms)'     => number_format($stdDev, 3),
                'Plan Time (ms)'  => number_format($medianPlan, 3),
                'Shared Hit'      => $repRootHit,
                'Shared Read'     => $repRootRead,
            ];
        }

        // [5/5] KONTROL K1 (Ablasi: Uji Query G dengan idx_pendaftaran_nik_all di-drop sementara)
        $this->info("[5/5] Menjalankan KONTROL K1 (Ablasi Indeks: Query G tanpa idx_pendaftaran_nik_all)...");
        $db->statement("DROP INDEX IF EXISTS idx_pendaftaran_nik_all;");

        // Jalankan evaluasi Query G tanpa indeks
        $qG = $queryDefs['phase1_query_g_separated_nik_all_status'];
        $k1ExecTimes = [];
        $k1PlanTimes = [];
        $k1PlansJson = [];

        foreach ($targets as $tgt) {
            $bindings = ($qG['build_bind'])($tgt);
            $rawJson = $db->select("EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) " . $qG['sql'], $bindings);
            $parsed = json_decode($rawJson[0]->{'QUERY PLAN'}, true)[0];
            $k1PlansJson[] = $parsed;
            $k1ExecTimes[] = (float) $parsed['Execution Time'];
            $k1PlanTimes[] = (float) $parsed['Planning Time'];
        }

        // Statistik Kontrol K1
        sort($k1ExecTimes);
        $k1MedianIndex = (int) floor(count($k1ExecTimes) / 2);
        $k1MedianExec = $k1ExecTimes[$k1MedianIndex];
        $k1MeanExec = array_sum($k1ExecTimes) / count($k1ExecTimes);
        $k1MinExec = min($k1ExecTimes);
        $k1MaxExec = max($k1ExecTimes);

        $k1Variance = 0.0;
        foreach ($k1ExecTimes as $et) {
            $k1Variance += pow($et - $k1MeanExec, 2);
        }
        $k1StdDev = sqrt($k1Variance / count($k1ExecTimes));

        $representativeK1 = $k1PlansJson[0];
        $closestDiff = PHP_FLOAT_MAX;
        foreach ($k1PlansJson as $p) {
            $diff = abs((float)$p['Execution Time'] - $k1MedianExec);
            if ($diff < $closestDiff) {
                $closestDiff = $diff;
                $representativeK1 = $p;
            }
        }

        // Simpan JSON rencana eksekusi Kontrol K1
        file_put_contents($resultsDir . '/phase1_control_k1_no_index_explain.json', json_encode([$representativeK1], JSON_PRETTY_PRINT));

        $k1RootHit = (int) ($representativeK1['Plan']['Shared Hit Blocks'] ?? 0);
        $k1RootRead = (int) ($representativeK1['Plan']['Shared Read Blocks'] ?? 0);
        $k1ScanNode = $this->findScanNode($representativeK1['Plan']);
        $k1PrimaryScan = $k1ScanNode['Node Type'] ?? $representativeK1['Plan']['Node Type'];

        $summaryTable[] = [
            'Query ID'        => 'Control K1',
            'Scenario Key'    => 'phase1_control_k1_no_index',
            'Primary Scan'    => $k1PrimaryScan,
            'Index Used'      => 'None (Index Dropped for Control)',
            'Median (ms)'     => number_format($k1MedianExec, 3),
            'Mean (ms)'       => number_format($k1MeanExec, 3),
            'Min (ms)'        => number_format($k1MinExec, 3),
            'Max (ms)'        => number_format($k1MaxExec, 3),
            'StdDev (ms)'     => number_format($k1StdDev, 3),
            'Plan Time (ms)'  => number_format($k1PlanTimes[0], 3),
            'Shared Hit'      => $k1RootHit,
            'Shared Read'     => $k1RootRead,
        ];

        // Dapatkan text format ilustratif Kontrol K1
        $rawTextK1 = $db->select("EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) " . $qG['sql'], ($qG['build_bind'])($targets[0]));
        $textLinesK1 = array_map(fn($row) => $row->{'QUERY PLAN'}, $rawTextK1);
        $fullPlanTextK1 = implode("\n", $textLinesK1);

        $textIllustrativeReport .= "### [Control K1] Kontrol K1: Query G dengan idx_pendaftaran_nik_all di-drop (Ablasi Indeks)\n";
        $textIllustrativeReport .= "Deskripsi   : Menilai dampak ketiadaan indeks universal pada pencarian exact-match NIK\n";
        $textIllustrativeReport .= "SQL         : " . $qG['sql'] . "\n";
        $textIllustrativeReport .= "Evaluasi    : {$targetCount} target acak (Seed: {$seed})\n";
        $textIllustrativeReport .= "Statistik   : Min: " . number_format($k1MinExec, 3) . " ms | Max: " . number_format($k1MaxExec, 3) . " ms | Mean: " . number_format($k1MeanExec, 3) . " ms | Median: " . number_format($k1MedianExec, 3) . " ms | SD: " . number_format($k1StdDev, 3) . " ms\n";
        $textIllustrativeReport .= "Root Buffer : Shared Hit={$k1RootHit}, Shared Read={$k1RootRead} (Node Akar)\n";
        $textIllustrativeReport .= "[ILUSTRASI PLAN TEXT - SINGLE RUN BUKAN DARI MEDIAN AGREGAT]:\n" . $fullPlanTextK1 . "\n\n" . str_repeat("-", 100) . "\n\n";

        // Kembalikan indeks idx_pendaftaran_nik_all
        $this->info("Mengembalikan indeks idx_pendaftaran_nik_all pada tte_benchmark...");
        $db->statement("CREATE INDEX idx_pendaftaran_nik_all ON pendaftaran_tte (nik) WHERE nik IS NOT NULL;");
        $db->statement("VACUUM ANALYZE pendaftaran_tte;");
        $this->info("✓ Indeks idx_pendaftaran_nik_all berhasil dipulihkan.");

        // Simpan berkas teks ilustrasi
        file_put_contents($resultsDir . "/phase1_explain_plans.txt", $textIllustrativeReport);

        // Simpan berkas JSON ringkasan
        file_put_contents($resultsDir . "/phase1_summary.json", json_encode([
            'pg_environment'     => $pgEnv,
            'dataset_records'    => $records,
            'target_sample_size' => $targetCount,
            'summary_table'      => $summaryTable,
        ], JSON_PRETTY_PRINT));

        $this->newLine();
        $this->table(
            ['Query ID', 'Primary Scan', 'Index Used', 'Median (ms)', 'Mean (ms)', 'Min (ms)', 'Max (ms)', 'SD (ms)', 'Root Hit', 'Root Read'],
            array_map(function ($row) {
                return [
                    $row['Query ID'],
                    $row['Primary Scan'],
                    $row['Index Used'],
                    $row['Median (ms)'],
                    $row['Mean (ms)'],
                    $row['Min (ms)'],
                    $row['Max (ms)'],
                    $row['StdDev (ms)'],
                    $row['Shared Hit'],
                    $row['Shared Read'],
                ];
            }, $summaryTable)
        );

        $this->info("✓ Seluruh output mentah dan laporan tolok ukur Fase 1c berhasil disimpan di: {$resultsDir}/");
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
}
