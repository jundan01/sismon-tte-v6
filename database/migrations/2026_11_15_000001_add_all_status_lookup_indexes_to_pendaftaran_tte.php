<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Menambahkan indeks B-Tree non-unik untuk pencarian exact-match NIK & NIP
     * yang mencakup seluruh status registrasi (termasuk status = 'DITOLAK').
     * Indeks parsial unik sebelumnya (idx_pendaftaran_nik_active & idx_pendaftaran_nip_active)
     * tetap dipertahankan untuk menegakkan aturan integritas bisnis unik pada data aktif.
     */
    public function up(): void
    {
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_pendaftaran_nik_all
            ON pendaftaran_tte (nik)
            WHERE nik IS NOT NULL;
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_pendaftaran_nip_all
            ON pendaftaran_tte (nip)
            WHERE nip IS NOT NULL;
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_pendaftaran_nik_all;');
        DB::statement('DROP INDEX IF EXISTS idx_pendaftaran_nip_all;');
    }
};
