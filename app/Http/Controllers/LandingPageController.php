<?php

namespace App\Http\Controllers;

use App\Domain\Master\Models\Skpd;
use App\Domain\Pendaftaran\Models\PendaftaranTte;
use App\Domain\Sertifikat\Models\SertifikatTte;
use App\Services\SystemParameterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Controller Landing Page Interaktif Portal Monitoring TTE & Pelayanan Email Dinas.
 * Menampilkan ringkasan eksekutif, live counter metrik, alur layanan, transparansi capaian, dan kanal aksi publik.
 */
class LandingPageController extends Controller
{
    /**
     * Tampilkan Landing Page Interaktif.
     */
    public function index(Request $request): View
    {
        // 1. Ambil Agregat Metrik Makro Kota Cirebon (Cache 5 Menit)
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

        // Pastikan default key lengkap untuk mencegah undefined array key dari cache yang belum konsisten
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

        // 2. Ambil Seluruh Data SKPD dengan Capaian untuk Widget Transparansi & Pencarian Interaktif
        $allSkpdList = Cache::remember('landing_all_skpd_list', 300, function () {
            return DB::table('skpd')
                ->leftJoin('pendaftaran_tte', 'skpd.id', '=', 'pendaftaran_tte.skpd_id')
                ->select(
                    'skpd.id',
                    'skpd.nama_skpd',
                    'skpd.kode_skpd',
                    'skpd.total_asn',
                    DB::raw("COUNT(pendaftaran_tte.id) as total_pendaftar"),
                    DB::raw("COUNT(CASE WHEN pendaftaran_tte.status = 'DISETUJUI' THEN 1 END) as total_disetujui"),
                    DB::raw("CASE WHEN COALESCE(skpd.total_asn, 0) > 0 THEN ROUND((COUNT(CASE WHEN pendaftaran_tte.status = 'DISETUJUI' THEN 1 END)::numeric / skpd.total_asn) * 100, 1) ELSE 0 END as persentase_capaian")
                )
                ->where('skpd.is_active', true)
                ->where('skpd.total_asn', '>', 0)
                ->groupBy('skpd.id', 'skpd.nama_skpd', 'skpd.kode_skpd', 'skpd.total_asn')
                ->orderByDesc('persentase_capaian')
                ->orderByDesc('total_disetujui')
                ->get();
        });

        // Hitung Trio Kategori Kepatuhan
        $optimalCount = 0;
        $progresifCount = 0;
        $akselerasiCount = 0;
        foreach ($allSkpdList as $item) {
            $val = (float) $item->persentase_capaian;
            if ($val >= 80) {
                $optimalCount++;
            } elseif ($val >= 50) {
                $progresifCount++;
            } else {
                $akselerasiCount++;
            }
        }

        $macroStats['count_optimal'] = $optimalCount;
        $macroStats['count_progresif'] = $progresifCount;
        $macroStats['count_akselerasi'] = $akselerasiCount;

        $topSkpdList = $allSkpdList->take(5);
        $allowNonAsn = (bool) SystemParameterService::get('allow_non_asn_registration', true);

        return view('public.landing', [
            'macroStats'   => $macroStats,
            'allSkpdList'  => $allSkpdList,
            'topSkpdList'  => $topSkpdList,
            'allowNonAsn'  => $allowNonAsn,
        ]);
    }
}
