<?php

use App\Http\Controllers\AdminImportHistoricalDataController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CapaianSkpdController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportLaporanController;
use App\Http\Controllers\ExportLaporanPdfController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\MasterJabatanGolonganController;
use App\Http\Controllers\MasterSkpdController;
use App\Http\Controllers\PendaftaranController;
use App\Http\Controllers\PenerbitanController;
use App\Http\Controllers\SkpdTargetMetricController;
use App\Http\Controllers\SystemParameterController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\VerifikasiController;
use Illuminate\Support\Facades\Route;

// ── 1. PORTAL UTAMA & LANDING PAGE INTERAKTIF ───────────────────
Route::get('/', [LandingPageController::class, 'index'])->name('home');

// ── 1A. FORMULIR PENDAFTARAN MANDIRI TTE (ASN & NON-ASN) ────────
Route::get('/formulir', [PendaftaranController::class, 'index'])->name('pendaftaran.index');
Route::post('/formulir', [PendaftaranController::class, 'store'])
    ->middleware('throttle.custom:pendaftaran_limit,5,60')
    ->name('pendaftaran.store');

// Rute Kompatibilitas / Alias
Route::get('/pendaftaran', fn() => redirect()->route('pendaftaran.index', [], 301));
Route::post('/pendaftaran', [PendaftaranController::class, 'store'])
    ->middleware('throttle.custom:pendaftaran_limit,5,60');


// ── 2. AUTENTIKASI & IAM SUPERADMIN (ISOLASI VPN GATEWAY) ────────
Route::middleware(['vpn.gateway'])->prefix('gateway')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle.custom:login_limit,5,60');
    Route::get('/login/mfa', [AuthController::class, 'showMfaForm'])->name('gateway.login.mfa_prompt');
    Route::post('/login/mfa', [AuthController::class, 'verifyMfa'])
        ->middleware('throttle.custom:login_limit,5,60')
        ->name('gateway.login.mfa');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/refresh', [AuthController::class, 'refreshToken'])->name('auth.refresh');
});

