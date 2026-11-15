<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabel target adopsi SKPD bulanan.
     * Ref: implementation.md §3.1.D
     */
    public function up(): void
    {
        Schema::create('skpd_target_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('skpd_id');
            $table->integer('tahun');
            $table->integer('bulan');
            $table->decimal('target_persentase', 5, 2)->default(100.00);
            $table->integer('target_jumlah_pegawai')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['skpd_id', 'tahun', 'bulan'], 'idx_skpd_target_unique');
            $table->foreign('skpd_id')->references('id')->on('skpd')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skpd_target_metrics');
    }
};
