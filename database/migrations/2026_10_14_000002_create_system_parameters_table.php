<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabel konfigurasi parameter kebijakan dinamis (anti hard-coding).
     * Ref: implementation.md §3.1.C
     */
    public function up(): void
    {
        Schema::create('system_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('param_key', 100)->unique();
            $table->text('param_value');
            $table->string('param_group', 50)->default('GENERAL');
            $table->string('data_type', 20)->default('STRING'); // STRING, INTEGER, BOOLEAN, JSON
            $table->string('label', 255);
            $table->text('description')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_parameters');
    }
};