// ── 3. PANEL KONTROL SUPERADMIN TERPROTEKSI (VPN GATEWAY + AUTH) ──
Route::middleware(['vpn.gateway', 'auth'])->prefix('admin')->group(function () {
    // A. Dashboard Fast Analytics (Redis Sub-ms)
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::post('/dashboard/refresh', [DashboardController::class, 'refreshStats'])->name('admin.dashboard.refresh');

    // B. Modul Verifikasi Tahap 1 & Rekap Email Dinas
    Route::get('/verifikasi', [VerifikasiController::class, 'index'])->name('admin.verifikasi.index');
    Route::post('/verifikasi/bulk-approve', [VerifikasiController::class, 'bulkApprove'])->name('admin.verifikasi.bulk-approve');
    Route::get('/verifikasi/{nomor_referensi}', [VerifikasiController::class, 'show'])->name('admin.verifikasi.show');
    Route::get('/verifikasi/{nomor_referensi}/file-bukti', [VerifikasiController::class, 'downloadFileBukti'])->name('admin.verifikasi.file-bukti');
    Route::post('/verifikasi/{nomor_referensi}/undo', [VerifikasiController::class, 'undo'])->name('admin.verifikasi.undo');
    Route::post('/verifikasi/{nomor_referensi}/check-collision', [VerifikasiController::class, 'checkCollision'])->name('admin.verifikasi.check-collision');
    Route::post('/verifikasi/{nomor_referensi}/rekap-email', [VerifikasiController::class, 'rekapEmail'])->name('admin.verifikasi.rekap-email');
    Route::post('/verifikasi/{nomor_referensi}/jabatan-golongan', [VerifikasiController::class, 'updateJabatanGolongan'])->name('admin.verifikasi.update-jabatan-golongan');
    Route::post('/verifikasi/{nomor_referensi}/approve', [VerifikasiController::class, 'approve'])->name('admin.verifikasi.approve');
    Route::post('/verifikasi/{nomor_referensi}/reject', [VerifikasiController::class, 'reject'])->name('admin.verifikasi.reject');

    // C. Modul Validasi & Penerbitan Sertifikat Tahap 2
    Route::get('/penerbitan', [PenerbitanController::class, 'index'])->name('admin.penerbitan.index');
    Route::post('/penerbitan/bulk-validate', [PenerbitanController::class, 'bulkValidate'])->name('admin.penerbitan.bulk-validate');
    Route::post('/penerbitan/{nomor_referensi}', [PenerbitanController::class, 'store'])->name('admin.penerbitan.store');
    Route::post('/penerbitan/sertifikat/{id}/status', [PenerbitanController::class, 'updateStatus'])->name('admin.penerbitan.status');
    Route::post('/penerbitan/sertifikat/{id}/undo-revoke', [PenerbitanController::class, 'undoRevoke'])->name('admin.penerbitan.undo-revoke');

    // D. Modul Database Cursor Streaming Export (RAM < 20MB) & Laporan PDF Resmi
    Route::get('/export/csv', [ExportLaporanController::class, 'exportCsv'])->name('admin.export.csv');
    Route::get('/export/pdf', [ExportLaporanPdfController::class, 'exportPdf'])->name('admin.export.pdf');

    // D2. Modul Analitik Capaian Adopsi TTE per SKPD (Seluruh Data + Ekspor CSV & Breakdown Dinamis)
    Route::get('/capaian-skpd', [CapaianSkpdController::class, 'index'])->name('admin.capaian-skpd.index');
    Route::get('/capaian-skpd/export', [CapaianSkpdController::class, 'exportCsv'])->name('admin.capaian-skpd.export');
    Route::get('/capaian-skpd/{id}/breakdown', [CapaianSkpdController::class, 'breakdown'])->name('admin.capaian-skpd.breakdown');
    Route::get('/capaian-skpd/{id}/export-breakdown', [CapaianSkpdController::class, 'exportBreakdownCsv'])->name('admin.capaian-skpd.export-breakdown');

    // D3. Modul Impor Data Historis Pegawai & Sertifikat CSV
    Route::get('/import/historical-data', [AdminImportHistoricalDataController::class, 'index'])->name('admin.import.index');
    Route::get('/import/historical-data/template', [AdminImportHistoricalDataController::class, 'downloadTemplate'])->name('admin.import.template');
    Route::post('/import/historical-data', [AdminImportHistoricalDataController::class, 'store'])->name('admin.import.store');

    // E. Modul Master Data SKPD
    Route::get('/master/skpd', [MasterSkpdController::class, 'index'])->name('admin.master.skpd.index');
    Route::post('/master/skpd', [MasterSkpdController::class, 'store'])->name('admin.master.skpd.store');
    Route::put('/master/skpd/{id}', [MasterSkpdController::class, 'update'])->name('admin.master.skpd.update');
    Route::post('/master/skpd/{id}/toggle', [MasterSkpdController::class, 'toggleStatus'])->name('admin.master.skpd.toggle');

    // F. Modul Master Data Jabatan & Golongan
    Route::get('/master/jabatan-golongan', [MasterJabatanGolonganController::class, 'index'])->name('admin.master.jabatan-golongan.index');
    Route::post('/master/jabatan-golongan', [MasterJabatanGolonganController::class, 'store'])->name('admin.master.jabatan-golongan.store');
    Route::put('/master/jabatan-golongan/{id}', [MasterJabatanGolonganController::class, 'update'])->name('admin.master.jabatan-golongan.update');
    Route::delete('/master/jabatan-golongan/{id}', [MasterJabatanGolonganController::class, 'destroy'])->name('admin.master.jabatan-golongan.destroy');

    // G. Modul User IAM & Setup TOTP MFA
    Route::get('/master/users', [UserManagementController::class, 'index'])->name('admin.master.users.index');
    Route::post('/master/users', [UserManagementController::class, 'store'])->name('admin.master.users.store');
    Route::put('/master/users/{id}', [UserManagementController::class, 'update'])->name('admin.master.users.update');
    Route::delete('/master/users/{id}', [UserManagementController::class, 'destroy'])->name('admin.master.users.destroy');
    Route::get('/mfa/setup', [UserManagementController::class, 'setupMfa'])->name('admin.mfa.setup');
    Route::post('/mfa/confirm', [UserManagementController::class, 'confirmMfa'])->name('admin.mfa.confirm');
    Route::post('/master/users/{id}/disable-mfa', [UserManagementController::class, 'disableMfa'])->name('admin.master.users.disable-mfa');

    // H. Modul Pengelolaan Target Adopsi TTE SKPD Bulanan
    Route::get('/target-skpd', [SkpdTargetMetricController::class, 'index'])->name('admin.target-skpd.index');
    Route::put('/target-skpd', [SkpdTargetMetricController::class, 'update'])->name('admin.target-skpd.update');
    Route::post('/target-skpd/bulk-default', [SkpdTargetMetricController::class, 'bulkSetDefault'])->name('admin.target-skpd.bulk-default');

    // I. Modul Pengaturan Parameter Kebijakan Sistem (Anti Hard-Coding)
    Route::get('/settings/parameters', [SystemParameterController::class, 'index'])->name('admin.settings.parameters');
    Route::put('/settings/parameters', [SystemParameterController::class, 'update'])->name('admin.settings.parameters.update');
});
