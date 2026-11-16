<?php

namespace App\Console\Commands;

use App\Benchmark\Security\AesGcmHelper;
use App\Benchmark\Security\BlindIndexService;
use App\Services\Security\CryptoHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunPhase3SearchWorker extends Command
{
    protected $signature = 'bench:phase3-worker
                            {--scale=1k : Skala dataset (1k, 10k, 100k)}
                            {--scenario=A : Skenario pencarian (A, B, C, D)}
                            {--targets= : JSON array berisi daftar target NIK plaintext}';

    protected $description = 'Worker proses terisolasi untuk eksekusi pencarian benchmark Fase 3 (mengukur memory peak & CPU murni per skenario)';

    public function handle(): int
    {
        $db = DB::connection('pgsql_bench');

        if ($db->getDatabaseName() !== 'tte_benchmark') {
            $this->error(json_encode(['error' => 'Database target bukan tte_benchmark']));
            return 1;
        }

        $scale = strtolower($this->option('scale'));
        $scenario = strtoupper($this->option('scenario'));
        $targetsJson = $this->option('targets');
        $targets = json_decode($targetsJson, true);

        if (!is_array($targets) || empty($targets)) {
            $this->error(json_encode(['error' => 'Parameter targets tidak valid']));
            return 1;
        }

        // 1. Fase Warm-up (5 iterasi pertama dibuang dari statistik)
        $warmupCount = 5;
        $warmTarget = $targets[0];
        for ($w = 0; $w < $warmupCount; $w++) {
            if ($scenario === 'A') {
                $db->selectOne("SELECT id, nama_lengkap, nik, status FROM bench_plain_{$scale} WHERE nik = ? AND status = 'MENUNGGU_VERIFIKASI'", [$warmTarget]);
            } elseif ($scenario === 'B') {
                $c = CryptoHelper::encryptField($warmTarget, 'nik');
                $db->selectOne("SELECT id, nama_lengkap, nik, status FROM bench_dae_{$scale} WHERE nik = ? AND status = 'MENUNGGU_VERIFIKASI'", [$c]);
            } elseif ($scenario === 'C') {
                $cursor = $db->table("bench_bidx_{$scale}")->select('id', 'nik_gcm')->limit(100)->cursor();
                foreach ($cursor as $row) {
                    AesGcmHelper::decrypt($row->nik_gcm);
                }
            } elseif ($scenario === 'D') {
                $bidx = BlindIndexService::generate($warmTarget);
                $row = $db->selectOne("SELECT id, nama_lengkap, nik_gcm, status FROM bench_bidx_{$scale} WHERE nik_bidx = ? AND status = 'MENUNGGU_VERIFIKASI'", [$bidx]);
                if ($row) {
                    AesGcmHelper::decrypt($row->nik_gcm);
                }
            }
        }

        // 2. Fase Pengukuran Terisolasi
        $results = [];
        $trialIndex = 1;

        foreach ($targets as $targetNik) {
            if (function_exists('memory_reset_peak_usage')) {
                memory_reset_peak_usage();
            }

            $cpuStart = getrusage();
            $t0 = hrtime(true);
            $rowsReturned = 0;

            if ($scenario === 'A') {
                // Skenario A: Plaintext + B-Tree Index
                $res = $db->selectOne("SELECT id, nama_lengkap, nik, status FROM bench_plain_{$scale} WHERE nik = ? AND status = 'MENUNGGU_VERIFIKASI'", [$targetNik]);
                $rowsReturned = $res ? 1 : 0;
            } elseif ($scenario === 'B') {
                // Skenario B: New DAE v1 (CTR-SIV) + B-Tree Index (termasuk enkripsi parameter lokal)
                $cipher = CryptoHelper::encryptField($targetNik, 'nik');
                $res = $db->selectOne("SELECT id, nama_lengkap, nik, status FROM bench_dae_{$scale} WHERE nik = ? AND status = 'MENUNGGU_VERIFIKASI'", [$cipher]);
                $rowsReturned = $res ? 1 : 0;
            } elseif ($scenario === 'C') {
                // Skenario C: Probabilistic AES-256-GCM + In-Memory Decryption Scan
                $cursor = $db->table("bench_bidx_{$scale}")->select('id', 'nik_gcm')->cursor();
                $foundId = null;
                foreach ($cursor as $row) {
                    $decrypted = AesGcmHelper::decrypt($row->nik_gcm);
                    if ($decrypted === $targetNik) {
                        $foundId = $row->id;
                        break;
                    }
                }
                $rowsReturned = ($foundId !== null) ? 1 : 0;
            } elseif ($scenario === 'D') {
                // Skenario D: Probabilistic AES-256-GCM + HMAC Blind Index (hitung blind index + SQL lookup + dekripsi baris)
                $bidx = BlindIndexService::generate($targetNik);
                $res = $db->selectOne("SELECT id, nama_lengkap, nik_gcm, status FROM bench_bidx_{$scale} WHERE nik_bidx = ? AND status = 'MENUNGGU_VERIFIKASI'", [$bidx]);
                if ($res) {
                    $decrypted = AesGcmHelper::decrypt($res->nik_gcm);
                    $rowsReturned = ($decrypted === $targetNik) ? 1 : 0;
                } else {
                    $rowsReturned = 0;
                }
            }

            $t1 = hrtime(true);
            $cpuEnd = getrusage();
            $peakMemBytes = memory_get_peak_usage(true);

            $wallMs = round(($t1 - $t0) / 1e6, 3);
            $cpuMs = round(
                ((($cpuEnd['ru_utime.tv_sec'] - $cpuStart['ru_utime.tv_sec']) + ($cpuEnd['ru_stime.tv_sec'] - $cpuStart['ru_stime.tv_sec'])) * 1000.0)
                + ((($cpuEnd['ru_utime.tv_usec'] - $cpuStart['ru_utime.tv_usec']) + ($cpuEnd['ru_stime.tv_usec'] - $cpuStart['ru_stime.tv_usec'])) / 1000.0),
                3
            );
            $peakMemMb = round($peakMemBytes / (1024.0 * 1024.0), 2);

            $results[] = [
                'trial'         => $trialIndex++,
                'target_id'     => $targetNik,
                'wall_ms'       => $wallMs,
                'php_cpu_ms'    => $cpuMs,
                'peak_mem_mb'   => $peakMemMb,
                'rows_returned' => $rowsReturned,
            ];
        }

        // Kembalikan JSON terstruktur ke standard output
        $this->line("__WORKER_RESULT_START__" . json_encode($results) . "__WORKER_RESULT_END__");
        return 0;
    }
}
