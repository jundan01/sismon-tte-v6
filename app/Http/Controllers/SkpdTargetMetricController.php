<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\Models\AuditActionHistory;
use App\Domain\Analytics\Models\SkpdTargetMetric;
use App\Domain\Master\Models\Skpd;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Controller Pengelolaan Target Adopsi TTE SKPD Bulanan.
 * Ref: implementation.md §3.1.D & §4.6.2
 */
class SkpdTargetMetricController extends Controller
{
    /**
     * Tampilkan tabel penetapan target bulanan SKPD.
     */
    public function index(Request $request): View
    {
        if (!auth()->user()?->isSuperadmin()) {
            abort(403, 'Akses Terbatas: Hanya Superadmin yang berwenang mengelola target kinerja SKPD.');
        }

        $tahun = (int) $request->query('tahun', date('Y'));
        $bulan = (int) $request->query('bulan', date('n'));

        // Pastikan range bulan valid 1 - 12
        if ($bulan < 1 || $bulan > 12) {
            $bulan = (int) date('n');
        }

        $skpdList = Skpd::active()
            ->orderBy('nama_skpd')
            ->get();

        $existingMetrics = SkpdTargetMetric::where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->get()
            ->keyBy('skpd_id');

        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return view('admin.target_skpd.index', [
            'skpdList'        => $skpdList,
            'existingMetrics' => $existingMetrics,
            'tahun'           => $tahun,
            'bulan'           => $bulan,
            'namaBulan'       => $namaBulan,
        ]);
    }

    /**
     * Simpan pembaruan target bulanan SKPD.
     */
    public function update(Request $request): RedirectResponse
    {
        if (!auth()->user()?->isSuperadmin()) {
            abort(403, 'Akses Terbatas: Hanya Superadmin yang berwenang mengubah target kinerja SKPD.');
        }

        $validated = $request->validate([
            'tahun'                                    => ['required', 'integer', 'min:2020', 'max:2035'],
            'bulan'                                    => ['required', 'integer', 'min:1', 'max:12'],
            'targets'                                  => ['required', 'array'],
            'targets.*.target_persentase'              => ['required', 'numeric', 'min:0', 'max:100'],
            'targets.*.target_jumlah_pegawai'          => ['required', 'integer', 'min:0'],
        ]);

        $tahun = (int) $validated['tahun'];
        $bulan = (int) $validated['bulan'];

        DB::transaction(function () use ($validated, $tahun, $bulan) {
            $now = now();
            $upsertData = [];

            foreach ($validated['targets'] as $skpdId => $targetData) {
                $upsertData[] = [
                    'skpd_id'               => (int) $skpdId,
                    'tahun'                 => $tahun,
                    'bulan'                 => $bulan,
                    'target_persentase'     => (float) $targetData['target_persentase'],
                    'target_jumlah_pegawai' => (int) $targetData['target_jumlah_pegawai'],
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ];
            }

            if (!empty($upsertData)) {
                SkpdTargetMetric::upsert(
                    $upsertData,
                    ['skpd_id', 'tahun', 'bulan'],
                    ['target_persentase', 'target_jumlah_pegawai', 'updated_at']
                );
            }

            AuditActionHistory::create([
                'action_type'    => 'UPDATE_SKPD_TARGET_METRICS',
                'entity_type'    => 'SkpdTargetMetric',
                'entity_id'      => "TARGET-{$tahun}-{$bulan}",
                'user_id'        => auth()->id(),
                'previous_state' => [],
                'new_state'      => [
                    'tahun'        => $tahun,
                    'bulan'        => $bulan,
                    'total_skpds'  => count($validated['targets']),
                ],
                'is_undone'      => false,
            ]);
        });

        return redirect()->route('admin.target-skpd.index', ['tahun' => $tahun, 'bulan' => $bulan])
            ->with('success', "Target adopsi SKPD untuk periode {$bulan}/{$tahun} berhasil disimpan.");
    }

    /**
     * Terapkan target default (100% capaian sesuai total ASN) untuk seluruh SKPD.
     */
    public function bulkSetDefault(Request $request): RedirectResponse
    {
        if (!auth()->user()?->isSuperadmin()) {
            abort(403, 'Akses Terbatas: Hanya Superadmin yang berwenang mengubah target kinerja SKPD.');
        }

        $validated = $request->validate([
            'tahun' => ['required', 'integer', 'min:2020', 'max:2035'],
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $tahun = (int) $validated['tahun'];
        $bulan = (int) $validated['bulan'];

        $skpdList = Skpd::active()->get();

        DB::transaction(function () use ($skpdList, $tahun, $bulan) {
            $now = now();
            $upsertData = $skpdList->map(fn($skpd) => [
                'skpd_id'               => $skpd->id,
                'tahun'                 => $tahun,
                'bulan'                 => $bulan,
                'target_persentase'     => 100.00,
                'target_jumlah_pegawai' => (int) ($skpd->total_asn ?? 0),
                'created_at'            => $now,
                'updated_at'            => $now,
            ])->all();

            if (!empty($upsertData)) {
                SkpdTargetMetric::upsert(
                    $upsertData,
                    ['skpd_id', 'tahun', 'bulan'],
                    ['target_persentase', 'target_jumlah_pegawai', 'updated_at']
                );
            }

            AuditActionHistory::create([
                'action_type'    => 'BULK_DEFAULT_SKPD_TARGET_METRICS',
                'entity_type'    => 'SkpdTargetMetric',
                'entity_id'      => "TARGET-DEFAULT-{$tahun}-{$bulan}",
                'user_id'        => auth()->id(),
                'previous_state' => [],
                'new_state'      => [
                    'tahun'       => $tahun,
                    'bulan'       => $bulan,
                    'total_skpds' => $skpdList->count(),
                ],
                'is_undone'      => false,
            ]);
        });

        return redirect()->route('admin.target-skpd.index', ['tahun' => $tahun, 'bulan' => $bulan])
            ->with('success', "Target default (100% Kepatuhan) berhasil diterapkan ke seluruh {$skpdList->count()} SKPD aktif.");
    }
}
