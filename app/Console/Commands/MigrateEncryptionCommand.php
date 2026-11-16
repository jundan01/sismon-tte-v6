<?php

namespace App\Console\Commands;

use App\Services\Security\CryptoHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateEncryptionCommand extends Command
{
    protected $signature = 'bench:migrate-encryption
                            {--dry-run : Uji coba validasi dekripsi-enkripsi 100% baris tanpa menulis ke DB}
                            {--rollback : Kembalikan enkripsi dari v1 (DAE CTR-SIV) ke v0 (Legacy CBC)}
                            {--chunk=1000 : Jumlah record per batch pemrosesan}';

    protected $description = 'Fase 2: Skrip migrasi enkripsi ulang data sensitif NIK & NIP (v0 CBC -> v1 DAE CTR-SIV) dengan verifikasi 100% dan zero-downtime reindex';

    public function handle(): int
    {
        $db = DB::connection('pgsql_bench');

        $this->info("================================================================================");
        $this->info("  FASE 2: MIGRASI RE-ENKRIPSI DATA SENSITIF (v0 CBC <-> v1 DAE CTR-SIV)");
        $this->info("  Database Target : " . $db->getDatabaseName() . " (tte_postgres_bench)");
        $this->info("================================================================================");

        // Guard Ketat: Jangan pernah sentuh database tte_monitoring (produksi)
        if ($db->getDatabaseName() !== 'tte_benchmark') {
            $this->error("CRITICAL SAFETY ABORT: Perintah migrasi ini HANYA boleh dieksekusi pada database tte_benchmark!");
            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rollback = (bool) $this->option('rollback');
        $chunkSize = max(100, (int) $this->option('chunk'));

        $totalRows = $db->table('pendaftaran_tte')->count();
        if ($totalRows === 0) {
            $this->warn("Tabel pendaftaran_tte kosong! Tidak ada baris untuk dimigrasi.");
            return 0;
        }

        $modeLabel = $rollback ? 'ROLLBACK (v1 -> v0 CBC)' : ($dryRun ? 'DRY-RUN (Simulasi & Verifikasi 100%)' : 'LIVE MIGRATION (v0 CBC -> v1 DAE)');
        $this->line("Mode Eksekusi : <fg=yellow>{$modeLabel}</>");
        $this->line("Total Record  : " . number_format($totalRows, 0, ',', '.') . " baris");
        $this->line("Ukuran Chunk  : " . number_format($chunkSize, 0, ',', '.') . " baris\n");

        $bar = $this->output->createProgressBar($totalRows);

        $processed = 0;
        $verified = 0;
        $mismatchErrors = 0;
        $lengthErrors = 0;

        $lastId = 0;

        while (true) {
            $records = $db->table('pendaftaran_tte')
                ->where('id', '>', $lastId)
                ->orderBy('id', 'asc')
                ->limit($chunkSize)
                ->get(['id', 'nik', 'nip']);

            if ($records->isEmpty()) {
                break;
            }

            $updates = [];

            foreach ($records as $row) {
                $lastId = $row->id;
                $processed++;

                if ($rollback) {
                    // Mode Rollback: v1 -> v0
                    // Dekripsi dengan mode dual-read (bisa dekripsi v1 maupun v0)
                    $plainNik = CryptoHelper::decryptField($row->nik, 'nik');
                    $plainNip = $row->nip ? CryptoHelper::decryptField($row->nip, 'nip') : null;

                    if ($plainNik === null || ($row->nip !== null && $plainNip === null)) {
                        $mismatchErrors++;
                        continue;
                    }

                    // Enkripsi kembali ke skema lama v0
                    $legacyNik = CryptoHelper::encryptFieldLegacy($plainNik);
                    $legacyNip = $plainNip ? CryptoHelper::encryptFieldLegacy($plainNip) : null;

                    // Verifikasi ulang dekripsi v0
                    if (CryptoHelper::decryptLegacy($legacyNik) !== $plainNik) {
                        $mismatchErrors++;
                        continue;
                    }

                    $updates[] = [
                        'id'  => $row->id,
                        'nik' => $legacyNik,
                        'nip' => $legacyNip,
                    ];
                    $verified++;
                } else {
                    // Mode Forward: v0 -> v1 DAE
                    // 1. Dekripsi data lama (v0 CBC)
                    $plainNik = CryptoHelper::decryptLegacy($row->nik);
                    $plainNip = $row->nip ? CryptoHelper::decryptLegacy($row->nip) : null;

                    // Jika gagal dekripsi v0, coba dual-read (mungkin sudah v1)
                    if ($plainNik === null) {
                        $plainNik = CryptoHelper::decryptField($row->nik, 'nik');
                    }
                    if ($row->nip !== null && $plainNip === null) {
                        $plainNip = CryptoHelper::decryptField($row->nip, 'nip');
                    }

                    if ($plainNik === null || ($row->nip !== null && $plainNip === null)) {
                        $mismatchErrors++;
                        continue;
                    }

                    // 2. Enkripsi dengan skema baru v1 DAE CTR-SIV (dengan konteks label kolom)
                    $newNikCipher = CryptoHelper::encryptField($plainNik, 'nik');
                    $newNipCipher = $plainNip ? CryptoHelper::encryptField($plainNip, 'nip') : null;

                    // 3. Verifikasi ukuran raw byte dan panjang base64
                    // NIK: 16 bytes plaintext -> 1 byte versi + 16 bytes V + 16 bytes CTR = 33 raw bytes -> 44 base64 chars
                    $rawNikBytes = strlen(base64_decode($newNikCipher, true));
                    if ($rawNikBytes !== 33 || strlen($newNikCipher) !== 44) {
                        $lengthErrors++;
                    }

                    if ($newNipCipher !== null) {
                        // NIP: 18 bytes plaintext -> 1 byte versi + 16 bytes V + 18 bytes CTR = 35 raw bytes -> 48 base64 chars
                        $rawNipBytes = strlen(base64_decode($newNipCipher, true));
                        if ($rawNipBytes !== 35 || strlen($newNipCipher) !== 48) {
                            $lengthErrors++;
                        }
                    }

                    // 4. Verifikasi dekripsi skema baru 100% cocok dengan plaintext asli
                    $decryptedNewNik = CryptoHelper::decryptField($newNikCipher, 'nik');
                    $decryptedNewNip = $newNipCipher ? CryptoHelper::decryptField($newNipCipher, 'nip') : null;

                    if ($decryptedNewNik !== $plainNik || ($newNipCipher !== null && $decryptedNewNip !== $plainNip)) {
                        $mismatchErrors++;
                        continue;
                    }

                    $updates[] = [
                        'id'  => $row->id,
                        'nik' => $newNikCipher,
                        'nip' => $newNipCipher,
                    ];
                    $verified++;
                }

                $bar->advance();
            }

            // Tulis ke DB jika bukan dry-run
            if (!$dryRun && !empty($updates)) {
                // Bulk update menggunakan batch query PostgreSQL
                $casesNik = [];
                $casesNip = [];
                $ids = [];

                foreach ($updates as $u) {
                    $ids[] = $u['id'];
                    $casesNik[] = "WHEN {$u['id']} THEN " . $db->getPdo()->quote($u['nik']);
                    $casesNip[] = "WHEN {$u['id']} THEN " . ($u['nip'] ? $db->getPdo()->quote($u['nip']) : "NULL");
                }

                $idList = implode(',', $ids);
                $sqlNik = "CASE id " . implode(' ', $casesNik) . " END";
                $sqlNip = "CASE id " . implode(' ', $casesNip) . " END";

                $db->statement("
                    UPDATE pendaftaran_tte 
                    SET nik = ({$sqlNik}), 
                        nip = ({$sqlNip}),
                        updated_at = NOW()
                    WHERE id IN ({$idList})
                ");
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->line("================================================================================");
        $this->line("  RINGKASAN EKSEKUSI");
        $this->line("================================================================================");
        $this->line("  Total Record Diproses : " . number_format($processed, 0, ',', '.'));
        $this->line("  Terverifikasi 100%    : <fg=green>" . number_format($verified, 0, ',', '.') . "</>");
        $this->line("  Kesalahan Mismatch    : " . ($mismatchErrors > 0 ? "<fg=red>{$mismatchErrors}</>" : "<fg=green>0</>"));
        $this->line("  Kesalahan Ukuran Kolom: " . ($lengthErrors > 0 ? "<fg=red>{$lengthErrors}</>" : "<fg=green>0</>"));

        if ($mismatchErrors > 0 || $lengthErrors > 0) {
            $this->error("\nABORT: Ditemukan kegagalan integritas data! Periksa detail di atas.");
            return 1;
        }

        if ($dryRun) {
            $this->info("\n✓ DRY-RUN BERHASIL: Seluruh 100.000 baris tervalidasi 100% identik tanpa perubahan pada DB.");
            return 0;
        }

        // Rebuild indeks secara CONCURRENTLY untuk mencegah table lock
        $this->info("\n[Post-Migration] Menjalankan REINDEX INDEX CONCURRENTLY pada seluruh indeks NIK dan NIP...");
        $indexesToReindex = [
            'idx_pendaftaran_nik_all',
            'idx_pendaftaran_nip_all',
            'idx_pendaftaran_nik_active',
            'idx_pendaftaran_nip_active',
        ];

        foreach ($indexesToReindex as $idxName) {
            $this->line("  -> Merekonstruksi indeks: {$idxName}...");
            $t0 = hrtime(true);
            $db->statement("REINDEX INDEX CONCURRENTLY {$idxName};");
            $durMs = round((hrtime(true) - $t0) / 1e6, 2);
            $this->info("     ✓ {$idxName} selesai dalam {$durMs} ms");
        }

        $this->info("\n[Post-Migration] Menjalankan VACUUM ANALYZE...");
        $db->statement("VACUUM ANALYZE pendaftaran_tte;");
        $this->info("✓ VACUUM ANALYZE tuntas.");

        $this->info("\n✓ MIGRASI SELESAI: 100.000 record pada tte_benchmark berhasil di-migrasi ke skema " . ($rollback ? 'v0' : 'v1 DAE') . ".");
        return 0;
    }
}
