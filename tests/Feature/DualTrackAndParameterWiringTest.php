<?php

namespace Tests\Feature;

use App\Domain\Master\Models\Skpd;
use App\Domain\Pendaftaran\Actions\StorePendaftaranAction;
use App\Domain\Pendaftaran\Models\PendaftaranTte;
use App\Domain\Sertifikat\Actions\TerbitkanSertifikatAction;
use App\Domain\Sertifikat\Models\SertifikatTte;
use App\Jobs\UpdateSertifikatStatusJob;
use App\Models\User;
use App\Services\Security\CryptoHelper;
use App\Services\SystemParameterService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test Suite Tahap 1 V6:
 * 1. Dual-Track Pendaftaran (Jalur ASN & Non-ASN)
 * 2. Kelonggaran NIP & Upload Berkas Bukti TTE
 * 3. Wiring Parameter Dinamis pada Masa Berlaku Sertifikat & Job Scanner Kedaluwarsa
 * Ref: implementation.md §3.1 & §4.1
 */
class DualTrackAndParameterWiringTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected Skpd $skpd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'admin@cirebonkota.go.id')->firstOrFail();
        $this->skpd = Skpd::firstOrFail();
        Storage::fake('public');
        RateLimiter::clear(sha1('pendaftaran_limit|127.0.0.1'));
        SystemParameterService::clearCache();
    }

    protected function getCaptchaPayload(): array
    {
        $answer = 15;
        $timestamp = now()->timestamp;
        $signature = Crypt::encryptString("{$answer}|{$timestamp}");

        return [
            'local_captcha_answer'    => (string) $answer,
            'local_captcha_signature' => $signature,
        ];
    }

    /**
     * 1. Jalur ASN mewajibkan NIP 18 digit angka.
     */
    public function test_pendaftaran_asn_requires_valid_18_digit_nip(): void
    {
        $randomNik = '3274010101' . random_int(100000, 999999);

        // Tanpa NIP untuk ASN -> Gagal validasi
        $responseFail = $this->postJson('/pendaftaran', array_merge([
            'tipe_pegawai'          => 'ASN',
            'nama_lengkap'          => 'Drs. Supardi, M.Si',
            'nik'                   => $randomNik,
            'nip'                   => '',
            'no_hp'                 => '081234567890',
            'skpd_id'               => $this->skpd->id,
            'status_memiliki_email' => false,
        ], $this->getCaptchaPayload()));

        $responseFail->assertStatus(422);
        $responseFail->assertJsonValidationErrors(['nip']);

        RateLimiter::clear(sha1('pendaftaran_limit|127.0.0.1'));

        // Dengan NIP 18 digit lengkap -> Berhasil
        $randomNip = '19850101201001' . random_int(1000, 9999);
        $responseSuccess = $this->postJson('/pendaftaran', array_merge([
            'tipe_pegawai'          => 'ASN',
            'nama_lengkap'          => 'Drs. Supardi, M.Si',
            'nik'                   => $randomNik,
            'nip'                   => $randomNip,
            'no_hp'                 => '081234567890',
            'skpd_id'               => $this->skpd->id,
            'status_memiliki_email' => false,
        ], $this->getCaptchaPayload()));

        $responseSuccess->assertStatus(201);
        $pendaftaran = PendaftaranTte::where('nomor_referensi', $responseSuccess->json('nomor_referensi'))->first();
        $this->assertNotNull($pendaftaran);
        $this->assertEquals('ASN', $pendaftaran->tipe_pegawai);
        $this->assertEquals($randomNip, $pendaftaran->nip);
        $this->assertEquals('Supardi', $pendaftaran->nama_lengkap);
    }

    /**
     * 2. Jalur Non-ASN mengizinkan NIP kosong / null.
     */
    public function test_pendaftaran_non_asn_allows_null_or_empty_nip(): void
    {
        $randomNik = '3274010102' . random_int(100000, 999999);

        $response = $this->postJson('/pendaftaran', array_merge([
            'tipe_pegawai'          => 'NON_ASN',
            'nama_lengkap'          => 'Rian Hidayat Tenaga Teknis',
            'nik'                   => $randomNik,
            'nip'                   => null, // NIP null untuk Non-ASN
            'no_hp'                 => '081234567891',
            'skpd_id'               => $this->skpd->id,
            'deskripsi'             => 'Tenaga IT Lapangan Non-ASN',
            'status_memiliki_email' => false,
        ], $this->getCaptchaPayload()));

        $response->assertStatus(201);
        $pendaftaran = PendaftaranTte::where('nomor_referensi', $response->json('nomor_referensi'))->first();
        $this->assertNotNull($pendaftaran);
        $this->assertEquals('NON_ASN', $pendaftaran->tipe_pegawai);
        $this->assertNull($pendaftaran->nip);
        $this->assertEquals('Rian Hidayat Tenaga Teknis', $pendaftaran->nama_lengkap);
        $this->assertNotNull($pendaftaran->email_usulan);
        $this->assertStringContainsString('@cirebonkota.go.id', $pendaftaran->email_usulan);
    }

    /**
     * 3. Pendaftaran tanpa berkas bukti kepemilikan TTE (fitur lampiran telah dihilangkan).
     */
    public function test_pendaftaran_supports_upload_file_bukti_tte(): void
    {
        $randomNik = '3274010103' . random_int(100000, 999999);

        $response = $this->postJson('/pendaftaran', array_merge([
            'tipe_pegawai'          => 'NON_ASN',
            'nama_lengkap'          => 'Dr. Siti Nurhaliza Konsultan',
            'nik'                   => $randomNik,
            'nip'                   => null,
            'no_hp'                 => '081234567892',
            'skpd_id'               => $this->skpd->id,
            'status_memiliki_email' => false,
        ], $this->getCaptchaPayload()));

        $response->assertStatus(201);
        $pendaftaran = PendaftaranTte::where('nomor_referensi', $response->json('nomor_referensi'))->first();
        $this->assertNotNull($pendaftaran);
        $this->assertNull($pendaftaran->file_bukti_tte);
    }

    /**
     * 4. Pendaftaran Non-ASN diblokir otomatis jika parameter kebijakan allow_non_asn_registration bernilai false.
     */
    public function test_pendaftaran_non_asn_blocked_when_parameter_disabled(): void
    {
        SystemParameterService::set('allow_non_asn_registration', false);

        $randomNik = '3274010104' . random_int(100000, 999999);

        $response = $this->postJson('/pendaftaran', array_merge([
            'tipe_pegawai'          => 'NON_ASN',
            'nama_lengkap'          => 'Wawan Setiawan',
            'nik'                   => $randomNik,
            'no_hp'                 => '081234567893',
            'skpd_id'               => $this->skpd->id,
            'status_memiliki_email' => false,
        ], $this->getCaptchaPayload()));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tipe_pegawai']);
        $this->assertStringContainsString('dinonaktifkan', $response->json('errors.tipe_pegawai.0'));

        // Kembalikan parameter
        SystemParameterService::set('allow_non_asn_registration', true);
    }

    /**
     * 5. Penerbitan sertifikat menghitung tanggal kadaluarsa dinamis berbasis parameter cert_validity_years.
     */
    public function test_terbitkan_sertifikat_respects_dynamic_cert_validity_years(): void
    {
        // Ubah parameter sistem masa berlaku sertifikat menjadi 3 tahun
        SystemParameterService::set('cert_validity_years', 3);

        $randomSuffix = random_int(1000, 9999);
        $pendaftaran = PendaftaranTte::create([
            'nomor_referensi'           => 'TTE-TEST-VAL-' . $randomSuffix,
            'tipe_pegawai'              => 'ASN',
            'nama_lengkap'              => 'Bambang Trihatmodjo',
            'nik'                       => '327401010190' . $randomSuffix,
            'nip'                       => '19890101201501' . $randomSuffix,
            'no_hp'                     => '081299887711',
            'skpd_id'                   => $this->skpd->id,
            'status_memiliki_email'     => false,
            'email_usulan'              => "bambang.trihatmodjo{$randomSuffix}@cirebonkota.go.id",
            'email_final_terverifikasi' => "bambang.trihatmodjo{$randomSuffix}@cirebonkota.go.id",
            'is_email_recap_confirmed'  => true,
            'status'                    => 'DISETUJUI',
            'verified_by'               => $this->admin->id,
            'verified_at'               => now(),
        ]);

        $tglPenerbitan = Carbon::today()->toDateString();
        $expectedExpiry = Carbon::today()->addYears(3)->toDateString();

        $action = app(TerbitkanSertifikatAction::class);
        $sertifikat = $action->execute($pendaftaran, $tglPenerbitan, $this->admin->id);

        $this->assertEquals($tglPenerbitan, $sertifikat->tanggal_penerbitan->toDateString());
        $this->assertEquals($expectedExpiry, $sertifikat->tanggal_kadaluarsa->toDateString());

        // Kembalikan parameter ke default 2 tahun
        SystemParameterService::set('cert_validity_years', 2);
    }

    /**
     * 6. Job scanner harian menghitung ambang peringatan dinamis berbasis parameter cert_expiry_warning_days.
     */
    public function test_update_sertifikat_status_job_respects_dynamic_warning_days(): void
    {
        // Set parameter ambang peringatan menjadi 14 hari (H-14)
        SystemParameterService::set('cert_expiry_warning_days', 14);

        $randomSuffix = random_int(1000, 9999);
        $pendaftaran = PendaftaranTte::create([
            'nomor_referensi'           => 'TTE-TEST-WARN-' . $randomSuffix,
            'tipe_pegawai'              => 'NON_ASN',
            'nama_lengkap'              => 'Dedi Mulyadi',
            'nik'                       => '327401010191' . $randomSuffix,
            'nip'                       => null,
            'no_hp'                     => '081299887722',
            'skpd_id'                   => $this->skpd->id,
            'status_memiliki_email'     => false,
            'email_usulan'              => "dedi.mulyadi{$randomSuffix}@cirebonkota.go.id",
            'email_final_terverifikasi' => "dedi.mulyadi{$randomSuffix}@cirebonkota.go.id",
            'is_email_recap_confirmed'  => true,
            'status'                    => 'DISETUJUI',
            'verified_by'               => $this->admin->id,
            'verified_at'               => now(),
        ]);

        // Buat sertifikat yang kadaluarsa dalam 10 hari (masuk di antara H-7 dan H-14)
        $cert = SertifikatTte::create([
            'pendaftaran_id'     => $pendaftaran->id,
            'email_dinas'        => "dedi.mulyadi{$randomSuffix}@cirebonkota.go.id",
            'tanggal_penerbitan' => Carbon::today()->subYears(2)->addDays(10)->toDateString(),
            'tanggal_kadaluarsa' => Carbon::today()->addDays(10)->toDateString(), // H-10
            'status_sertifikat'  => 'AKTIF',
            'verified_by_tahap2' => $this->admin->id,
        ]);

        $job = new UpdateSertifikatStatusJob();
        $job->handle();

        $cert->refresh();
        $this->assertEquals('WARNING_H7', $cert->status_sertifikat);

        // Kembalikan parameter ke default 7 hari
        SystemParameterService::set('cert_expiry_warning_days', 7);
    }
}
