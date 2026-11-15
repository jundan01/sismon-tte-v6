<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\Models\AuditActionHistory;
use App\Domain\Pendaftaran\Actions\ImportHistoricalDataAction;
use App\Domain\Pendaftaran\Models\PendaftaranTte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller Admin Panel Impor Data Historis Pegawai & Sertifikat.
 * Ref: implementation.md §4.2.2 (V6.5)
 */
class AdminImportHistoricalDataController extends Controller
{
    /**
     * Tampilkan formulir impor data historis beserta riwayat eksekusi batch.
     */
    public function index(): View
    {
        $this->authorize('viewAny', PendaftaranTte::class);

        $recentImports = AuditActionHistory::with('user')
            ->where('action_type', 'IMPORT_HISTORICAL_DATA')
            ->latest()
            ->take(10)
            ->get();

        return view('admin.import.index', [
            'recentImports' => $recentImports,
        ]);
    }

    /**
     * Unduh berkas template CSV standar untuk import data historis.
     */
    public function downloadTemplate(): StreamedResponse
    {
        $this->authorize('viewAny', PendaftaranTte::class);

        $filename = 'template_impor_historis_tte.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            // Tambahkan BOM UTF-8
            fputs($handle, "\xEF\xBB\xBF");

            // Header standar
            fputcsv($handle, [
                'nama_lengkap',
                'nik',
                'nip',
                'tipe_pegawai',
                'skpd',
                'no_hp',
                'email_dinas',
                'tanggal_penerbitan',
                'status_sertifikat',
            ]);

            // Baris Contoh 1: ASN dengan Sertifikat Aktif
            fputcsv($handle, [
                'Ahmad Fauzi, S.Kom',
                '3274011205880001',
                '198805122015031001',
                'ASN',
                'Dinas Komunikasi dan Informatika',
                '081234567890',
                'ahmad.fauzi@cirebonkota.go.id',
                '2025-06-15',
                'AKTIF',
            ]);

            // Baris Contoh 2: Non-ASN (THL) Tanpa NIP
            fputcsv($handle, [
                'Budi Santoso',
                '3274021508920002',
                '',
                'NON_ASN',
                'Badan Pengelola Keuangan dan Pendapatan Daerah',
                '085678901234',
                'budi.santoso@cirebonkota.go.id',
                '2025-08-20',
                'AKTIF',
            ]);

            // Baris Contoh 3: ASN Pengajuan Baru (Belum Terbit Sertifikat)
            fputcsv($handle, [
                'Dewi Lestari, S.AP',
                '3274032204910003',
                '199104222019032002',
                'ASN',
                'Dinas Kependudukan dan Pencatatan Sipil',
                '087812345678',
                '',
                '',
                '',
            ]);

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Unggah dan proses berkas CSV data historis.
     */
    public function store(Request $request, ImportHistoricalDataAction $importer): RedirectResponse
    {
        $this->authorize('create', PendaftaranTte::class);

        $request->validate([
            'file_csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'], // Max 10MB
            'dry_run'  => ['nullable', 'boolean'],
        ], [
            'file_csv.required' => 'Harap pilih berkas CSV yang akan diimpor.',
            'file_csv.mimes'    => 'Berkas harus berupa file berekstensi .csv atau .txt dengan pemisah koma/titik koma.',
            'file_csv.max'      => 'Ukuran berkas CSV maksimal 10 Megabytes.',
        ]);

        $file = $request->file('file_csv');
        $isDryRun = $request->boolean('dry_run', false);
        $userId = auth()->id() ?? 1;

        try {
            $result = $importer->execute($file->getRealPath(), $userId, $isDryRun);

            $msg = $isDryRun ? "Simulasi selesai: " : "Impor data berhasil diproses: ";
            $msg .= "{$result['imported_count']} data " . ($isDryRun ? "valid siap diimpor" : "berhasil disimpan") . ", ";
            $msg .= "{$result['skipped_count']} data dilewati (NIK sudah terdaftar), ";
            $msg .= "{$result['error_count']} galat.";

            if (!empty($result['errors'])) {
                session()->flash('import_errors', array_slice($result['errors'], 0, 15));
            }

            if (!empty($result['skipped'])) {
                session()->flash('import_skipped', array_slice($result['skipped'], 0, 15));
            }

            return redirect()->route('admin.import.index')->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->route('admin.import.index')->with('error', 'Gagal memproses berkas impor: ' . $e->getMessage());
        }
    }
}
