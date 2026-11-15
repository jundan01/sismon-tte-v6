<?php

namespace App\Http\Controllers;

use App\Domain\Master\Models\MasterJabatanGolongan;
use App\Domain\Master\Models\Skpd;
use App\Domain\Pendaftaran\Actions\ApproveTahap1Action;
use App\Domain\Pendaftaran\Actions\BulkVerifikasiAction;
use App\Domain\Pendaftaran\Actions\RejectPendaftaranAction;
use App\Domain\Pendaftaran\Actions\RekapEmailDinasAction;
use App\Domain\Pendaftaran\Actions\UndoVerifikasiAction;
use App\Domain\Pendaftaran\Actions\UpdateJabatanGolonganAction;
use App\Domain\Pendaftaran\Models\PendaftaranTte;
use App\Http\Requests\RekapEmailDinasRequest;
use App\Http\Requests\UpdateJabatanGolonganRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller Panel Antrian Verifikasi Tahap 1 & Rekap Email Dinas Superadmin.
 * Ref: PRD.md §7.2, ARCHITECTURE.md §6.2, DESIGN.md §7
 */
class VerifikasiController extends Controller
{
    /**
     * Tampilkan antrian verifikasi pendaftaran ASN (Tahap 1) dengan urutan FIFO.
     */
    public function index(Request $request): JsonResponse|\Illuminate\View\View|Response
    {
        $this->authorize('viewAny', PendaftaranTte::class);

        $status = $request->input('status', 'MENUNGGU_VERIFIKASI');
        $skpdId = $request->input('skpd_id');
        $search = $request->input('q');
        $deskripsi = $request->input('deskripsi');

        $pendaftaranList = PendaftaranTte::with(['skpd', 'jabatan', 'golongan'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($skpdId, fn($q) => $q->where('skpd_id', $skpdId))
            ->when($deskripsi, fn($q, $d) => $q->where('deskripsi', 'ilike', '%' . addcslashes(trim($d), '%_\\') . '%'))
            ->when($search, function ($q, $search) {
                $trimmed = trim($search);
                $digitsOnly = preg_replace('/\D/', '', $trimmed);

                // Jalur 1: Pencarian Identitas Sensitif Terenkripsi (Exact-Match via B-Tree Index)
                // Memisahkan NIK (16 digit) dan NIP (18 digit) agar terhindar dari Seq Scan pada compound OR
                if (strlen($digitsOnly) === 16 && preg_match('/^[\d\s\.\-]+$/', $trimmed)) {
                    $encryptedNik = \App\Services\Security\CryptoHelper::encryptField($digitsOnly);
                    $q->where('nik', $encryptedNik);
                } elseif (strlen($digitsOnly) === 18 && preg_match('/^[\d\s\.\-]+$/', $trimmed)) {
                    $encryptedNip = \App\Services\Security\CryptoHelper::encryptField($digitsOnly);
                    $q->where('nip', $encryptedNip);
                } else {
                    // Jalur 2: Pencarian Teks / Deskriptif Non-Eksak (Nama, Nomor Referensi, Deskripsi)
                    // Dilakukan escaping pada karakter wildcard SQL (%, _, \) untuk mencegah wildcard injection
                    $escaped = addcslashes($trimmed, '%_\\');
                    $q->where(function ($subQ) use ($escaped) {
                        $subQ->where('nama_lengkap', 'ilike', "%{$escaped}%")
                             ->orWhere('nomor_referensi', 'ilike', "%{$escaped}%")
                             ->orWhere('deskripsi', 'ilike', "%{$escaped}%");
                    });
                }
            })
            ->orderBy('created_at', 'asc') // FIFO
            ->paginate(15)
            ->withQueryString();

        $skpdList = Skpd::active()->orderBy('nama_skpd')->get(['id', 'kode_skpd', 'nama_skpd']);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success'   => true,
                'data'      => $pendaftaranList,
                'skpd_list' => $skpdList,
            ]);
        }

