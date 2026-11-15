<?php

namespace Tests\Feature;

use App\Domain\Analytics\Models\AuditActionHistory;
use App\Domain\Analytics\Models\SkpdTargetMetric;
use App\Domain\Master\Models\Skpd;
use App\Models\User;
use App\Services\SystemParameterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminSettingsAndManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected Skpd $skpd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skpd = Skpd::first() ?? Skpd::create([
            'kode_skpd' => 'SKPD-TEST-SETTINGS',
            'nama_skpd' => 'Dinas Pengaturan Test',
            'singkatan' => 'DPT',
            'is_active' => true,
            'total_asn' => 200,
        ]);

        $this->admin = User::first() ?? User::factory()->create([
            'role' => 'superadmin',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_settings_or_kunjungan_or_target(): void
    {
        $this->get('/admin/settings/parameters')->assertRedirect();
        $this->get('/admin/target-skpd')->assertRedirect();
    }

    public function test_superadmin_can_view_system_parameters_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/settings/parameters');

        $response->assertStatus(200);
        $response->assertSee('Pengaturan Parameter Kebijakan Sistem');
        $response->assertSee('cert_validity_years');
        $response->assertSee('allow_non_asn_registration');
        $response->assertSee('bulk_verification_max_batch');
        $response->assertSee('city_adoption_target_percent');
    }

    public function test_superadmin_can_update_system_parameters(): void
    {
        $response = $this->actingAs($this->admin)->put('/admin/settings/parameters', [
            'parameters' => [
                'cert_validity_years'          => 3,
                'cert_expiry_warning_days'     => 14,
                'allow_non_asn_registration'   => '1',
                'bulk_verification_max_batch'  => 75,
                'city_adoption_target_percent' => 95,
            ],
        ]);

        $response->assertRedirect(route('admin.settings.parameters'));
        $response->assertSessionHas('success');

        // Verifikasi pembaharuan langsung terbaca via SystemParameterService (Cache Invalidation)
        $this->assertEquals(3, SystemParameterService::get('cert_validity_years'));
        $this->assertEquals(14, SystemParameterService::get('cert_expiry_warning_days'));
        $this->assertTrue(SystemParameterService::get('allow_non_asn_registration'));
        $this->assertEquals(75, SystemParameterService::get('bulk_verification_max_batch'));
        $this->assertEquals(95, SystemParameterService::get('city_adoption_target_percent'));

        // Verifikasi audit log tercatat
        $this->assertTrue(
            AuditActionHistory::where('action_type', 'UPDATE_SYSTEM_PARAMETERS')->exists()
        );
    }

    public function test_system_parameter_validation_guards(): void
    {
        // Nilai cert_validity_years di bawah batas minimal 1
        $response = $this->actingAs($this->admin)->put('/admin/settings/parameters', [
            'parameters' => [
                'cert_validity_years' => 0,
            ],
        ]);

        $response->assertSessionHasErrors(['parameters.cert_validity_years']);

        // Nilai bulk_verification_max_batch melebihi batas maksimal 200
        $response2 = $this->actingAs($this->admin)->put('/admin/settings/parameters', [
            'parameters' => [
                'bulk_verification_max_batch' => 500,
            ],
        ]);

        $response2->assertSessionHasErrors(['parameters.bulk_verification_max_batch']);
    }

    public function test_superadmin_can_view_target_skpd_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/target-skpd?tahun=2026&bulan=10');

        $response->assertStatus(200);
        $response->assertSee('Penetapan Target Adopsi TTE SKPD Bulanan');
        $response->assertSee('Periode Target:');
        $response->assertSee('Oktober 2026');
        $response->assertSee($this->skpd->nama_skpd);
    }

    public function test_superadmin_can_update_target_skpd(): void
    {
        $response = $this->actingAs($this->admin)->put('/admin/target-skpd', [
            'tahun'   => 2026,
            'bulan'   => 10,
            'targets' => [
                $this->skpd->id => [
                    'target_persentase'     => 85.50,
                    'target_jumlah_pegawai' => 171,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.target-skpd.index', ['tahun' => 2026, 'bulan' => 10]));
        $response->assertSessionHas('success');

        $metric = SkpdTargetMetric::where('skpd_id', $this->skpd->id)
            ->where('tahun', 2026)
            ->where('bulan', 10)
            ->first();

        $this->assertNotNull($metric);
        $this->assertEquals(85.50, $metric->target_persentase);
        $this->assertEquals(171, $metric->target_jumlah_pegawai);

        $this->assertTrue(
            AuditActionHistory::where('action_type', 'UPDATE_SKPD_TARGET_METRICS')->exists()
        );
    }

    public function test_superadmin_can_bulk_set_default_target_skpd(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/target-skpd/bulk-default', [
            'tahun' => 2026,
            'bulan' => 11,
        ]);

        $response->assertRedirect(route('admin.target-skpd.index', ['tahun' => 2026, 'bulan' => 11]));
        $response->assertSessionHas('success');

        $metric = SkpdTargetMetric::where('skpd_id', $this->skpd->id)
            ->where('tahun', 2026)
            ->where('bulan', 11)
            ->first();

        $this->assertNotNull($metric);
        $this->assertEquals(100.00, $metric->target_persentase);
        $this->assertEquals($this->skpd->total_asn, $metric->target_jumlah_pegawai);
    }
}
