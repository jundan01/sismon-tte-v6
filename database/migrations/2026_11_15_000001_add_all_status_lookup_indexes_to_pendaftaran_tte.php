<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Nonaktifkan transaksi agar mendukung CREATE INDEX CONCURRENTLY pada PostgreSQL.
     */
    public bool $withinTransaction = false;

    /**
     * [TODO-PENULIS]: Migrasi indeks universal ini menunggu keputusan final penulis terkait
     * adopsi skema permanen. Untuk implementasi lingkungan produksi dengan volume data masif,
     * pembuatan indeks wajib menggunakan CREATE INDEX CONCURRENTLY tanpa mengunci tabel.
     *
     * Menambahkan indeks B-Tree untuk pencarian exact-match NIK & NIP
     * yang mencakup seluruh status registrasi (termasuk status = 'DITOLAK').
     */
    public function up(): void
    {
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_pendaftaran_nik_all
            ON pendaftaran_tte (nik)
            WHERE nik IS NOT NULL;
        ");

        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_pendaftaran_nip_all
            ON pendaftaran_tte (nip)
            WHERE nip IS NOT NULL;
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_pendaftaran_nik_all;');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_pendaftaran_nip_all;');
    }
};
