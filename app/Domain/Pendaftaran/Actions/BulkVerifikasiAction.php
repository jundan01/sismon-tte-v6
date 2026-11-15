<?php

namespace App\Domain\Pendaftaran\Actions;

use App\Domain\Analytics\Models\AuditActionHistory;
use App\Domain\Pendaftaran\Models\PendaftaranTte;
use App\Services\SystemParameterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Action Verifikasi Masal (Bulk Approval) Tahap 1.
 * Memproses persetujuan banyak permohonan TTE sekaligus dalam transaksi atomik aman.
 * Ref: implementation.md §3.1.C, §4.4.1
 */
class BulkVerifikasiAction
{
    /**
     * Eksekusi persetujuan masal Tahap 1.
     *
     * @param array<string|int> $identifiers Array berisi nomor_referensi, UUID, atau ID permohonan
     */
    public function execute(array $identifiers, int $userId, ?string $ipAddress = null): array
    {
        $identifiers = array_values(array_filter(array_unique($identifiers)));

        if (empty($identifiers)) {
            throw ValidationException::withMessages([
                'identifiers' => 'Pilih minimal satu permohonan untuk melakukan verifikasi masal.',
            ]);
        }

        $maxBatch = (int) SystemParameterService::get('bulk_verification_max_batch', 50);
        if (count($identifiers) > $maxBatch) {
            throw ValidationException::withMessages([
                'identifiers' => "Jumlah permohonan yang dipilih (" . count($identifiers) . ") melebihi batas maksimum validasi masal ({$maxBatch} permohonan per batch).",
            ]);
        }

        return DB::transaction(function () use ($identifiers, $userId, $ipAddress) {
            $uuids = array_values(array_filter($identifiers, fn($v) => \Illuminate\Support\Str::isUuid((string)$v)));
            $nomors = array_values(array_filter($identifiers, fn($v) => !\Illuminate\Support\Str::isUuid((string)$v) && !is_numeric($v)));
            $numericIds = array_values(array_filter($identifiers, 'is_numeric'));

            $pendaftarans = PendaftaranTte::where('status', 'MENUNGGU_VERIFIKASI')
                ->where(function ($q) use ($uuids, $nomors, $numericIds) {
                    $hasClause = false;
                    if (!empty($uuids)) {
                        $q->whereIn('uuid', $uuids);
                        $hasClause = true;
                    }
                    if (!empty($nomors)) {
                        $hasClause ? $q->orWhereIn('nomor_referensi', $nomors) : $q->whereIn('nomor_referensi', $nomors);
                        $hasClause = true;
                    }
                    if (!empty($numericIds)) {
                        $hasClause ? $q->orWhereIn('id', $numericIds) : $q->whereIn('id', $numericIds);
                    }
                })
                ->get();

            $approvedList = [];

            foreach ($pendaftarans as $p) {
                $previousState = [
                    'status' => $p->status,
                ];

                $p->update([
                    'status'      => 'DISETUJUI',
                    'verified_by' => $userId,
                    'verified_at' => now(),
                    'verified_ip' => $ipAddress ?? request()->ip(),
                ]);

                // Catat riwayat aksi ke AuditActionHistory
                AuditActionHistory::create([
                    'action_type'    => 'BULK_VERIFIKASI_TAHAP1',
                    'entity_type'    => 'PendaftaranTte',
                    'entity_id'      => (string) ($p->uuid ?? $p->nomor_referensi),
                    'user_id'        => $userId,
                    'previous_state' => $previousState,
                    'new_state'      => ['status' => 'DISETUJUI'],
                    'is_undone'      => false,
                ]);

                $approvedList[] = [
                    'id'              => $p->id,
                    'nomor_referensi' => $p->nomor_referensi,
                    'nama_lengkap'    => $p->nama_lengkap,
                ];
            }

            return [
                'total_requested' => count($identifiers),
                'total_approved'  => count($approvedList),
                'approved_items'  => $approvedList,
            ];
        });
    }
}
