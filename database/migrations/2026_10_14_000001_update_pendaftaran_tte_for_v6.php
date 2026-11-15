<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Pembaruan tabel `pendaftaran_tte` untuk mendukung:
     * 1. Jalur Dual-Track (tipe_pegawai: 'ASN' | 'NON_ASN')
     * 2. NIP bersifat nullable bagi pegawai Non-ASN
     * 3. Penyimpanan path berkas bukti kepemilikan TTE aktif sebelumnya (file_bukti_tte)
     * Ref: implementation.md §3.1.A
     */
    public function up(): void
    {
        Schema::table('pendaftaran_tte', function (Blueprint $table) {
            if (!Schema::hasColumn('pendaftaran_tte', 'tipe_pegawai')) {
                $table->string('tipe_pegawai', 20)->default('ASN')->after('nomor_referensi');
            }
            if (!Schema::hasColumn('pendaftaran_tte', 'file_bukti_tte')) {
                $table->string('file_bukti_tte', 255)->nullable()->after('no_hp');
            }
            // Ubah nip menjadi nullable untuk Non-ASN
            $table->string('nip', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pendaftaran_tte', function (Blueprint $table) {
            if (Schema::hasColumn('pendaftaran_tte', 'tipe_pegawai')) {
                $table->dropColumn('tipe_pegawai');
            }
            if (Schema::hasColumn('pendaftaran_tte', 'file_bukti_tte')) {
                $table->dropColumn('file_bukti_tte');
            }
            $table->string('nip', 255)->nullable(false)->change();
        });
    }
};
