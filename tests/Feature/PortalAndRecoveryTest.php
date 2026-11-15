<?php

namespace Tests\Feature;

use App\Domain\Master\Models\Skpd;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PortalAndRecoveryTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected Skpd $skpd;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear(sha1('pendaftaran_limit|127.0.0.1'));

        $this->skpd = Skpd::first() ?? Skpd::create([
            'kode_skpd' => 'SKPD-TEST-PORTAL',
            'nama_skpd' => 'Dinas Komunikasi dan Informatika Test',
            'singkatan' => 'DKIS',
            'is_active' => true,
            'total_asn' => 150,
        ]);

        $this->admin = User::first() ?? User::factory()->create([
            'role' => 'superadmin',
        ]);
    }

    public function test_public_landing_page_renders_macro_kpi_and_dual_track_options(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Tingkat Adopsi TTE Aparatur Pemerintah Kota Cirebon');
        $response->assertSee('Capaian Kepatuhan Kota');
        $response->assertSee('Sertifikat TTE Aktif');
        $response->assertSee('Permohonan Masuk');
        $response->assertSee('Formulir Pegawai ASN');
        $response->assertSee('Formulir Pegawai Non-ASN');
        $response->assertDontSee('file_bukti_tte');
        $response->assertDontSee('/gateway/login');
        $response->assertDontSee('/admin');
    }

    public function test_public_form_renders_without_file_upload_and_with_dual_track(): void
    {
        $response = $this->get('/formulir');

        $response->assertStatus(200);
        $response->assertSee('Formulir Pendaftaran Sertifikat Elektronik (TTE)');
        $response->assertSee('Jalur ASN');
        $response->assertSee('Jalur Non-ASN');
        $response->assertDontSee('file_bukti_tte');
        $response->assertDontSee('kunjungan.create');
        $response->assertDontSee('recovery.index');
    }

    public function test_portal_api_returns_json_with_macro_stats_and_skpd_list(): void
    {
        $response = $this->getJson('/formulir');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'skpd_list',
            'sub_unit_list',
            'jabatan_list',
            'golongan_list',
            'allow_non_asn',
            'macro_stats' => [
                'total_pegawai',
                'total_sertifikat',
                'total_pendaftaran',
                'total_skpd',
                'target_persentase',
                'persentase_kota',
            ],
        ]);
    }

    public function test_removed_routes_return_404(): void
    {
        $this->get('/recovery')->assertStatus(404);
        $this->post('/recovery', [])->assertStatus(404);
        $this->get('/kunjungan-layanan')->assertStatus(404);
        $this->post('/kunjungan-layanan', [])->assertStatus(404);
        $this->get('/kunjungan-layanan/status')->assertStatus(404);
    }
}
