<?php

namespace App\Console\Commands;

use App\Domain\Analytics\Models\AuditActionHistory;
use App\Domain\Sertifikat\Models\SertifikatTte;
use App\Mail\SertifikatExpiringNotification;
use App\Services\SystemParameterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Artisan Command: Notifikasi Email Kedaluwarsa Sertifikat TTE H-7.
 * Dijadwalkan otomatis pukul 07:00 WIB via Laravel Scheduler.
 * Ref: implementation.md §4.6.3 (V6.5)
 */
class NotifyExpiringCertificatesH7Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tte:notify-expiring-h7
                            {--days= : Ambang batas sisa hari peringatan (default: dari system_parameters)}
                            {--window : Periksa seluruh sertifikat dalam rentang <= H-X (bukan hanya tepat H-X)}
                            {--dry-run : Mode simulasi tanpa mengirimkan email nyata}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pindai dan kirimkan notifikasi email resmi peringatan kedaluwarsa sertifikat TTE H-7 kepada pemilik sertifikat';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $customDays = $this->option('days');
        $warningDays = !empty($customDays) ? (int)$customDays : (int)SystemParameterService::get('cert_expiry_warning_days', 7);
        if ($warningDays <= 0) {
            $warningDays = 7;
        }

        $isDryRun = $this->option('dry-run');
        $useWindow = $this->option('window');

        $this->info("=== PEMINDAIAN SERTIFIKAT TTE MENDEKATI KEDALUWARSA (H-{$warningDays}) ===");
        if ($isDryRun) {
            $this->warn("[PERINGATAN] Berjalan dalam mode SIMULASI (--dry-run). Tidak ada email nyata yang akan dikirimkan.");
        }

        $targetDate = today()->addDays($warningDays)->toDateString();

        $query = SertifikatTte::with(['pendaftaran.skpd'])
            ->whereIn('status_sertifikat', ['AKTIF', 'WARNING_H7', 'WARNING_H3']);

        if ($useWindow) {
            $query->whereDate('tanggal_kadaluarsa', '>=', today()->toDateString())
                  ->whereDate('tanggal_kadaluarsa', '<=', $targetDate);
        } else {
            $query->whereDate('tanggal_kadaluarsa', $targetDate);
        }

        $expiringCerts = $query->get();

        if ($expiringCerts->isEmpty()) {
            $this->info("✔ Tidak ada sertifikat TTE yang akan kedaluwarsa pada target tanggal {$targetDate} (H-{$warningDays}).");
            return Command::SUCCESS;
        }

        $this->info("Ditemukan {$expiringCerts->count()} sertifikat TTE yang memenuhi kriteria peringatan kedaluwarsa.");

        $tableData = [];
        $sentCount = 0;
        $failedCount = 0;

        foreach ($expiringCerts as $cert) {
            $pendaftaran = $cert->pendaftaran;
            $email = $cert->email_dinas ?: ($pendaftaran?->email_final_terverifikasi ?: $pendaftaran?->email_existing);
            $nama = $pendaftaran?->nama_lengkap ?? 'Pemilik Sertifikat';
            $skpd = $pendaftaran?->skpd?->nama_skpd ?? '-';
            $sisaHari = $cert->sisa_hari ?? $warningDays;

            if (empty($email)) {
                $this->warn("[-] Sertifikat UUID {$cert->uuid} ({$nama}): Email dinas tidak ditemukan. Dilewati.");
                $failedCount++;
                continue;
            }

            $tableData[] = [
                $cert->uuid,
                $nama,
                $skpd,
                $email,
                $cert->tanggal_kadaluarsa?->toDateString() ?? '-',
                "H-{$sisaHari}",
                $isDryRun ? 'SIMULASI (SIAP KIRIM)' : 'DIKIRIM',
            ];

            if (!$isDryRun) {
                try {
                    Mail::to($email)->send(new SertifikatExpiringNotification($cert, $sisaHari));

                    // Catat ke Audit Action History
                    AuditActionHistory::create([
                        'action_type'    => 'NOTIFIKASI_EXPIRING_H7',
                        'entity_type'    => 'SertifikatTte',
                        'entity_id'      => (string) $cert->id,
                        'user_id'        => 1, // System automated user
                        'previous_state' => [
                            'status_sertifikat'  => $cert->status_sertifikat,
                            'tanggal_kadaluarsa' => $cert->tanggal_kadaluarsa?->toDateString(),
                        ],
                        'new_state'      => [
                            'email_penerima' => $email,
                            'notified_at'    => now()->toIso8601String(),
                            'sisa_hari'      => $sisaHari,
                        ],
                        'is_undone'      => false,
                    ]);

                    Log::info("Notifikasi kedaluwarsa H-{$warningDays} terkirim ke {$email} untuk sertifikat ID {$cert->id}");
                    $sentCount++;
                } catch (\Throwable $e) {
                    Log::error("Gagal mengirim email notifikasi kedaluwarsa ke {$email}: " . $e->getMessage());
                    $this->error("[-] Gagal kirim ke {$email}: " . $e->getMessage());
                    $failedCount++;
                }
            } else {
                $sentCount++;
            }
        }

        $this->table(
            ['UUID Sertifikat', 'Nama Pemilik', 'SKPD', 'Email Tujuan', 'Tgl Kadaluarsa', 'Status', 'Hasil'],
            $tableData
        );

        $this->newLine();
        if ($isDryRun) {
            $this->info("✔ Selesai simulasi: {$sentCount} notifikasi teridentifikasi siap dikirim.");
        } else {
            $this->info("✔ Selesai: {$sentCount} notifikasi berhasil dikirim, {$failedCount} gagal/dilewati.");
        }

        return Command::SUCCESS;
    }
}
