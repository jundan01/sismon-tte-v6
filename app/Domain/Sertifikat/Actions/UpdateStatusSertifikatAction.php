<?php

namespace App\Domain\Sertifikat\Actions;

use App\Domain\Analytics\Models\AuditActionHistory;
use App\Domain\Sertifikat\Models\SertifikatTte;
use Illuminate\Validation\ValidationException;

/**
 * Action Pembaruan & Override Status Siklus Hidup Sertifikat TTE.
 * Ref: PRD.md §7.4, DESIGN.md §3, SCHEMA.md §7.6
 */
class UpdateStatusSertifikatAction
{
    public const VALID_STATUSES = ['AKTIF', 'WARNING_H7', 'WARNING_H3', 'EXPIRED', 'REVOKED', 'PERLU_PERPANJANG'];

    /**
     * Eksekusi update status sertifikat.
     */
    public function execute(SertifikatTte $sertifikat, string $statusBaru, ?int $userId = null): SertifikatTte
    {
        $statusBaru = strtoupper(trim($statusBaru));

        if (!in_array($statusBaru, self::VALID_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status_sertifikat' => 'Status sertifikat tidak valid. Pilihan status: ' . implode(', ', self::VALID_STATUSES),
            ]);
        }

        $oldStatus = $sertifikat->status_sertifikat;

        $sertifikat->update([
            'status_sertifikat' => $statusBaru,
        ]);

        // Catat ke AuditActionHistory saat sertifikat dicabut (REVOKED) untuk mendukung fitur Undo Revoke
        if ($statusBaru === 'REVOKED') {
            AuditActionHistory::create([
                'action_type'    => 'REVOKE_SERTIFIKAT',
                'entity_type'    => 'SertifikatTte',
                'entity_id'      => (string) ($sertifikat->uuid ?? $sertifikat->id),
                'user_id'        => $userId ?? auth()->id() ?? 1,
                'previous_state' => ['status_sertifikat' => $oldStatus],
                'new_state'      => ['status_sertifikat' => $statusBaru],
                'is_undone'      => false,
            ]);
        }

        return $sertifikat->fresh();
    }
}
