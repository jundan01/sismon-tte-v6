<?php

namespace Tests\Feature;

use App\Domain\Analytics\Models\AuditActionHistory;
use App\Domain\Master\Models\Skpd;
use App\Domain\Pendaftaran\Models\PendaftaranTte;
use App\Domain\Sertifikat\Models\SertifikatTte;
use App\Mail\SertifikatExpiringNotification;
use App\Models\User;
use App\Services\Security\CryptoHelper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Feature Test Suite: Modul Ekspor PDF Resmi, Scheduler Notifikasi H-7 & Impor Data Historis CSV (V6.5).
 * Ref: implementation.md §4.6.3, §4.6.4, §4.2.2
 */
class PdfExportAndSchedulerTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected Skpd $skpd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skpd = Skpd::first() ?? Skpd::create([
            'kode_skpd' => 'SKPD-TEST-PDF',
            'nama_skpd' => 'Dinas Komunikasi dan Informatika Test',
            'singkatan' => 'DKIS',
            'is_active' => true,
            'total_asn' => 150,
        ]);

        $this->admin = User::first() ?? User::factory()->create([
            'role' => 'superadmin',
        ]);
    }

    /**
     * 1. Pengujian proteksi akses rute ekspor PDF dan impor data historis bagi pengguna non-autentikasi.
     */
    public function test_unauthenticated_user_cannot_access_export_pdf_or_import_historical_data(): void
    {
        $this->get('/admin/export/pdf')->assertRedirect();
        $this->get('/admin/import/historical-data')->assertRedirect();
        $this->get('/admin/import/historical-data/template')->assertRedirect();
        $this->post('/admin/import/historical-data')->assertRedirect();
    }

    /**
     * 2. Pengujian ekspor PDF resmi: HTTP 200, Content-Type application/pdf, dan header biner PDF.
     */
    public function test_superadmin_can_export_pdf_report_with_valid_pdf_stream_or_download(): void
    {
        // Buat data pendaftaran dummy
        $pendaftaran = PendaftaranTte::create([
            'nomor_referensi'       => 'TTE-TEST-PDF-001',
            'tipe_pegawai'          => 'ASN',
            'nama_lengkap'          => 'Budi Test Pdf',
            'nik'                   => '3274010101880001',
            'nip'                   => '198801012015011001',
            'no_hp'                 => '081234567890',
            'skpd_id'               => $this->skpd->id,
            'status_memiliki_email' => true,
            'email_existing'        => 'budi.pdf@cirebonkota.go.id',
            'status'                => 'DISETUJUI',
        ]);

        // Uji ekspor mode unduh default
        $response = $this->actingAs($this->admin)->get('/admin/export/pdf');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        // Uji ekspor mode stream (buka di tab browser)
        $streamResponse = $this->actingAs($this->admin)->get('/admin/export/pdf?stream=1');
        $streamResponse->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $streamResponse->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $streamResponse->getContent());
    }

    /**
     * 3. Pengujian filter ekspor PDF berdasarkan SKPD, tanggal, dan status pendaftaran.
     */
    public function test_superadmin_can_export_pdf_with_skpd_and_status_filters(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/export/pdf?' . http_build_query([
            'skpd_id'           => $this->skpd->id,
            'status'            => 'DISETUJUI',
            'status_sertifikat' => 'AKTIF',
            'tipe_pegawai'      => 'ASN',
            'start_date'        => date('Y-01-01'),
            'end_date'          => date('Y-12-31'),
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * 4. Pengujian scheduler artisan command tte:notify-expiring-h7 yang mengirimkan email peringatan H-7.
     */
    public function test_scheduler_notify_expiring_h7_command_identifies_and_sends_email(): void
    {
        Mail::fake();

        // Buat pendaftaran dan sertifikat yang kedaluwarsa tepat 7 hari lagi
        $targetDate = today()->addDays(7)->toDateString();

        $pendaftaran = PendaftaranTte::create([
            'nomor_referensi'           => 'TTE-H7-NOTIF-001',
            'tipe_pegawai'              => 'ASN',
            'nama_lengkap'              => 'Pegawai Mendekati H7',
            'nik'                       => '3274020202880002',
            'nip'                       => '198802022015021002',
            'no_hp'                     => '081223344556',
            'skpd_id'                   => $this->skpd->id,
            'status_memiliki_email'     => true,
            'email_existing'            => 'h7.test@cirebonkota.go.id',
            'email_final_terverifikasi' => 'h7.test@cirebonkota.go.id',
            'status'                    => 'DISETUJUI',
        ]);

        $sertifikat = SertifikatTte::create([
            'pendaftaran_id'     => $pendaftaran->id,
            'email_dinas'        => 'h7.test@cirebonkota.go.id',
            'tanggal_penerbitan' => today()->subYears(2)->addDays(7)->toDateString(),
            'tanggal_kadaluarsa' => $targetDate,
            'status_sertifikat'  => 'AKTIF',
            'verified_by_tahap2' => $this->admin->id,
            'verified_at_tahap2' => now(),
        ]);

        // Buat pendaftaran dan sertifikat lain yang masih lama (30 hari lagi) yang TIDAK boleh dikirimkan notifikasi
        $pendaftaran2 = PendaftaranTte::create([
            'nomor_referensi'           => 'TTE-H7-NOTIF-002',
            'tipe_pegawai'              => 'ASN',
            'nama_lengkap'              => 'Pegawai Masih Lama',
            'nik'                       => '3274020202880099',
            'nip'                       => '198802022015021099',
            'no_hp'                     => '081223344599',
            'skpd_id'                   => $this->skpd->id,
            'status_memiliki_email'     => true,
            'email_existing'            => 'lama.test@cirebonkota.go.id',
            'email_final_terverifikasi' => 'lama.test@cirebonkota.go.id',
            'status'                    => 'DISETUJUI',
        ]);

        $sertifikatLama = SertifikatTte::create([
            'pendaftaran_id'     => $pendaftaran2->id,
            'email_dinas'        => 'lama.test@cirebonkota.go.id',
            'tanggal_penerbitan' => today()->subYears(2)->addDays(30)->toDateString(),
            'tanggal_kadaluarsa' => today()->addDays(30)->toDateString(),
            'status_sertifikat'  => 'AKTIF',
            'verified_by_tahap2' => $this->admin->id,
            'verified_at_tahap2' => now(),
        ]);

        // Jalankan perintah artisan secara eksplisit pada ambang 7 hari
        $exitCode = Artisan::call('tte:notify-expiring-h7', ['--days' => 7]);

        $this->assertEquals(0, $exitCode);

        // Verifikasi bahwa email terkirim ke target H-7
        Mail::assertSent(SertifikatExpiringNotification::class, function ($mail) {
            return $mail->hasTo('h7.test@cirebonkota.go.id') && $mail->sisaHari === 7;
        });

        // Verifikasi bahwa sertifikat yang masih 30 hari lagi TIDAK dikirimkan email
        Mail::assertNotSent(SertifikatExpiringNotification::class, function ($mail) {
            return $mail->hasTo('lama.test@cirebonkota.go.id');
        });

        // Verifikasi rekaman audit trail
        $this->assertDatabaseHas('audit_action_history', [
            'action_type' => 'NOTIFIKASI_EXPIRING_H7',
            'entity_type' => 'SertifikatTte',
            'entity_id'   => (string) $sertifikat->id,
        ]);
    }

    /**
     * 5. Pengujian mode simulasi (--dry-run) command tte:notify-expiring-h7 tanpa mengirimkan email nyata.
     */
    public function test_scheduler_notify_expiring_h7_command_dry_run_does_not_send_email(): void
    {
        Mail::fake();

        $targetDate = today()->addDays(7)->toDateString();

        $pendaftaran = PendaftaranTte::create([
            'nomor_referensi'           => 'TTE-H7-SIMULASI-001',
            'tipe_pegawai'              => 'ASN',
            'nama_lengkap'              => 'Pegawai Uji Simulasi',
            'nik'                       => '3274030303880003',
            'nip'                       => '198803032015031003',
            'no_hp'                     => '081233445566',
            'skpd_id'                   => $this->skpd->id,
            'status_memiliki_email'     => true,
            'email_existing'            => 'simulasi.test@cirebonkota.go.id',
            'email_final_terverifikasi' => 'simulasi.test@cirebonkota.go.id',
            'status'                    => 'DISETUJUI',
        ]);

        SertifikatTte::create([
            'pendaftaran_id'     => $pendaftaran->id,
            'email_dinas'        => 'simulasi.test@cirebonkota.go.id',
            'tanggal_penerbitan' => today()->subYears(2)->addDays(7)->toDateString(),
            'tanggal_kadaluarsa' => $targetDate,
            'status_sertifikat'  => 'AKTIF',
            'verified_by_tahap2' => $this->admin->id,
            'verified_at_tahap2' => now(),
        ]);

        $exitCode = Artisan::call('tte:notify-expiring-h7', ['--dry-run' => true]);

        $this->assertEquals(0, $exitCode);
        Mail::assertNothingSent();
    }

    /**
     * 6. Pengujian halaman modul impor data historis dan unduh berkas template CSV standar.
     */
    public function test_superadmin_can_view_import_historical_page_and_download_csv_template(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/import/historical-data');
        $response->assertStatus(200);
        $response->assertSee('Impor Data Historis Pegawai', false);
        $response->assertSee('Unduh Template CSV Standar');

        $templateResponse = $this->actingAs($this->admin)->get('/admin/import/historical-data/template');
        $templateResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', $templateResponse->headers->get('Content-Type'));
        $content = $templateResponse->streamedContent();
        $this->assertStringContainsString('nama_lengkap,nik,nip,tipe_pegawai,skpd', $content);
    }

    /**
     * 7. Pengujian batch import data historis via berkas CSV: persistensi, enkripsi, dan penerbitan sertifikat.
     */
    public function test_superadmin_can_import_historical_data_via_csv(): void
    {
        $csvContent = "\xEF\xBB\xBF" .
            "nama_lengkap,nik,nip,tipe_pegawai,skpd,no_hp,email_dinas,tanggal_penerbitan,status_sertifikat\n" .
            "Dedi Supriadi,3274040404880004,198804042015041004,ASN,{$this->skpd->nama_skpd},081299887766,dedi.supriadi@cirebonkota.go.id,2025-05-10,AKTIF\n" .
            "Rina Non Asn,3274050505920005,,NON_ASN,{$this->skpd->nama_skpd},085611223344,rina.thl@cirebonkota.go.id,,";

        $file = UploadedFile::fake()->createWithContent('data_historis.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post('/admin/import/historical-data', [
            'file_csv' => $file,
            'dry_run'  => 0,
        ]);

        $response->assertRedirect(route('admin.import.index'));
        $response->assertSessionHas('success');

        // Verifikasi baris ASN (dengan tanggal penerbitan)
        $encryptedNikDedi = CryptoHelper::encryptField('3274040404880004');
        $pendaftaranDedi = PendaftaranTte::where('nik', $encryptedNikDedi)->first();
        $this->assertNotNull($pendaftaranDedi);
        $this->assertEquals('Dedi Supriadi', $pendaftaranDedi->nama_lengkap);
        $this->assertEquals('ASN', $pendaftaranDedi->tipe_pegawai);
        $this->assertEquals('DISETUJUI', $pendaftaranDedi->status);
        $this->assertEquals('dedi.supriadi@cirebonkota.go.id', $pendaftaranDedi->email_final_terverifikasi);

        // Verifikasi sertifikat historis Dedi terbit otomatis
        $sertifikatDedi = SertifikatTte::where('pendaftaran_id', $pendaftaranDedi->id)->first();
        $this->assertNotNull($sertifikatDedi);
        $this->assertEquals('2025-05-10', $sertifikatDedi->tanggal_penerbitan->toDateString());

        $validityYears = (int) \App\Services\SystemParameterService::get('cert_validity_years', 2);
        $expectedExpiry = \Carbon\Carbon::parse('2025-05-10')->addYears($validityYears)->toDateString();
        $this->assertEquals($expectedExpiry, $sertifikatDedi->tanggal_kadaluarsa->toDateString());
        $this->assertEquals('AKTIF', $sertifikatDedi->status_sertifikat);

        // Verifikasi baris Non-ASN (tanpa tanggal penerbitan)
        $encryptedNikRina = CryptoHelper::encryptField('3274050505920005');
        $pendaftaranRina = PendaftaranTte::where('nik', $encryptedNikRina)->first();
        $this->assertNotNull($pendaftaranRina);
        $this->assertEquals('Rina Non Asn', $pendaftaranRina->nama_lengkap);
        $this->assertEquals('NON_ASN', $pendaftaranRina->tipe_pegawai);
        $this->assertNull($pendaftaranRina->nip);
        $this->assertEquals('MENUNGGU_VERIFIKASI', $pendaftaranRina->status);
        $this->assertFalse($pendaftaranRina->sertifikat()->exists());

        // Verifikasi audit log batch
        $this->assertDatabaseHas('audit_action_history', [
            'action_type' => 'IMPORT_HISTORICAL_DATA',
        ]);
    }

    /**
     * 8. Pengujian pencegahan duplikasi NIK pada saat impor data historis CSV.
     */
    public function test_historical_import_skips_duplicate_nik_gracefully(): void
    {
        // Daftarkan NIK terlebih dahulu
        PendaftaranTte::create([
            'nomor_referensi'       => 'TTE-EXISTING-DUP-01',
            'tipe_pegawai'          => 'ASN',
            'nama_lengkap'          => 'Pegawai Eksisting Duplikat',
            'nik'                   => '3274060606880006',
            'nip'                   => '198806062015061006',
            'no_hp'                 => '081244556677',
            'skpd_id'               => $this->skpd->id,
            'status_memiliki_email' => false,
            'status'                => 'MENUNGGU_VERIFIKASI',
        ]);

        // Berkas CSV berisi 1 NIK duplikat dan 1 NIK baru
        $csvContent = "nama_lengkap,nik,nip,tipe_pegawai,skpd\n" .
            "Duplikat Orang,3274060606880006,198806062015061006,ASN,{$this->skpd->nama_skpd}\n" .
            "Orang Baru,3274070707880007,198807072015071007,ASN,{$this->skpd->nama_skpd}\n";

        $file = UploadedFile::fake()->createWithContent('data_duplikat.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post('/admin/import/historical-data', [
            'file_csv' => $file,
            'dry_run'  => 0,
        ]);

        $response->assertRedirect(route('admin.import.index'));
        $response->assertSessionHas('success');
        $this->assertStringContainsString('1 data dilewati', session('success'));

        // Pastikan hanya 1 record untuk NIK eksisting (tidak berlipat ganda)
        $encryptedNikExisting = CryptoHelper::encryptField('3274060606880006');
        $this->assertEquals(1, PendaftaranTte::where('nik', $encryptedNikExisting)->count());

        // Pastikan NIK baru berhasil disimpan
        $encryptedNikBaru = CryptoHelper::encryptField('3274070707880007');
        $this->assertEquals(1, PendaftaranTte::where('nik', $encryptedNikBaru)->count());
    }

    /**
     * 9. Pengujian mode uji coba (dry run) pada import historis tidak menyimpan record ke basis data.
     */
    public function test_historical_import_dry_run_does_not_persist_records(): void
    {
        $csvContent = "nama_lengkap,nik,nip,tipe_pegawai,skpd\n" .
            "Orang Dry Run,3274080808880008,198808082015081008,ASN,{$this->skpd->nama_skpd}\n";

        $file = UploadedFile::fake()->createWithContent('data_dry_run.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post('/admin/import/historical-data', [
            'file_csv' => $file,
            'dry_run'  => 1,
        ]);

        $response->assertRedirect(route('admin.import.index'));
        $response->assertSessionHas('success');
        $this->assertStringContainsString('Simulasi selesai', session('success'));

        $encryptedNikDry = CryptoHelper::encryptField('3274080808880008');
        $this->assertNull(PendaftaranTte::where('nik', $encryptedNikDry)->first());
    }
}
