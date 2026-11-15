<?php

namespace App\Domain\Sertifikat\Actions;

use App\Domain\Analytics\Models\AuditActionHistory;
use App\Domain\Pendaftaran\Models\PendaftaranTte;
use App\Services\SystemParameterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Action Validasi Masal Tahap 2 (Penerbitan / Validasi Sertifikat TTE Masal).
 * Memproses penerbitan sertifikat TTE untuk banyak permohonan disetujui sekaligus.
 * Ref: implementation.md §3.1.C, §4.3.3, §4.4.1
 */
class BulkValidasiTahap2Action
{
    public function __construct(
        protected TerbitkanSertifikatAction $terbitkanAction
    ) {}

    /**
     * Eksekusi validasi dan penerbitan masal Tahap 2.
     *
     * @param array<string|int> $identifiers Array nomor_referensi, UUID, atau ID permohonan
     */
    public function execute(array $identifiers, string $tanggalPenerbitan, int $userId, ?string $ipAddress = null): array
    {
        $identifiers = array_values(array_filter(array_unique($identifiers)));

        if (empty($identifiers)) {
            throw ValidationException::withMessages([
                'identifiers' => 'Pilih minimal satu permohonan untuk melakukan validasi masal.',
            ]);
        }

        $maxBatch = (int) SystemParameterService::get('bulk_verification_max_batch', 50);
        if (count($identifiers) > $maxBatch) {
            throw ValidationException::withMessages([
                'identifiers' => "Jumlah permohonan yang dipilih (" . count($identifiers) . ") melebihi batas maksimum validasi masal ({$maxBatch} permohonan per batch).",
            ]);
        }

        return DB::transaction(function () use ($identifiers, $tanggalPenerbitan, $userId, $ipAddress) {
            $uuids = array_values(array_filter($identifiers, fn($v) => \Illuminate\Support\Str::isUuid((string)$v)));
            $nomors = array_values(array_filter($identifiers, fn($v) => !\Illuminate\Support\Str::isUuid((string)$v) && !is_numeric($v)));
            $numericIds = array_values(array_filter($identifiers, 'is_numeric'));

            // Ambil seluruh permohonan DISETUJUI yang belum memiliki sertifikat
            $pendaftarans = PendaftaranTte::where('status', 'DISETUJUI')
                ->doesntHave('sertifikat')
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

            $validatedList = [];

            foreach ($pendaftarans as $p) {
                // Pastikan email dinas tersedia (email_final_terverifikasi atau email_usulan/existing)
                if (empty($p->email_final_terverifikasi)) {
                    $p->update([
                        'email_final_terverifikasi' => $p->email_existing ?: $p->email_usulan,
                        'is_email_recap_confirmed'  => true,
                    ]);
                }

                $sertifikat = $this->terbitkanAction->execute(
                    $p,
                    $tanggalPenerbitan,
                    $userId,
                    $ipAddress
                );

                // Catat ke AuditActionHistory
                AuditActionHistory::create([
                    'action_type'    => 'BULK_VALIDASI_TAHAP2',
                    'entity_type'    => 'SertifikatTte',
                    'entity_id'      => (string) ($sertifikat->uuid ?? $sertifikat->id),
                    'user_id'        => $userId,
                    'previous_state' => ['pendaftaran_status' => 'DISETUJUI'],
                    'new_state'      => [
                        'status_sertifikat'  => 'AKTIF',
                        'tanggal_penerbitan' => $sertifikat->tanggal_penerbitan->toDateString(),
                        'tanggal_kadaluarsa' => $sertifikat->tanggal_kadaluarsa->toDateString(),
                    ],
                    'is_undone'      => false,
                ]);

                $validatedList[] = [
                    'pendaftaran_id'     => $p->id,
                    'nomor_referensi'    => $p->nomor_referensi,
                    'nama_lengkap'       => $p->nama_lengkap,
                    'email_dinas'        => $sertifikat->email_dinas,
                    'tanggal_kadaluarsa' => $sertifikat->tanggal_kadaluarsa->toDateString(),
                ];
            }

            return [
                'total_requested' => count($identifiers),
                'total_validated' => count($validatedList),
                'validated_items' => $validatedList,
            ];
        });
    }
}
