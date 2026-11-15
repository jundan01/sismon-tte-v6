<?php

namespace App\Domain\Pendaftaran\Actions;

use App\Domain\Pendaftaran\Models\PendaftaranTte;
use App\Services\EmailGeneratorService;
use App\Services\Security\CryptoHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Action Eksekusi Penyimpanan Pendaftaran TTE Mandiri.
 * Menangani transaksi terenkripsi, pembangkitan usulan email dinas, dan pencatatan audit.
 * Ref: PRD.md §7.1, ARCHITECTURE.md §8, SCHEMA.md §7.4
 */
class StorePendaftaranAction
{
    public function __construct(
        protected EmailGeneratorService $emailGenerator
    ) {}

    /**
     * Eksekusi penyimpanan pendaftaran baru (Mendukung Jalur ASN & Non-ASN).
     *
     * @param array{
     *     nama_lengkap: string,
     *     nik: string,
     *     nip?: string|null,
     *     tipe_pegawai?: string|null,
     *     file_bukti_tte?: mixed,
     *     no_hp: string,
     *     skpd_id: int,
     *     deskripsi?: string|null,
     *     jabatan_id?: int|null,
     *     golongan_id?: int|null,
     *     status_memiliki_email: bool,
     *     email_existing?: string|null,
     * } $data
     * @return PendaftaranTte
     */
    public function execute(array $data): PendaftaranTte
    {
        return DB::transaction(function () use ($data) {
            // 1. Sanitasi nama lengkap: eliminasi seluruh jenis gelar untuk penyimpanan murni ke basis data
            $cleanNamaLengkap = $this->emailGenerator->cleanFullName($data['nama_lengkap']);

            // 2. Tipe Pegawai: default 'ASN', mendukung 'NON_ASN'
            $tipePegawai = !empty($data['tipe_pegawai']) && in_array(strtoupper($data['tipe_pegawai']), ['ASN', 'NON_ASN'], true)
                ? strtoupper($data['tipe_pegawai'])
                : 'ASN';

            // 3. Handle upload file bukti kepemilikan TTE jika ada (Disimpan di disk privat 'local' untuk mematuhi UU PDP)
            $fileBuktiPath = null;
            if (isset($data['file_bukti_tte'])) {
                if ($data['file_bukti_tte'] instanceof \Illuminate\Http\UploadedFile) {
                    $fileBuktiPath = $data['file_bukti_tte']->store('bukti_tte', 'local');
                } elseif (is_string($data['file_bukti_tte'])) {
                    $fileBuktiPath = $data['file_bukti_tte'];
                }
            }

            // 4. Generate nomor referensi internal Superadmin: TTE-YYYYMMDD-XXXXX
            $nomorReferensi = $this->generateNomorReferensi();

            // 5. NIP (null bagi Non-ASN, wajib bagi ASN)
            $nipValue = ($tipePegawai === 'NON_ASN') ? null : (!empty($data['nip']) ? trim($data['nip']) : null);

            // 6. Generate email usulan otomatis jika belum memiliki email dinas
            $emailUsulan = null;
            if (empty($data['status_memiliki_email'])) {
                $emailUsulan = $this->emailGenerator->generate($cleanNamaLengkap, $nipValue);
            }

            // 7. Simpan data pendaftaran utama (nama murni tanpa gelar, NIK/NIP di-encrypt otomatis)
            /** @var PendaftaranTte $pendaftaran */
            $pendaftaran = PendaftaranTte::create([
                'nomor_referensi'          => $nomorReferensi,
                'tipe_pegawai'             => $tipePegawai,
                'nama_lengkap'             => $cleanNamaLengkap, // Murni nama tanpa gelar
                'nik'                      => $data['nik'], // Mutator model melakukan encrypt + hash blind index
                'nip'                      => $nipValue, // Mutator model mendukung null bagi Non-ASN
                'no_hp'                    => $data['no_hp'],
                'file_bukti_tte'           => $fileBuktiPath,
                'skpd_id'                  => $data['skpd_id'],
                'deskripsi'                => $data['deskripsi'] ?? null,
                'jabatan_id'               => $data['jabatan_id'] ?? null,
                'golongan_id'              => $data['golongan_id'] ?? null,
                'status_memiliki_email'    => (bool)$data['status_memiliki_email'],
                'email_existing'           => $data['email_existing'] ?? null,
                'email_usulan'             => $emailUsulan,
                'email_final_terverifikasi'=> null,
                'is_email_recap_confirmed' => false,
                'status'                   => 'MENUNGGU_VERIFIKASI',
            ]);

            return $pendaftaran->load(['skpd', 'jabatan', 'golongan']);
        });
    }

    /**
     * Hasilkan format nomor referensi unik: TTE-YYYYMMDD-XXXXXX.
     */
    protected function generateNomorReferensi(): string
    {
        $attempts = 0;
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
            $nomor = 'TTE-' . date('Ymd') . '-' . $code;
            $attempts++;
        } while ($attempts < 5 && PendaftaranTte::where('nomor_referensi', $nomor)->exists());

        return $nomor;
    }
}
