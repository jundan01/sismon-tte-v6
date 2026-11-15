<?php

namespace App\Http\Controllers;

use App\Domain\Master\Models\MasterJabatanGolongan;
use App\Domain\Master\Models\Skpd;
use App\Domain\Pendaftaran\Actions\StorePendaftaranAction;
use App\Domain\Pendaftaran\Models\PendaftaranTte;
use App\Domain\Sertifikat\Models\SertifikatTte;
use App\Http\Requests\StorePendaftaranRequest;
use App\Services\SystemParameterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller Form Pendaftaran Mandiri TTE Publik.
 * Ref: PRD.md §7.1, ARCHITECTURE.md §6.1, DESIGN.md §5.1, implementation.md §4.1 & §4.3
 */
class PendaftaranController extends Controller
{
    /**
     * Tampilkan formulir pendaftaran mandiri atau kembalikan data inisialisasi JSON.
     */
    public function index(Request $request): JsonResponse|\Illuminate\View\View|Response
    {
        $skpdList = Skpd::active()
            ->orderBy('nama_skpd')
            ->get(['id', 'kode_skpd', 'nama_skpd']);

        $jabatanList = MasterJabatanGolongan::jabatan()
            ->orderBy('nama_label')
            ->get(['id', 'kode', 'nama_label']);

        $golonganList = MasterJabatanGolongan::golongan()
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama_label']);

        $subUnitList = \App\Domain\Master\Models\SkpdSubUnit::where('is_active', true)
            ->orderBy('nama_sub_unit')
            ->get(['id', 'skpd_id', 'nama_sub_unit', 'kategori_penempatan']);

        $allowNonAsn = (bool) SystemParameterService::get('allow_non_asn_registration', true);

        // Agregat Macro KPI Kota Cirebon untuk Landing Page Publik
        $macroStats = Cache::remember('public_portal_macro_stats', 300, function () {
            $totalPegawai = (int) Skpd::active()->sum('total_asn');
            $totalSertifikat = SertifikatTte::whereIn('status_sertifikat', ['AKTIF', 'WARNING_H7'])->count();
            $totalPendaftaran = PendaftaranTte::count();
            $totalDisetujui = PendaftaranTte::where('status', 'DISETUJUI')->count();
            $totalAntrian = PendaftaranTte::where('status', 'MENUNGGU_VERIFIKASI')->count();
            $totalSkpd = Skpd::active()->count();
            $targetPersentase = (int) SystemParameterService::get('city_adoption_target_percent', 100);
            $persentaseKota = $totalPegawai > 0 ? round(($totalSertifikat / $totalPegawai) * 100, 1) : 0.0;

            return [
                'total_pegawai'     => $totalPegawai,
                'total_sertifikat'  => $totalSertifikat,
                'total_pendaftaran' => $totalPendaftaran,
                'total_disetujui'   => $totalDisetujui,
                'total_antrian'     => $totalAntrian,
                'total_skpd'        => $totalSkpd,
                'target_persentase' => $targetPersentase,
                'persentase_kota'   => $persentaseKota,
            ];
        });

        $macroStats = array_merge([
            'total_pegawai'     => 0,
            'total_sertifikat'  => 0,
            'total_pendaftaran' => 0,
            'total_disetujui'   => 0,
            'total_antrian'     => 0,
            'total_skpd'        => 0,
            'target_persentase' => 100,
            'persentase_kota'   => 0.0,
        ], is_array($macroStats) ? $macroStats : []);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success'        => true,
                'skpd_list'      => $skpdList,
                'sub_unit_list'  => $subUnitList,
                'jabatan_list'   => $jabatanList,
                'golongan_list'  => $golonganList,
                'allow_non_asn'  => $allowNonAsn,
                'macro_stats'    => $macroStats,
            ]);
        }

        return view('public.pendaftaran', [
            'skpdList'      => $skpdList,
            'subUnitList'   => $subUnitList,
            'jabatanList'   => $jabatanList,
            'golonganList'  => $golonganList,
            'allowNonAsn'   => $allowNonAsn,
            'macroStats'    => $macroStats,
        ]);
    }

    /**
     * Proses kirim data pendaftaran mandiri baru.
     */
    public function store(StorePendaftaranRequest $request, StorePendaftaranAction $action): JsonResponse|Response
    {
        $pendaftaran = $action->execute(
            $request->validated()
        );

        $responseMessage = 'Pendaftaran TTE berhasil dikirim. Data Anda sedang dalam proses verifikasi oleh Tim Persandian Diskominfo Kota Cirebon.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success'         => true,
                'message'         => $responseMessage,
                'nomor_referensi' => $pendaftaran->nomor_referensi,
                'status'          => $pendaftaran->status,
                'email_usulan'    => $pendaftaran->email_usulan,
            ], 201);
        }

        return redirect()->route('pendaftaran.index')->with('success', $responseMessage);
    }
}
