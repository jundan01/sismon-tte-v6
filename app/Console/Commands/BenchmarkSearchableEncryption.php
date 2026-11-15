<?php

namespace App\Console\Commands;

use App\Services\Security\CryptoHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BenchmarkSearchableEncryption extends Command
{
    protected $signature = 'benchmark:searchable-encryption {--records=100000 : Jumlah record sintetis} {--queries=100 : Jumlah pengulangan kueri}';
    protected $description = 'Benchmarking B-Tree Indexed Plaintext vs AES-256-CBC Deterministik vs In-Memory Decryption pada dataset 100.000 rekaman';

    public function handle(): int
    {
        $records = (int) $this->option('records');
        $queryReps = (int) $this->option('queries');
        $tableName = 'benchmark_encryption_records';

        $this->info("================================================================================");
        $this->info("  BENCHMARK SEARCHABLE ENCRYPTION POSTGRESQL 16 & LARAVEL 12/13 (PHP 8.3)      ");
        $this->info("================================================================================");
        $this->info("Jumlah Data Sintetis : " . number_format($records, 0, ',', '.') . " baris");
        $this->info("Jumlah Repetisi Kueri: " . number_format($queryReps, 0, ',', '.') . " kali");
        $this->newLine();

        // 1. Setup Tabel Benchmark
        $this->info("[1/4] Menyiapkan skema tabel benchmark dan indeks B-Tree...");
        Schema::dropIfExists($tableName);

        DB::statement("
            CREATE TABLE {$tableName} (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                nik_plaintext VARCHAR(255) NOT NULL,
                nik_ciphertext VARCHAR(255) NOT NULL,
                nama_lengkap VARCHAR(255) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'MENUNGGU_VERIFIKASI'
            );
        ");

        DB::statement("CREATE INDEX idx_bench_nik_plaintext ON {$tableName} (nik_plaintext);");
        DB::statement("CREATE INDEX idx_bench_nik_ciphertext ON {$tableName} (nik_ciphertext);");

        // 2. Seeding 100.000 Data Sintetis dalam Batch
        $this->info("[2/4] Melakukan bulk-seeding " . number_format($records, 0, ',', '.') . " data sintetis terenkripsi deterministik...");
        $startTimeSeed = microtime(true);
        $chunkSize = 5000;
        $chunks = ceil($records / $chunkSize);

        $bar = $this->output->createProgressBar($chunks);
        $bar->start();

        // Tentukan target NIK untuk dicari (berada di posisi ~80% dari dataset untuk merepresentasikan worst/realistic case)
        $targetIndex = (int) ($records * 0.825);
        $targetPlaintextNik = sprintf('3274%012d', $targetIndex);
        $targetCiphertextNik = CryptoHelper::encryptField($targetPlaintextNik);

        for ($c = 0; $c < $chunks; $c++) {
            $data = [];
            $offset = $c * $chunkSize;
            $limit = min($chunkSize, $records - $offset);

            for ($i = 0; $i < $limit; $i++) {
                $idx = $offset + $i + 1;
                $nikPlain = sprintf('3274%012d', $idx);
                $nikCipher = ($nikPlain === $targetPlaintextNik)
                    ? $targetCiphertextNik
                    : CryptoHelper::encryptField($nikPlain);

                $data[] = [
                    'nik_plaintext'  => $nikPlain,
                    'nik_ciphertext' => $nikCipher,
                    'nama_lengkap'   => "Aparatur Sipil Negara {$idx}",
                    'status'         => 'MENUNGGU_VERIFIKASI',
                ];
            }

            DB::table($tableName)->insert($data);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $seedDuration = round(microtime(true) - $startTimeSeed, 2);
        $this->info("✓ Seeding tuntas dalam {$seedDuration} detik.");
        $this->newLine();

        // Jalankan ANALYZE agar statistik optimizer PostgreSQL mutakhir
        DB::statement("ANALYZE {$tableName};");

        // 3. Eksekusi Pengujian 3 Skenario
        $this->info("[3/4] Mengeksekusi pengujian latensi kueri...");

        // Skenario A: Kueri Teks Polos (Plaintext) berindeks B-Tree
        $memStartA = memory_get_peak_usage(true);
        $cpuStartA = getrusage();
        $latenciesA = [];
        for ($q = 0; $q < $queryReps; $q++) {
            $t0 = microtime(true);
            $resA = DB::selectOne("SELECT id, nama_lengkap, nik_plaintext FROM {$tableName} WHERE nik_plaintext = ? LIMIT 1", [$targetPlaintextNik]);
            $latenciesA[] = (microtime(true) - $t0) * 1000;
        }
        $cpuEndA = getrusage();
        $memEndA = memory_get_peak_usage(true);
        $avgLatA = array_sum($latenciesA) / count($latenciesA);
        $cpuTimeA = (($cpuEndA['ru_utime.tv_sec'] - $cpuStartA['ru_utime.tv_sec']) * 1000)
            + (($cpuEndA['ru_utime.tv_usec'] - $cpuStartA['ru_utime.tv_usec']) / 1000);

        // Skenario B: Pencarian Ciphertext Deterministik AES-256-CBC berindeks B-Tree
        $memStartB = memory_get_peak_usage(true);
        $cpuStartB = getrusage();
        $latenciesB = [];
        for ($q = 0; $q < $queryReps; $q++) {
            $t0 = microtime(true);
            // 1. Enkripsi deterministik parameter pencarian
            $searchedCipher = CryptoHelper::encryptField($targetPlaintextNik);
            // 2. Kueri langsung ke indeks B-Tree
            $resB = DB::selectOne("SELECT id, nama_lengkap, nik_ciphertext FROM {$tableName} WHERE nik_ciphertext = ? LIMIT 1", [$searchedCipher]);
            $latenciesB[] = (microtime(true) - $t0) * 1000;
        }
        $cpuEndB = getrusage();
        $memEndB = memory_get_peak_usage(true);
        $avgLatB = array_sum($latenciesB) / count($latenciesB);
        $cpuTimeB = (($cpuEndB['ru_utime.tv_sec'] - $cpuStartB['ru_utime.tv_sec']) * 1000)
            + (($cpuEndB['ru_utime.tv_usec'] - $cpuStartB['ru_utime.tv_usec']) / 1000);

        // Skenario C: In-Memory Full Decryption Scan (Ambil dataset via cursor / streaming lalu dekripsi satu per satu di PHP)
        $this->info("   Menjalankan simulasi In-Memory Full Decryption (5 pengulangan karena intensitas komputasi)...");
        $latenciesC = [];
        $inMemoryReps = 5;
        $memStartC = memory_get_peak_usage(true);
        $cpuStartC = getrusage();

        for ($q = 0; $q < $inMemoryReps; $q++) {
            $t0 = microtime(true);
            $found = null;
            // Baca dataset dan dekripsi di memori aplikasi
            $cursor = DB::table($tableName)->select('id', 'nik_ciphertext')->cursor();
            foreach ($cursor as $row) {
                $decrypted = CryptoHelper::decryptField($row->nik_ciphertext);
                if ($decrypted === $targetPlaintextNik) {
                    $found = $row->id;
                    break;
                }
            }
            $latenciesC[] = (microtime(true) - $t0) * 1000;
        }
        $cpuEndC = getrusage();
        $memEndC = memory_get_peak_usage(true);
        $avgLatC = array_sum($latenciesC) / count($latenciesC);
        $cpuTimeC = ((($cpuEndC['ru_utime.tv_sec'] - $cpuStartC['ru_utime.tv_sec']) * 1000)
            + (($cpuEndC['ru_utime.tv_usec'] - $cpuStartC['ru_utime.tv_usec']) / 1000)) / $inMemoryReps;

        // 4. Bersihkan Tabel Uji
        $this->info("[4/4] Membersihkan tabel benchmark sintetis...");
        Schema::dropIfExists($tableName);
        $this->info("✓ Tabel {$tableName} berhasil dibersihkan (Zero leftover state).");
        $this->newLine();

        // 5. Tampilkan Tabel Rekapitulasi Ilmiah
        $this->info("================================================================================");
        $this->info("                    HASIL EVALUASI TOLOK UKUR RIIL                              ");
        $this->info("================================================================================");

        $headers = ['Skenario Pengujian', 'Kompleksitas', 'Rata-rata Latensi', 'CPU Time / Kueri', 'Puncak Memori'];
        $rows = [
            [
                'Skenario A: Plaintext B-Tree Index',
                'O(log N)',
                sprintf('%.3f ms', $avgLatA),
                sprintf('%.2f ms', $cpuTimeA / $queryReps),
                sprintf('%.2f MB', $memEndA / 1024 / 1024),
            ],
            [
                'Skenario B: AES-256-CBC Deterministik B-Tree',
                'O(log N)',
                sprintf('%.3f ms', $avgLatB),
                sprintf('%.2f ms', $cpuTimeB / $queryReps),
                sprintf('%.2f MB', $memEndB / 1024 / 1024),
            ],
            [
                'Skenario C: In-Memory Full Decryption Scan',
                'O(N)',
                sprintf('%.2f ms', $avgLatC),
                sprintf('%.2f ms', $cpuTimeC),
                sprintf('%.2f MB', $memEndC / 1024 / 1024),
            ],
        ];

        $this->table($headers, $rows);

        $overheadRatio = $avgLatB / max($avgLatA, 0.001);
        $speedupRatio = $avgLatC / max($avgLatB, 0.001);

        $this->info(sprintf("-> Rasio Latensi Deterministik vs Plaintext : %.2fx (Overhead kriptografi lokal)", $overheadRatio));
        $this->info(sprintf("-> Kecepatan Indeks B-Tree vs In-Memory Scan: %.1fx Lebih Cepat", $speedupRatio));
        $this->info("================================================================================");

        return Command::SUCCESS;
    }
}