        return view('admin.verifikasi.index', [
            'pendaftaranList' => $pendaftaranList,
            'skpdList'        => $skpdList,
            'currentStatus'   => $status,
        ]);
    }

    /**
     * Tampilkan detail data pendaftaran ASN untuk verifikasi mendalam.
     * Menerima nomor_referensi resmi (misal: TTE-20260819-TS0002) atau ID.
     */
    public function show(Request $request, string|int $id): JsonResponse|\Illuminate\View\View|Response
    {
        $pendaftaran = PendaftaranTte::with(['skpd', 'jabatan', 'golongan', 'verifikator'])
            ->where('nomor_referensi', $id)
            ->orWhere('id', is_numeric($id) ? (int)$id : 0)
            ->firstOrFail();

        // Enforce canonical URL: Jika URL diakses dengan ID numerik murni (/admin/verifikasi/1),
        // otomatis redirect ke URL resmi berbasis nomor_referensi (/admin/verifikasi/TTE-xxx)
        if (is_numeric($id) && $id !== $pendaftaran->nomor_referensi) {
            if (!request()->expectsJson() && !request()->is('api/*')) {
                return redirect()->route('admin.verifikasi.show', $pendaftaran->nomor_referensi);
            }
        }

        $this->authorize('view', $pendaftaran);

        $jabatanList = MasterJabatanGolongan::jabatan()
            ->orderBy('nama_label')
            ->get(['id', 'kode', 'nama_label']);

        $golonganList = MasterJabatanGolongan::golongan()
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama_label']);

        if (request()->expectsJson() || request()->is('api/*')) {
            return response()->json([
                'success'       => true,
                'data'          => $pendaftaran,
                'jabatan_list'  => $jabatanList,
                'golongan_list' => $golonganList,
            ]);
        }

        return view('admin.verifikasi.show', [
            'pendaftaran'  => $pendaftaran,
            'jabatanList'  => $jabatanList,
            'golonganList' => $golonganList,
        ]);
    }

    /**
     * Aksi Update / Hapus (Kosongkan) Jabatan & Golongan ASN.
     */
    public function updateJabatanGolongan(
        UpdateJabatanGolonganRequest $request,
        string|int $id,
        UpdateJabatanGolonganAction $action
    ): JsonResponse|Response
    {
        $pendaftaran = $this->findPendaftaran($id);

        $this->authorize('updateJabatanGolongan', $pendaftaran);

        $updated = $action->execute(
            $pendaftaran,
            $request->filled('jabatan_id') ? (int)$request->input('jabatan_id') : null,
            $request->filled('golongan_id') ? (int)$request->input('golongan_id') : null,
        );

        $message = 'Jabatan dan Golongan ASN berhasil diperbarui.';

        if ($request->expectsJson() || $request->is('api/*') || $request->ajax()) {
            return response()->json([
                'success'       => true,
                'message'       => $message,
                'data'          => $updated,
                'jabatan_nama'  => $updated->jabatan?->nama_label ?? '-',
                'golongan_nama' => $updated->golongan ? ($updated->golongan->nama_label . ' (' . $updated->golongan->kode . ')') : '-',
            ]);
        }

        return redirect()->route('admin.verifikasi.show', $pendaftaran->nomor_referensi)->with('success', $message);
    }

    /**
     * Aksi AJAX: Rekap & Kunci Email Dinas Definitif.
     */
    public function rekapEmail(RekapEmailDinasRequest $request, string|int $id, RekapEmailDinasAction $action): JsonResponse
    {
        $pendaftaran = $this->findPendaftaran($id);
        
        $this->authorize('rekapEmailDinas', $pendaftaran);

        $updated = $action->execute(
            $pendaftaran,
            $request->input('email_final_terverifikasi')
        );

        return response()->json([
            'success'                   => true,
            'message'                   => 'Penetapan email dinas definitif berhasil direkap.',
            'email_final_terverifikasi' => $updated->email_final_terverifikasi,
            'is_email_recap_confirmed'  => $updated->is_email_recap_confirmed,
        ]);
    }

    /**
     * Aksi AJAX: Cek Ringkas Tabrakan/Duplikasi Email (Quick Collision Check).
     */
    public function checkCollision(Request $request, string|int $id, \App\Services\EmailGeneratorService $emailService): JsonResponse
    {
        $pendaftaran = $this->findPendaftaran($id);

        $this->authorize('view', $pendaftaran);

        $emailToCheck = $request->input('email');
        if (empty($emailToCheck)) {
            $emailToCheck = $pendaftaran->email_final_terverifikasi
                ?? ($pendaftaran->status_memiliki_email ? $pendaftaran->email_existing : $pendaftaran->email_usulan);
        }

        if (empty($emailToCheck)) {
            return response()->json([
                'success' => false,
                'message' => 'Alamat email yang akan dicek tidak boleh kosong.',
            ], 422);
        }

        // Kunci domain @cirebonkota.go.id secara permanen
        $emailToCheckStr = trim((string)$emailToCheck);
        $prefix = str_contains($emailToCheckStr, '@') ? explode('@', $emailToCheckStr)[0] : $emailToCheckStr;
        $prefix = trim($prefix);
        $canonicalEmail = strtolower($prefix . '@' . \App\Services\EmailGeneratorService::DEFAULT_DOMAIN);

        $detail = $emailService->checkCollisionDetail($canonicalEmail, $pendaftaran->id);

        return response()->json([
            'success' => true,
            'email'   => $canonicalEmail,
            'data'    => $detail,
        ]);
    }

    /**
     * Aksi Persetujuan Tahap 1.
     */
    public function approve(Request $request, string|int $id, ApproveTahap1Action $action): JsonResponse|Response
    {
        $pendaftaran = $this->findPendaftaran($id);

        $this->authorize('approveTahap1', $pendaftaran);

        $updated = $action->execute(
            $pendaftaran,
            auth()->id(),
            $request->ip()
        );

        $message = 'Pendaftaran TTE berhasil disetujui (Tahap 1 Selesai). Data siap diproses untuk penerbitan sertifikat.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $updated,
            ]);
        }

        return redirect()->route('admin.verifikasi.index')->with('success', $message);
    }

    /**
     * Aksi Penolakan Tahap 1.
     */
    public function reject(Request $request, string|int $id, RejectPendaftaranAction $action): JsonResponse|Response
    {
        $request->validate([
            'catatan_penolakan' => ['required', 'string', 'max:1000'],
        ], [
            'catatan_penolakan.required' => 'Catatan alasan penolakan berkas wajib dicantumkan.',
        ]);

        $pendaftaran = $this->findPendaftaran($id);

        $this->authorize('rejectTahap1', $pendaftaran);

        $updated = $action->execute(
            $pendaftaran,
            $request->input('catatan_penolakan'),
            auth()->id(),
            $request->ip()
        );

        $message = 'Pendaftaran TTE telah ditolak. Pemohon dapat mendaftar ulang setelah memperbaiki data.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $updated,
            ]);
        }

        return redirect()->route('admin.verifikasi.index')->with('success', $message);
    }

    /**
     * Aksi Verifikasi & Persetujuan Masal Tahap 1.
     */
    public function bulkApprove(Request $request, BulkVerifikasiAction $action): JsonResponse|Response
    {
        $this->authorize('bulkApprove', PendaftaranTte::class);

        $selectedIds = $request->input('selected_ids', $request->input('uuids', []));

        if (empty($selectedIds) || !is_array($selectedIds)) {
            $msg = 'Pilih setidaknya satu permohonan untuk diverifikasi secara masal.';
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        try {
            $result = $action->execute(
                $selectedIds,
                auth()->id(),
                $request->ip()
            );

            $message = "Verifikasi masal berhasil. Sebanyak {$result['total_approved']} dari {$result['total_requested']} permohonan telah disetujui.";

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data'    => $result,
                ]);
            }

            return redirect()->route('admin.verifikasi.index')->with('success', $message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errorMsg = collect($e->errors())->flatten()->first() ?: $e->getMessage();
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => $errorMsg], 422);
            }
            return back()->with('error', $errorMsg);
        } catch (\Throwable $e) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Aksi Pembatalan (Undo) Verifikasi Tahap 1.
     */
    public function undo(Request $request, string|int $id, UndoVerifikasiAction $action): JsonResponse|Response
    {
        $pendaftaran = $this->findPendaftaran($id);

        $this->authorize('undo', $pendaftaran);

        try {
            $restored = $action->execute($pendaftaran, auth()->id());

            $message = "Tindakan verifikasi pada permohonan No. {$restored->nomor_referensi} berhasil dibatalkan dan dikembalikan ke antrian.";

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data'    => $restored,
                ]);
            }

            return redirect()->route('admin.verifikasi.index')->with('success', $message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errorMsg = collect($e->errors())->flatten()->first() ?: $e->getMessage();
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => $errorMsg], 422);
            }
            return back()->with('error', $errorMsg);
        } catch (\Throwable $e) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Helper resolusi PendaftaranTte via nomor_referensi atau ID.
     */
    protected function findPendaftaran(string|int $id): PendaftaranTte
    {
        $idStr = (string) $id;

        return PendaftaranTte::where(function ($q) use ($idStr, $id) {
            $q->where('nomor_referensi', $idStr);

            if (\Illuminate\Support\Str::isUuid($idStr)) {
                $q->orWhere('uuid', $idStr);
            }

            if (is_numeric($id)) {
                $q->orWhere('id', (int) $id);
            }
        })->firstOrFail();
    }

    /**
     * Unduh/buka berkas fisik bukti kepemilikan TTE secara aman dari storage privat (UU PDP & Zero-Public-Access).
     */
    public function downloadFileBukti(string $nomor_referensi)
    {
        $this->authorize('viewAny', PendaftaranTte::class);
        $pendaftaran = $this->findPendaftaran($nomor_referensi);

        if (!$pendaftaran->file_bukti_tte || !\Illuminate\Support\Facades\Storage::disk('local')->exists($pendaftaran->file_bukti_tte)) {
            abort(404, 'Berkas bukti TTE tidak ditemukan atau belum diunggah.');
        }

        return \Illuminate\Support\Facades\Storage::disk('local')->response($pendaftaran->file_bukti_tte);
    }
}

