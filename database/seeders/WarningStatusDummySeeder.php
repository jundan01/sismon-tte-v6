<?php

namespace Database\Seeders;

use App\Domain\Analytics\Actions\RefreshDailyAggregatorAction;
use App\Domain\Master\Models\MasterJabatanGolongan;
use App\Domain\Master\Models\Skpd;
use App\Domain\Pendaftaran\Models\PendaftaranTte;
use App\Domain\Sertifikat\Models\SertifikatTte;
use App\Models\User;
use App\Services\EmailGeneratorService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WarningStatusDummySeeder extends Seeder
{
    /**
     * Seeder untuk mengisi data dummy yang mencakup seluruh status warning:
     * 1. Seluruh variasi rentang EWS H-7 s/d H-0 (H-7, H-6, H-5, H-4, H-3, H-2, H-1 Besok, H-0 Hari Ini)
     * 2. Status WARNING_H7 yang telah melewati tanggal jatuh tempo (Lewat 7 hari, Lewat 3 hari, Lewat 1 hari)
     * 3. Status WARNING_H3 (legacy kompatibilitas)
     * 4. Status PERLU_PERPANJANG (peringatan administratif perpanjangan sertifikat)
     */
    public function run(): void
    {
        $admin = User::first();
        $adminId = $admin ? $admin->id : 1;

        $skpds = Skpd::all();
        $jabatans = MasterJabatanGolongan::where('kategori', 'JABATAN')->get();
        $golongans = MasterJabatanGolongan::where('kategori', 'GOLONGAN')->get();

        if ($skpds->isEmpty() || $jabatans->isEmpty() || $golongans->isEmpty()) {
            $this->command?->error('Master SKPD, Jabatan, atau Golongan kosong.');
            return;
        }

        $emailGenerator = app(EmailGeneratorService::class);
        $today = Carbon::today();

        $scenarios = [
            [
                'code'           => 'WARN-H7',
                'nama'           => 'Drs. Peringatan Tujuh Hari, M.Si',
                'gender'         => 'L',
                'offset_days'    => 7,
                'status_cert'    => 'WARNING_H7',
                'desc'           => 'Sertifikat memasuki ambang batas awal EWS H-7 (7 hari lagi)',
            ],
            [
                'code'           => 'WARN-H6',
                'nama'           => 'Ir. Hendra Enam Hari, M.T',
                'gender'         => 'L',
                'offset_days'    => 6,
                'status_cert'    => 'WARNING_H7',
                'desc'           => 'Sertifikat zona peringatan H-6 (6 hari lagi)',
            ],
            [
                'code'           => 'WARN-H5',
                'nama'           => 'Dr. Maya Lima Hari, Sp.A',
                'gender'         => 'P',
                'offset_days'    => 5,
                'status_cert'    => 'WARNING_H7',
                'desc'           => 'Sertifikat zona peringatan H-5 (5 hari lagi)',
            ],
            [
                'code'           => 'WARN-H4',
                'nama'           => 'Budi Empat Hari, S.STP',
                'gender'         => 'L',
                'offset_days'    => 4,
                'status_cert'    => 'WARNING_H7',
                'desc'           => 'Sertifikat zona peringatan H-4 (4 hari lagi)',
            ],
            [
                'code'           => 'WARN-H3',
                'nama'           => 'Ratna Tiga Hari, S.E',
                'gender'         => 'P',
                'offset_days'    => 3,
                'status_cert'    => 'WARNING_H7',
                'desc'           => 'Sertifikat zona kritis peringatan H-3 (3 hari lagi)',
            ],
            [
                'code'           => 'WARN-H2',
                'nama'           => 'Ahmad Dua Hari, S.Kom',
                'gender'         => 'L',
                'offset_days'    => 2,
                'status_cert'    => 'WARNING_H7',
                'desc'           => 'Sertifikat zona sangat mendesak H-2 (2 hari lagi)',
            ],
            [
                'code'           => 'WARN-H1',
                'nama'           => 'Sri Satu Hari, M.M',
                'gender'         => 'P',
                'offset_days'    => 1,
                'status_cert'    => 'WARNING_H7',
                'desc'           => 'Sertifikat jatuh tempo besok H-1 (Besok)',
            ],
            [
                'code'           => 'WARN-H0',
                'nama'           => 'Bambang Hari Ini, S.H',
                'gender'         => 'L',
                'offset_days'    => 0,
                'status_cert'    => 'WARNING_H7',
                'desc'           => 'Sertifikat jatuh tempo hari ini H-0 (Hari Ini)',
            ],
            [
                'code'           => 'WARN-EXP7',
                'nama'           => 'Endang Lewat Tujuh, S.Farm',
                'gender'         => 'P',
                'offset_days'    => -7,
                'status_cert'    => 'WARNING_H7',
                'desc'           => 'Status DB WARNING_H7 tapi tanggal kadaluarsa lewat 7 hari (Kadaluwarsa lewat 7 hari)',
            ],
            [
                'code'           => 'WARN-EXP3',
                'nama'           => 'Dedi Lewat Tiga, S.AP',
                'gender'         => 'L',
                'offset_days'    => -3,
                'status_cert'    => 'WARNING_H7',
                'desc'           => 'Status DB WARNING_H7 tapi tanggal kadaluarsa lewat 3 hari (Kadaluwarsa lewat 3 hari)',
            ],
            [
                'code'           => 'WARN-EXP1',
                'nama'           => 'Asep Lewat Satu, S.T',
                'gender'         => 'L',
                'offset_days'    => -1,
                'status_cert'    => 'WARNING_H7',
                'desc'           => 'Status DB WARNING_H7 tapi tanggal kadaluarsa lewat 1 hari (Kadaluwarsa lewat 1 hari)',
            ],
            [
                'code'           => 'LEGACY-H3',
                'nama'           => 'Rina Legacy Tiga Hari, S.Psi',
                'gender'         => 'P',
                'offset_days'    => 3,
                'status_cert'    => 'WARNING_H3',
                'desc'           => 'Status sertifikat dengan format enum legacy WARNING_H3',
            ],
            [
                'code'           => 'PERLU-PERPANJANG',
                'nama'           => 'Fajar Perlu Perpanjang, S.IP',
                'gender'         => 'L',
                'offset_days'    => 5,
                'status_cert'    => 'PERLU_PERPANJANG',
                'desc'           => 'Sertifikat berstatus administratif PERLU_PERPANJANG',
            ],
        ];

        foreach ($scenarios as $idx => $sc) {
            $num = str_pad((string)($idx + 80), 4, '0', STR_PAD_LEFT);
            $cleanNama = $emailGenerator->cleanFullName($sc['nama']);
            $nip = '199510' . str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT) . '202012' . ($sc['gender'] === 'P' ? '2' : '1') . '0' . str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT);
            $nik = '327402' . ($sc['gender'] === 'P' ? '51109' : '11109') . $num;

            $skpd = $skpds[$idx % $skpds->count()];
            $jabatan = $jabatans[$idx % $jabatans->count()];
            $golongan = $golongans[$idx % $golongans->count()];

            $username = Str::slug(explode(' ', $cleanNama)[0] . '_' . (explode(' ', $cleanNama)[1] ?? 'user'), '_');
            $cleanCode = strtolower(str_replace('-', '_', $sc['code']));
            $emailDinas = $username . '.' . $cleanCode . '@cirebonkota.go.id';

            $refNum = 'TTE-' . date('Ymd') . '-' . $sc['code'];

            // 1. Buat atau perbarui record PendaftaranTte
            $pendaftaran = PendaftaranTte::updateOrCreate(
                ['nomor_referensi' => $refNum],
                [
                    'uuid'                      => (string) Str::uuid(),
                    'nama_lengkap'              => $cleanNama,
                    'nik'                       => $nik,
                    'nip'                       => $nip,
                    'no_hp'                     => '08' . (7700000000 + $idx),
                    'skpd_id'                   => $skpd->id,
                    'jabatan_id'                => $jabatan->id,
                    'golongan_id'               => $golongan->id,
                    'status_memiliki_email'     => true,
                    'email_existing'            => $emailDinas,
                    'email_usulan'              => null,
                    'email_final_terverifikasi' => $emailDinas,
                    'is_email_recap_confirmed'  => true,
                    'status'                    => 'DISETUJUI',
                    'catatan_penolakan'         => null,
                    'verified_by'               => $adminId,
                    'verified_at'               => now()->subDays(15),
                    'verified_ip'               => '10.10.1.50',
                ]
            );

            // 2. Hitung tanggal kadaluarsa dan tanggal penerbitan (+2 tahun)
            $tglKadaluarsa = $today->copy()->addDays($sc['offset_days']);
            $tglPenerbitan = $tglKadaluarsa->copy()->subYears(2);

            // 3. Buat atau perbarui record SertifikatTte
            SertifikatTte::updateOrCreate(
                ['pendaftaran_id' => $pendaftaran->id],
                [
                    'uuid'               => (string) Str::uuid(),
                    'email_dinas'        => $emailDinas,
                    'tanggal_penerbitan' => $tglPenerbitan->toDateString(),
                    'tanggal_kadaluarsa' => $tglKadaluarsa->toDateString(),
                    'status_sertifikat'  => $sc['status_cert'],
                    'verified_by_tahap2' => $adminId,
                    'verified_at_tahap2' => now()->subDays(14),
                    'verified_ip_tahap2' => '10.10.1.50',
                ]
            );
        }

        // 4. Refresh agregator counter dashboard harian
        app(RefreshDailyAggregatorAction::class)->execute();
    }
}
