<?php

namespace App\Console\Commands;

use App\Services\Security\CryptoHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RunPhase1ExplainBenchmark extends Command
{
    protected $signature = 'bench:phase1-explain {--records=100000 : Jumlah record sintetis untuk seeding} {--force-reseed : Bersihkan dan isi ulang tabel pendaftaran_tte di benchmark DB}';
    protected $description = 'Fase 1: Menjalankan EXPLAIN (ANALYZE, BUFFERS) query pencarian pada DB benchmark tte_benchmark dan menyimpan hasil mentah ke bench/results/';

    public function handle(): int
    {
        $db = DB::connection('pgsql_bench');

        $this->info("================================================================================");
        $this->info("  FASE 1: EXPLAIN (ANALYZE, BUFFERS) AUDIT PENGGUNAAN INDEKS BASIS DATA         ");
        $this->info("  Database Target : " . $db->getDatabaseName() . " (ISOLATED BENCHMARK DB)");
        $this->info("================================================================================");

        // Pastikan tidak menyentuh database produksi
        if ($db->getDatabaseName() !== 'tte_benchmark') {
            $this->error("ABORT: Perintah ini HANYA boleh dijalankan pada database tte_benchmark!");
            return 1;
        }

        $records = (int) $this->option('records');
        $forceReseed = $this->option('force-reseed');

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

            $statuses = ['MENUNGGU_VERIFIKASI', 'MENUNGGU_VERIFIKASI', 'MENUNGGU_VERIFIKASI', 'DISETUJUI', 'DITOLAK'];

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
                    $status = $statuses[$idx % count($statuses)];

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

        // Jalankan VACUUM ANALYZE agar statistik query planner 100% akurat
        $this->info("[2/4] Menjalankan VACUUM ANALYZE pendaftaran_tte...");
        $db->statement("VACUUM ANALYZE pendaftaran_tte;");
        $this->info("✓ VACUUM ANALYZE tuntas.");

        // Target pencarian: ambil record di posisi ~50% yang berstatus MENUNGGU_VERIFIKASI
        $targetRow = $db->table('pendaftaran_tte')
            ->where('status', 'MENUNGGU_VERIFIKASI')
            ->offset((int)($records / 2))
            ->limit(1)
            ->first();

        if (!$targetRow) {
            $targetRow = $db->table('pendaftaran_tte')->where('status', 'MENUNGGU_VERIFIKASI')->first();
        }

        $targetNikCipher = $targetRow->nik;
        $targetNipCipher = $targetRow->nip;
        $targetNikPlain = CryptoHelper::decryptField($targetNikCipher);
        $targetNama = $targetRow->nama_lengkap;
        $targetRef = $targetRow->nomor_referensi;

        $this->info("[3/4] Data target benchmark:");
        $this->line("  ID               : {$targetRow->id}");
        $this->line("  Nama             : {$targetNama}");
        $this->line("  NIK Plain        : {$targetNikPlain}");
        $this->line("  NIK Cipher (b64) : " . substr($targetNikCipher, 0, 32) . "...");
        $this->line("  Status           : {$targetRow->status}");

        $resultsDir = base_path('bench/results');
        if (!is_dir($resultsDir)) {
            mkdir($resultsDir, 0755, true);
        }

        $this->info("[4/4] Mengeksekusi EXPLAIN (ANALYZE, BUFFERS) untuk 6 skenario kueri...");

        $queries = [
            'phase1_query_a_combined' => [
                'title' => 'Query A: Algoritma 4 Lama (Compound OR: ILIKE nama/ref/deskripsi OR nik/nip, filter status)',
                'sql' => "SELECT * FROM pendaftaran_tte WHERE status = 'MENUNGGU_VERIFIKASI' AND (nama_lengkap ILIKE ? OR nomor_referensi ILIKE ? OR deskripsi ILIKE ? OR nik = ? OR nip = ?) ORDER BY created_at ASC LIMIT 15",
                'bindings' => ["%{$targetNikPlain}%", "%{$targetNikPlain}%", "%{$targetNikPlain}%", $targetNikCipher, $targetNikCipher],
            ],
            'phase1_query_b_combined_nostatus' => [
                'title' => 'Query B: Algoritma 4 Lama Tanpa Filter Status (Compound OR murni)',
                'sql' => "SELECT * FROM pendaftaran_tte WHERE (nama_lengkap ILIKE ? OR nomor_referensi ILIKE ? OR deskripsi ILIKE ? OR nik = ? OR nip = ?) LIMIT 15",
                'bindings' => ["%{$targetNikPlain}%", "%{$targetNikPlain}%", "%{$targetNikPlain}%", $targetNikCipher, $targetNikCipher],
            ],
            'phase1_query_c_separated_nik_status' => [
                'title' => 'Query C: Jalur Terpisah Exact-Match NIK (status = MENUNGGU_VERIFIKASI)',
                'sql' => "SELECT * FROM pendaftaran_tte WHERE status = 'MENUNGGU_VERIFIKASI' AND nik = ? LIMIT 15",
                'bindings' => [$targetNikCipher],
            ],
            'phase1_query_d_separated_nik_active' => [
                'title' => 'Query D: Jalur Terpisah Exact-Match NIK (status != DITOLAK / Indeks Parsial)',
                'sql' => "SELECT * FROM pendaftaran_tte WHERE status != 'DITOLAK' AND nik = ? LIMIT 15",
                'bindings' => [$targetNikCipher],
            ],
            'phase1_query_e_separated_nip_status' => [
                'title' => 'Query E: Jalur Terpisah Exact-Match NIP (status = MENUNGGU_VERIFIKASI)',
                'sql' => "SELECT * FROM pendaftaran_tte WHERE status = 'MENUNGGU_VERIFIKASI' AND nip = ? LIMIT 15",
                'bindings' => [$targetNipCipher],
            ],
            'phase1_query_f_separated_text' => [
                'title' => 'Query F: Jalur Terpisah Pencarian Teks (Hanya ILIKE nama/ref/deskripsi tanpa OR kolom terenkripsi)',
                'sql' => "SELECT * FROM pendaftaran_tte WHERE status = 'MENUNGGU_VERIFIKASI' AND (nama_lengkap ILIKE ? OR nomor_referensi ILIKE ?) ORDER BY created_at ASC LIMIT 15",
                'bindings' => ["%Pegawai Simulasi Ke-50000%", "%50000%"],
            ],
        ];

        $summaryTable = [];
        $textReport = "HASIL EXPLAIN (ANALYZE, BUFFERS) - SISMON TTE POSTGRESQL 16\n";
        $textReport .= "Tanggal Pengujian: " . now()->toIso8601String() . "\n";
        $textReport .= "Dataset: " . number_format($records, 0, ',', '.') . " baris pendaftaran_tte di tte_benchmark\n";
        $textReport .= str_repeat("=", 80) . "\n\n";

        foreach ($queries as $key => $qInfo) {
            $this->line("Menjalankan: " . $qInfo['title']);

            // 1. Dapatkan JSON format
            $rawJson = $db->select("EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) " . $qInfo['sql'], $qInfo['bindings']);
            $jsonContent = $rawJson[0]->{'QUERY PLAN'};
            $jsonFilePath = $resultsDir . "/{$key}_explain.json";
            file_put_contents($jsonFilePath, json_encode(json_decode($jsonContent), JSON_PRETTY_PRINT));

            // 2. Dapatkan TEXT format
            $rawText = $db->select("EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) " . $qInfo['sql'], $qInfo['bindings']);
            $textLines = array_map(fn($row) => $row->{'QUERY PLAN'}, $rawText);
            $fullPlanText = implode("\n", $textLines);

            $textReport .= "### " . $qInfo['title'] . "\n";
            $textReport .= "SQL: " . $qInfo['sql'] . "\n";
            $textReport .= "PLAN:\n" . $fullPlanText . "\n\n" . str_repeat("-", 80) . "\n\n";

            // Parse ringkasan metrik dari JSON
            $planObj = json_decode($jsonContent, true)[0]['Plan'];
            $rootNodeType = $planObj['Node Type'] ?? 'Unknown';
            
            // Rekursif cari scan node terdalam
            $scanNode = $this->findScanNode($planObj);

            $planningTime = json_decode($jsonContent, true)[0]['Planning Time'] ?? 0;
            $executionTime = json_decode($jsonContent, true)[0]['Execution Time'] ?? 0;
            $sharedHit = $planObj['Shared Hit Blocks'] ?? 0;
            $sharedRead = $planObj['Shared Read Blocks'] ?? 0;

            $summaryTable[] = [
                'Scenario'        => $key,
                'Primary Scan'    => $scanNode['Node Type'] ?? $rootNodeType,
                'Index Used'      => $scanNode['Index Name'] ?? 'None (Seq Scan)',
                'Exec Time (ms)'  => number_format($executionTime, 3),
                'Plan Time (ms)'  => number_format($planningTime, 3),
                'Shared Hit'      => $sharedHit,
                'Shared Read'     => $sharedRead,
            ];
        }

        // Tulis laporan teks utuh
        file_put_contents($resultsDir . "/phase1_explain_plans.txt", $textReport);

        // Simpan tabel ringkasan ke JSON
        file_put_contents($resultsDir . "/phase1_summary.json", json_encode($summaryTable, JSON_PRETTY_PRINT));

        $this->newLine();
        $this->table(
            ['Scenario', 'Primary Scan', 'Index Used', 'Exec Time (ms)', 'Plan Time (ms)', 'Shared Hit', 'Shared Read'],
            $summaryTable
        );

        $this->info("✓ Semua file keluaran mentah berhasil disimpan di: {$resultsDir}/");
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
}
