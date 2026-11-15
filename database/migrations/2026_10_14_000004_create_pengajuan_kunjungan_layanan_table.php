<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabel permohonan kunjungan layanan asistensi TTE on-site dari SKPD.
     * Ref: implementation.md §3.1.E
     */
    public function up(): void
    {
        Schema::create('pengajuan_kunjungan_layanan', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nomor_tiket', 64)->unique();
            $table->unsignedBigInteger('skpd_id');
            $table->string('nama_pemohon', 255);
            $table->string('jabatan_pemohon', 255);
            $table->string('kontak_pemohon', 30);
            $table->date('tanggal_usulan_kunjungan');
            $table->integer('perkiraan_peserta')->default(1);
            $table->text('catatan_kebutuhan');
            $table->string('file_surat_permohonan', 255)->nullable();
            $table->string('status', 30)->default('MENUNGGU_VERIFIKASI'); // MENUNGGU_VERIFIKASI, DIJADWALKAN, SELESAI, DITOLAK
            $table->text('catatan_petugas')->nullable();
            $table->date('tanggal_kunjungan_final')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign('skpd_id')->references('id')->on('skpd')->cascadeOnDelete();
            $table->index('skpd_id', 'idx_kunjungan_skpd_id');
            $table->index('status', 'idx_kunjungan_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengajuan_kunjungan_layanan');
    }
};
