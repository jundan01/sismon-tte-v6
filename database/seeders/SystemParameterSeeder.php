<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemParameterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Inisialisasi parameter kebijakan sistem dinamis (anti hard-coding).
     * Ref: implementation.md §3.1.C
     */
    public function run(): void
    {
        $parameters = [
            [
                'param_key'   => 'cert_validity_years',
                'param_value' => '2',
                'param_group' => 'CERTIFICATE',
                'data_type'   => 'INTEGER',
                'label'       => 'Masa Berlaku Sertifikat (Tahun)',
                'description' => 'Durasi masa aktif standar sertifikat TTE BSrE sejak tanggal penerbitan.',
            ],
            [
                'param_key'   => 'cert_expiry_warning_days',
                'param_value' => '7',
                'param_group' => 'CERTIFICATE',
                'data_type'   => 'INTEGER',
                'label'       => 'Ambang Peringatan Kedaluwarsa (Hari)',
                'description' => 'Jumlah hari sebelum tanggal kedaluwarsa untuk menaikkan status WARNING_H7 dan memicu notifikasi email.',
            ],
            [
                'param_key'   => 'allow_non_asn_registration',
                'param_value' => 'true',
                'param_group' => 'REGISTRATION',
                'data_type'   => 'BOOLEAN',
                'label'       => 'Izinkan Pendaftaran Pegawai Non-ASN',
                'description' => 'Mengaktifkan atau menonaktifkan jalur registrasi mandiri untuk aparatur Non-ASN.',
            ],
            [
                'param_key'   => 'bulk_verification_max_batch',
                'param_value' => '50',
                'param_group' => 'OPERATIONAL',
                'data_type'   => 'INTEGER',
                'label'       => 'Batas Maksimum Validasi Masal per Batch',
                'description' => 'Jumlah maksimum pengajuan yang dapat divalidasi/disetujui secara bersamaan dalam satu transaksi.',
            ],
            [
                'param_key'   => 'city_adoption_target_percent',
                'param_value' => '100',
                'param_group' => 'ANALYTICS',
                'data_type'   => 'INTEGER',
                'label'       => 'Target Capaian Adopsi Kota (%)',
                'description' => 'Target persentase adopsi kepemilikan TTE aparatur di tingkat Pemerintah Kota Cirebon.',
            ],
        ];

        foreach ($parameters as $param) {
            DB::table('system_parameters')->updateOrInsert(
                ['param_key' => $param['param_key']],
                array_merge($param, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
