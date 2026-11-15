<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabel log riwayat aksi administratif & mekanisme pembatalan (Undo).
     * Ref: implementation.md §3.1.F
     */
    public function up(): void
    {
        Schema::create('audit_action_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('action_type', 50); // VERIFIKASI_TAHAP1, PENERBITAN_TAHAP2, TOLAK_PENDAFTARAN, REVOKE_SERTIFIKAT
            $table->string('entity_type', 100); // PendaftaranTte, SertifikatTte
            $table->string('entity_id', 64);
            $table->unsignedBigInteger('user_id');
            $table->jsonb('previous_state');
            $table->jsonb('new_state');
            $table->boolean('is_undone')->default(false);
            $table->unsignedBigInteger('undone_by')->nullable();
            $table->timestampTz('undone_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('undone_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['entity_type', 'entity_id'], 'idx_audit_action_entity');
            $table->index('action_type', 'idx_audit_action_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_action_history');
    }
};
