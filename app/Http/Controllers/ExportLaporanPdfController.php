<?php

namespace App\Http\Controllers;

use App\Domain\Master\Models\Skpd;
use App\Domain\Pendaftaran\Models\PendaftaranTte;
use App\Services\Security\CryptoHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Controller Ekspor Laporan Resmi Berformat PDF.
 * Menghasilkan dokumen cetak resmi berkop Dinas Komunikasi dan Informatika Kota Cirebon,
 * dilengkapi ringkasan statistik eksekutif, tabel data terstruktur, dan blok pengesahan.
 * Ref: implementation.md §4.6.4 (V6.5)
 */
class ExportLaporanPdfController extends Controller
{
    /**
     * Ekspor Laporan Rekapitulasi TTE Resmi Berformat PDF.
     */
    public function exportPdf(Request $request): Response
    {
        $this->authorize('exportLaporan', PendaftaranTte::class);

        $skpdId = $request->input('skpd_id');
        $status = $request->input('status');
        $statusSertifikat = $request->input('status_sertifikat');
        $tipePegawai = $request->input('tipe_pegawai');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $isStream = $request->boolean('stream', false);

        // Identifikasi nama SKPD terpilih
        $selectedSkpd = null;
        if (!empty($skpdId)) {
            $selectedSkpd = Skpd::find($skpdId);
        }

        // Database Query Join Komprehensif
        $query = DB::table('pendaftaran_tte')
            ->leftJoin('skpd', 'pendaftaran_tte.skpd_id', '=', 'skpd.id')
            ->leftJoin('master_jabatan_golongan as jab', 'pendaftaran_tte.jabatan_id', '=', 'jab.id')
            ->leftJoin('master_jabatan_golongan as gol', 'pendaftaran_tte.golongan_id', '=', 'gol.id')
            ->leftJoin('sertifikat_tte', 'pendaftaran_tte.id', '=', 'sertifikat_tte.pendaftaran_id')
            ->select([
                'pendaftaran_tte.id',
                'pendaftaran_tte.nomor_referensi',
                'pendaftaran_tte.tipe_pegawai',
                'pendaftaran_tte.nama_lengkap',
                'pendaftaran_tte.nik',
                'pendaftaran_tte.nip',
                'pendaftaran_tte.no_hp',
                'skpd.nama_skpd',
                'jab.nama_label as nama_jabatan',
                'gol.nama_label as nama_golongan',
                'pendaftaran_tte.status',
                'pendaftaran_tte.email_final_terverifikasi',
                'pendaftaran_tte.email_existing',
                'pendaftaran_tte.email_usulan',
                'pendaftaran_tte.created_at',
                'sertifikat_tte.uuid as sertifikat_uuid',
                'sertifikat_tte.tanggal_penerbitan',
                'sertifikat_tte.tanggal_kadaluarsa',
                'sertifikat_tte.status_sertifikat',
            ])
            ->when($skpdId, fn($q) => $q->where('pendaftaran_tte.skpd_id', $skpdId))
            ->when($status, fn($q) => $q->where('pendaftaran_tte.status', $status))
            ->when($statusSertifikat, fn($q) => $q->where('sertifikat_tte.status_sertifikat', $statusSertifikat))
            ->when($tipePegawai, fn($q) => $q->where('pendaftaran_tte.tipe_pegawai', $tipePegawai))
            ->when($startDate, fn($q) => $q->whereDate('pendaftaran_tte.created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('pendaftaran_tte.created_at', '<=', $endDate))
            ->orderBy('pendaftaran_tte.id', 'asc')
            ->limit(500); // Batas aman alokasi RAM per dokumen cetak PDF (mencegah memory exhaustion)

        $records = $query->get();
        $isCapped = $records->count() >= 500;

        // Hitung ringkasan statistik data yang terfilter
        $totalRecords = $records->count();
        $totalAsn = $records->where('tipe_pegawai', 'ASN')->count();
        $totalNonAsn = $records->where('tipe_pegawai', 'NON_ASN')->count();
        $totalAktif = $records->where('status_sertifikat', 'AKTIF')->count();
        $totalMenunggu = $records->where('status', 'MENUNGGU_VERIFIKASI')->count();
        $totalDisetujui = $records->where('status', 'DISETUJUI')->count();
        $totalDitolak = $records->where('status', 'DITOLAK')->count();

        // Format dan dekripsi/masking identitas demi kepatuhan UU PDP
        $formattedRecords = $records->map(function ($row) {
            $rawNik = CryptoHelper::decryptField($row->nik) ?? $row->nik;
            $rawNip = !empty($row->nip) ? (CryptoHelper::decryptField($row->nip) ?? $row->nip) : null;

            $maskedNik = $rawNik ? (strlen($rawNik) >= 8 ? substr($rawNik, 0, 6) . '******' . substr($rawNik, -4) : $rawNik) : '-';
            $maskedNip = $rawNip ? (strlen($rawNip) >= 8 ? substr($rawNip, 0, 6) . '******' . substr($rawNip, -4) : $rawNip) : '-';

            $emailDinas = $row->email_final_terverifikasi ?: ($row->email_existing ?: ($row->email_usulan ?: '-'));

            return (object) [
                'nomor_referensi'    => $row->nomor_referensi,
                'tipe_pegawai'       => $row->tipe_pegawai ?? 'ASN',
                'nama_lengkap'       => $row->nama_lengkap,
                'masked_nik'         => $maskedNik,
                'masked_nip'         => $maskedNip,
                'nama_skpd'          => $row->nama_skpd ?? '-',
                'nama_jabatan'       => $row->nama_jabatan ?? '-',
                'nama_golongan'      => $row->nama_golongan ?? '-',
                'status_pendaftaran' => $row->status,
                'email_dinas'        => $emailDinas,
                'status_sertifikat'  => $row->status_sertifikat ?? '-',
                'tanggal_terbit'     => $row->tanggal_penerbitan ? Carbon::parse($row->tanggal_penerbitan)->format('d/m/Y') : '-',
                'tanggal_kadaluarsa' => $row->tanggal_kadaluarsa ? Carbon::parse($row->tanggal_kadaluarsa)->format('d/m/Y') : '-',
                'tanggal_daftar'     => $row->created_at ? Carbon::parse($row->created_at)->format('d/m/Y') : '-',
            ];
        });

        $pdfData = [
            'records'          => $formattedRecords,
            'selectedSkpd'     => $selectedSkpd,
            'filterStatus'     => $status,
            'filterSertifikat' => $statusSertifikat,
            'filterTipe'       => $tipePegawai,
            'startDate'        => $startDate ? Carbon::parse($startDate)->isoFormat('D MMMM Y') : null,
            'endDate'          => $endDate ? Carbon::parse($endDate)->isoFormat('D MMMM Y') : null,
            'printedAt'        => Carbon::now()->isoFormat('D MMMM Y, HH:mm') . ' WIB',
            'printedBy'        => auth()->user()?->name ?? 'Administrator Sistem TTE',
            'isCapped'         => $isCapped,
            'summary'          => [
                'total'      => $totalRecords,
                'asn'        => $totalAsn,
                'non_asn'    => $totalNonAsn,
                'aktif'      => $totalAktif,
                'menunggu'   => $totalMenunggu,
                'disetujui'  => $totalDisetujui,
                'ditolak'    => $totalDitolak,
            ],
        ];

        $pdf = Pdf::loadView('admin.export.pdf_laporan', $pdfData)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => false,
                'defaultFont'          => 'sans-serif',
            ]);

        $fileName = 'laporan_resmi_tte_' . ($selectedSkpd ? strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $selectedSkpd->nama_skpd)) . '_' : 'kota_cirebon_') . date('Ymd_His') . '.pdf';

        if ($isStream) {
            return $pdf->stream($fileName);
        }

        return $pdf->download($fileName);
    }
}
