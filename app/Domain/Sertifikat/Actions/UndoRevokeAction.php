<?php

namespace App\Domain\Sertifikat\Actions;

use App\Domain\Analytics\Models\AuditActionHistory;
use App\Domain\Sertifikat\Models\SertifikatTte;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Action Undo Revoke Sertifikat TTE.
 * Memulihkan sertifikat yang tidak sengaja dicabut (REVOKED) kembali ke status AKTIF.
 * Ref: implementation.md §3.1.F, §4.5.2
 */
class UndoRevokeAction
{
    /**
     * Eksekusi pemulihan sertifikat yang telah dicabut.
     */
    public function execute(SertifikatTte $sertifikat, int $userId): SertifikatTte
    {
        if ($sertifikat->status_sertifikat !== 'REVOKED') {
            throw ValidationException::withMessages([
                'status_sertifikat' => 'Aksi pembatalan pencabutan (Undo Revoke) hanya dapat dilakukan untuk sertifikat berstatus REVOKED.',
            ]);
        }

        return DB::transaction(function () use ($sertifikat, $userId) {
            // Cari catatan audit pencabutan terbaru yang belum pernah di-undo
            $auditLog = AuditActionHistory::where('action_type', 'REVOKE_SERTIFIKAT')
                ->where('entity_type', 'SertifikatTte')
                ->where('entity_id', (string) ($sertifikat->uuid ?? $sertifikat->id))
                ->where('is_undone', false)
                ->latest('id')
                ->first();

            $restoredStatus = 'AKTIF';
            if ($auditLog && !empty($auditLog->previous_state['status_sertifikat'])) {
                $restoredStatus = $auditLog->previous_state['status_sertifikat'];
            }

            // Kembalikan status sertifikat
            $sertifikat->update([
                'status_sertifikat' => $restoredStatus,
            ]);

            // Tandai riwayat audit sebelumnya sebagai telah di-undo
            if ($auditLog) {
                $auditLog->update([
                    'is_undone' => true,
                    'undone_by' => $userId,
                    'undone_at' => now(),
                ]);
            }

            // Catat riwayat aksi undo revoke secara eksplisit
            AuditActionHistory::create([
                'action_type'    => 'UNDO_REVOKE_SERTIFIKAT',
                'entity_type'    => 'SertifikatTte',
                'entity_id'      => (string) ($sertifikat->uuid ?? $sertifikat->id),
                'user_id'        => $userId,
                'previous_state' => ['status_sertifikat' => 'REVOKED'],
                'new_state'      => ['status_sertifikat' => $restoredStatus],
                'is_undone'      => true,
                'undone_by'      => $userId,
                'undone_at'      => now(),
            ]);

            return $sertifikat->fresh();
        });
    }
}
