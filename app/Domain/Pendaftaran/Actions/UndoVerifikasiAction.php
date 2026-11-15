<?php

namespace App\Domain\Pendaftaran\Actions;

use App\Domain\Analytics\Models\AuditActionHistory;
use App\Domain\Pendaftaran\Models\PendaftaranTte;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Action Undo Verifikasi Tahap 1.
 * Membatalkan aksi persetujuan atau penolakan permohonan TTE, mengembalikannya ke MENUNGGU_VERIFIKASI.
 * Ref: implementation.md §3.1.F, §4.5.2
 */
class UndoVerifikasiAction
{
    /**
     * Eksekusi pembatalan aksi verifikasi Tahap 1.
     */
    public function execute(PendaftaranTte $pendaftaran, int $userId): PendaftaranTte
    {
        if ($pendaftaran->status === 'MENUNGGU_VERIFIKASI') {
            throw ValidationException::withMessages([
                'status' => 'Permohonan ini masih dalam antrian (MENUNGGU_VERIFIKASI) dan belum pernah diproses.',
            ]);
        }

        // Jika sudah terbit sertifikat aktif, verifikasi Tahap 1 tidak boleh di-undo sembarangan
        if ($pendaftaran->sertifikat()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Tidak dapat membatalkan verifikasi: Sertifikat TTE untuk permohonan ini telah diterbitkan.',
            ]);
        }

        return DB::transaction(function () use ($pendaftaran, $userId) {
            $previousStatus = $pendaftaran->status;
            $previousState = [
                'status'            => $previousStatus,
                'verified_by'       => $pendaftaran->verified_by,
                'verified_at'       => $pendaftaran->verified_at?->toIso8601String(),
                'catatan_penolakan' => $pendaftaran->catatan_penolakan,
            ];

            // Kembalikan ke antrian verifikasi
            $pendaftaran->update([
                'status'            => 'MENUNGGU_VERIFIKASI',
                'verified_by'       => null,
                'verified_at'       => null,
                'verified_ip'       => null,
                'catatan_penolakan' => null,
            ]);

            // Cari log aksi verifikasi sebelumnya yang relevan
            $auditLog = AuditActionHistory::whereIn('action_type', ['APPROVE_TAHAP1', 'REJECT_TAHAP1', 'BULK_VERIFIKASI_TAHAP1'])
                ->where('entity_type', 'PendaftaranTte')
                ->where('entity_id', (string) ($pendaftaran->uuid ?? $pendaftaran->nomor_referensi))
                ->where('is_undone', false)
                ->latest('id')
                ->first();

            if ($auditLog) {
                $auditLog->update([
                    'is_undone' => true,
                    'undone_by' => $userId,
                    'undone_at' => now(),
                ]);
            }

            // Catat riwayat aksi undo
            AuditActionHistory::create([
                'action_type'    => 'UNDO_VERIFIKASI_TAHAP1',
                'entity_type'    => 'PendaftaranTte',
                'entity_id'      => (string) ($pendaftaran->uuid ?? $pendaftaran->nomor_referensi),
                'user_id'        => $userId,
                'previous_state' => $previousState,
                'new_state'      => ['status' => 'MENUNGGU_VERIFIKASI'],
                'is_undone'      => true,
                'undone_by'      => $userId,
                'undone_at'      => now(),
            ]);

            return $pendaftaran->fresh();
        });
    }
}
