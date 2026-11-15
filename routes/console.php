<?php

use App\Domain\Master\Models\MasterJabatanGolongan;
use App\Domain\Master\Models\Skpd;
use App\Domain\Pendaftaran\Models\PendaftaranTte;
use App\Domain\Sertifikat\Models\SertifikatTte;
use App\Domain\Analytics\Actions\RefreshDailyAggregatorAction;
use App\Jobs\RefreshDailyAggregatorJob;
use App\Jobs\UpdateSertifikatStatusJob;
use App\Models\User;
use Database\Seeders\DummyTestingDataSeeder;
use Database\Seeders\WarningStatusDummySeeder;
use Database\Seeders\MasterJabatanGolonganSeeder;
use Database\Seeders\SkpdSeeder;
use Database\Seeders\SuperadminUserSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

/**
 * Task Scheduler Rutin Sistem Monitoring TTE Skala Nasional.
 * Ref: PRD.md §7.4, ARCHITECTURE.md §10
 */

// 1. Pemindaian status sertifikat harian (H-7 Warning & Expired) pukul 00:01 WIB
Schedule::job(new UpdateSertifikatStatusJob())
    ->dailyAt('00:01')
    ->timezone('Asia/Jakarta')
    ->name('daily-sertifikat-status-scanner');

// 2. Pembaruan fast counter aggregator dashboard setiap 30 menit
Schedule::job(new RefreshDailyAggregatorJob())
    ->everyThirtyMinutes()
    ->name('refresh-dashboard-fast-aggregator');

// 3. Notifikasi email resmi peringatan kedaluwarsa H-7 setiap hari pukul 07:00 WIB
Schedule::command('tte:notify-expiring-h7')
    ->dailyAt('07:00')
    ->timezone('Asia/Jakarta')
    ->name('daily-sertifikat-expiry-notification-h7');

/**
 * Console Commands: Manajemen Data Dummy Testing & Demo
 */

// 3. Masukkan Data Dummy Testing & Peringatan secara Instan
Artisan::command('dummy:seed', function (RefreshDailyAggregatorAction $aggregator) {
    $this->info('Memeriksa data master dasar...');

    if (Skpd::count() === 0) {
        $this->info('Mengisi data master SKPD...');
        $this->call(SkpdSeeder::class);
    }
    if (MasterJabatanGolongan::count() === 0) {
        $this->info('Mengisi data master Jabatan & Golongan...');
        $this->call(MasterJabatanGolonganSeeder::class);
    }
    if (User::count() === 0) {
        $this->info('Mengisi akun default Superadmin...');
        $this->call(SuperadminUserSeeder::class);
    }

    $this->info('Memasukkan data dummy pendaftaran & sertifikat...');
    $this->call(DummyTestingDataSeeder::class);

    $this->info('Memasukkan data dummy seluruh variasi status warning...');
    $this->call(WarningStatusDummySeeder::class);

    // Refresh statistik dashboard & cache Redis
    $aggregator->execute();

    $this->newLine();
    $this->table(
        ['Metrik / Status', 'Jumlah Data'],
        [
            ['Total Pendaftaran TTE', PendaftaranTte::count()],
            ['  ↳ Status: Menunggu Verifikasi', PendaftaranTte::where('status', 'MENUNGGU_VERIFIKASI')->count()],
            ['  ↳ Status: Disetujui (Belum Terbit)', PendaftaranTte::where('status', 'DISETUJUI')->whereDoesntHave('sertifikat')->count()],
            ['  ↳ Status: Ditolak', PendaftaranTte::where('status', 'DITOLAK')->count()],
            ['Total Sertifikat TTE Terbit', SertifikatTte::count()],
            ['  ↳ Sertifikat: Status AKTIF', SertifikatTte::where('status_sertifikat', 'AKTIF')->count()],
            ['  ↳ Sertifikat: Status WARNING H-7', SertifikatTte::where('status_sertifikat', 'WARNING_H7')->count()],
            ['  ↳ Sertifikat: Status WARNING H-3 (Legacy)', SertifikatTte::where('status_sertifikat', 'WARNING_H3')->count()],
            ['  ↳ Sertifikat: Status PERLU_PERPANJANG', SertifikatTte::where('status_sertifikat', 'PERLU_PERPANJANG')->count()],
            ['  ↳ Sertifikat: Status EXPIRED', SertifikatTte::where('status_sertifikat', 'EXPIRED')->count()],
        ]
    );

    $this->info('✔ Berhasil! Seluruh data dummy dan variasi status warning berhasil dimuat.');
})->purpose('Memasukkan data dummy pendaftaran, sertifikat TTE, & variasi status warning');

// 4. Hapus Seluruh Data Dummy / Transaksi Demo secara Instan
Artisan::command('dummy:clear', function (RefreshDailyAggregatorAction $aggregator) {
    $this->warn('Membersihkan seluruh data transaksi demo (pendaftaran, sertifikat, logs, stats)...');

    SertifikatTte::query()->delete();
    PendaftaranTte::query()->delete();
    DB::table('dashboard_daily_stats')->truncate();

    // Reset statistik dashboard & cache Redis ke 0
    $aggregator->execute();

    $this->info('✔ Berhasil! Seluruh data dummy demo telah dibersihkan secara instan.');
    $this->line('<fg=gray>Catatan: Data Master (SKPD, Jabatan, Golongan) dan Akun Superadmin tetap utuh.</>');
})->purpose('Membersihkan seluruh data demo pendaftaran & sertifikat TTE secara instan');

// 5. Reset & Muat Ulang Data Dummy secara Instan
Artisan::command('dummy:reset', function () {
    $this->call('dummy:clear');
    $this->newLine();
    $this->call('dummy:seed');
})->purpose('Membersihkan dan mengisi ulang seluruh data dummy secara instan');
