<?php

namespace Tests\Feature;

use App\Domain\Analytics\Models\AuditActionHistory;
use App\Domain\Master\Models\Skpd;
use App\Domain\Pendaftaran\Models\PendaftaranTte;
use App\Domain\Sertifikat\Actions\TerbitkanSertifikatAction;
use App\Domain\Sertifikat\Models\SertifikatTte;
use App\Models\User;
use App\Services\Security\CryptoHelper;
use App\Services\SystemParameterService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Test Suite Tahap 2 V6:
 * 1. Bulk Verification / Approval Tahap 1 (Maksimum batch limit guard)
 * 2. Bulk Validation / Penerbitan Tahap 2 (Maksimum batch limit guard)
 * 3. Mekanisme Undo Verifikasi (Mitigasi human-error verifikator)
 * 4. Mekanisme Undo Revoke (Pemulihan sertifikat yang salah cabut beserta Audit Trail)
 * Ref: implementation.md §3.1.C, §3.1.F, §4.4, §4.5
 */
class BulkOperationsAndUndoTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected Skpd $skpd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'admin@cirebonkota.go.id')->firstOrFail();
        $this->skpd = Skpd::firstOrFail();
        SystemParameterService::clearCache();
    }

    protected function createPendaftaran(string $status = 'MENUNGGU_VERIFIKASI', array $attributes = []): PendaftaranTte
    {
        $nik = (string) rand(3274010000000001, 3274019999999999);
        $nip = (string) rand(198501012010011001, 199912312020121009);

        return PendaftaranTte::create(array_merge([
            'uuid'                       => (string) Str::uuid(),
            'nomor_referensi'            => 'TTE-' . strtoupper(Str::random(8)),
            'nik'                        => $nik,
            'nip'                        => $nip,
            'nama_lengkap'               => 'Pegawai Test ' . Str::random(5),
            'no_hp'                      => '0812' . rand(10000000, 99999999),
            'skpd_id'                    => $this->skpd->id,
            'tipe_pegawai'               => 'ASN',
            'status_memiliki_email'      => false,
            'email_usulan'               => 'test.' . rand(1000, 9999) . '@cirebonkota.go.id',
            'email_final_terverifikasi'  => 'test.' . rand(1000, 9999) . '@cirebonkota.go.id',
            'is_email_recap_confirmed'   => true,
            'status'                     => $status,
            'registered_ip'              => '127.0.0.1',
        ], $attributes));
    }

    /**
     * 1. Superadmin dapat melakukan persetujuan masal (Bulk Approve) Tahap 1.
     */
    public function test_superadmin_can_bulk_approve_pendaftaran_tahap_1(): void
    {
        $p1 = $this->createPendaftaran('MENUNGGU_VERIFIKASI');
        $p2 = $this->createPendaftaran('MENUNGGU_VERIFIKASI');
        $p3 = $this->createPendaftaran('MENUNGGU_VERIFIKASI');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.verifikasi.bulk-approve'), [
                'uuids' => [$p1->uuid, $p2->uuid, $p3->uuid],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('DISETUJUI', $p1->fresh()->status);
        $this->assertEquals('DISETUJUI', $p2->fresh()->status);
        $this->assertEquals('DISETUJUI', $p3->fresh()->status);

        $this->assertEquals($this->admin->id, $p1->fresh()->verified_by);
        $this->assertNotNull($p1->fresh()->verified_at);

        // Audit Trail terverifikasi di AuditActionHistory
        $this->assertDatabaseHas('audit_action_history', [
            'action_type' => 'BULK_VERIFIKASI_TAHAP1',
            'entity_id'   => $p1->uuid,
            'user_id'     => $this->admin->id,
            'is_undone'   => false,
        ]);
    }

    /**
     * 2. Bulk Approve mematuhi batas batch maksimum dari System Parameter.
     */
    public function test_bulk_approve_respects_batch_limit_parameter(): void
    {
        SystemParameterService::set('bulk_verification_max_batch', 2, 'integer');

        $p1 = $this->createPendaftaran('MENUNGGU_VERIFIKASI');
        $p2 = $this->createPendaftaran('MENUNGGU_VERIFIKASI');
        $p3 = $this->createPendaftaran('MENUNGGU_VERIFIKASI');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.verifikasi.bulk-approve'), [
                'uuids' => [$p1->uuid, $p2->uuid, $p3->uuid],
            ]);

        $response->assertSessionHas('error');
        $this->assertEquals('MENUNGGU_VERIFIKASI', $p1->fresh()->status);
        $this->assertEquals('MENUNGGU_VERIFIKASI', $p2->fresh()->status);
        $this->assertEquals('MENUNGGU_VERIFIKASI', $p3->fresh()->status);
    }

    /**
     * 3. Superadmin dapat melakukan validasi & penerbitan masal Tahap 2.
     */
    public function test_superadmin_can_bulk_validate_sertifikat_tahap_2(): void
    {
        $p1 = $this->createPendaftaran('DISETUJUI');
        $p2 = $this->createPendaftaran('DISETUJUI');

        $tanggalPenerbitan = Carbon::today()->toDateString();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.penerbitan.bulk-validate'), [
                'uuids'              => [$p1->uuid, $p2->uuid],
                'tanggal_penerbitan' => $tanggalPenerbitan,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertNotNull($p1->fresh()->sertifikat);
        $this->assertNotNull($p2->fresh()->sertifikat);

        $this->assertEquals('AKTIF', $p1->fresh()->sertifikat->status_sertifikat);
        $this->assertEquals('AKTIF', $p2->fresh()->sertifikat->status_sertifikat);

        // Audit Trail tersimpan
        $this->assertDatabaseHas('audit_action_history', [
            'action_type' => 'BULK_VALIDASI_TAHAP2',
            'user_id'     => $this->admin->id,
            'is_undone'   => false,
        ]);
    }

    /**
     * 4. Bulk Validate mematuhi batas batch maksimum dari System Parameter.
     */
    public function test_bulk_validate_respects_batch_limit_parameter(): void
    {
        SystemParameterService::set('bulk_verification_max_batch', 1, 'integer');

        $p1 = $this->createPendaftaran('DISETUJUI');
        $p2 = $this->createPendaftaran('DISETUJUI');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.penerbitan.bulk-validate'), [
                'uuids'              => [$p1->uuid, $p2->uuid],
                'tanggal_penerbitan' => Carbon::today()->toDateString(),
            ]);

        $response->assertSessionHas('error');
        $this->assertNull($p1->fresh()->sertifikat);
        $this->assertNull($p2->fresh()->sertifikat);
    }

    /**
     * 5. Superadmin dapat membatalkan (Undo) Verifikasi Tahap 1 jika sertifikat belum diterbitkan.
     */
    public function test_superadmin_can_undo_verifikasi_when_certificate_not_issued(): void
    {
        $pendaftaran = $this->createPendaftaran('DISETUJUI', [
            'verified_by' => $this->admin->id,
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.verifikasi.undo', $pendaftaran->nomor_referensi));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $pFresh = $pendaftaran->fresh();
        $this->assertEquals('MENUNGGU_VERIFIKASI', $pFresh->status);
        $this->assertNull($pFresh->verified_by);
        $this->assertNull($pFresh->verified_at);

        // Audit Trail Undo terdata
        $this->assertDatabaseHas('audit_action_history', [
            'action_type' => 'UNDO_VERIFIKASI_TAHAP1',
            'entity_id'   => $pendaftaran->uuid,
            'user_id'     => $this->admin->id,
            'is_undone'   => true,
        ]);
    }

    /**
     * 6. Verifikasi Tahap 1 TIDAK BISA dibatalkan (Undo) jika sertifikat TTE sudah terbit.
     */
    public function test_cannot_undo_verifikasi_if_certificate_already_issued(): void
    {
        $pendaftaran = $this->createPendaftaran('DISETUJUI', [
            'verified_by' => $this->admin->id,
            'verified_at' => now(),
        ]);

        // Terbitkan sertifikat
        app(TerbitkanSertifikatAction::class)->execute(
            $pendaftaran,
            Carbon::today()->toDateString(),
            $this->admin->id
        );

        $response = $this->actingAs($this->admin)
            ->post(route('admin.verifikasi.undo', $pendaftaran->nomor_referensi));

        // Policy atau validation exception menghentikan aksi undo
        $response->assertStatus(403);
        $this->assertEquals('DISETUJUI', $pendaftaran->fresh()->status);
    }

    /**
     * 7. Superadmin dapat memulihkan (Undo Revoke) sertifikat yang telah dicabut statusnya.
     */
    public function test_superadmin_can_undo_revoke_certificate(): void
    {
        $pendaftaran = $this->createPendaftaran('DISETUJUI');

        $sertifikat = app(TerbitkanSertifikatAction::class)->execute(
            $pendaftaran,
            Carbon::today()->toDateString(),
            $this->admin->id
        );

        // Cabut sertifikat melalui endpoint status
        $revokeResponse = $this->actingAs($this->admin)
            ->postJson(route('admin.penerbitan.status', $sertifikat->uuid), [
                'status_sertifikat' => 'REVOKED',
            ]);

        $revokeResponse->assertJson(['success' => true]);
        $this->assertEquals('REVOKED', $sertifikat->fresh()->status_sertifikat);

        // Pastikan audit REVOKE tercatat
        $this->assertDatabaseHas('audit_action_history', [
            'action_type' => 'REVOKE_SERTIFIKAT',
            'entity_id'   => $sertifikat->uuid,
            'is_undone'   => false,
        ]);

        // Lakukan Undo Revoke
        $undoResponse = $this->actingAs($this->admin)
            ->post(route('admin.penerbitan.undo-revoke', $sertifikat->id));

        $undoResponse->assertRedirect();
        $undoResponse->assertSessionHas('success');

        // Status sertifikat pulih ke AKTIF
        $this->assertEquals('AKTIF', $sertifikat->fresh()->status_sertifikat);

        // Audit log sebelumnya ditandai is_undone = true
        $this->assertDatabaseHas('audit_action_history', [
            'action_type' => 'REVOKE_SERTIFIKAT',
            'entity_id'   => $sertifikat->uuid,
            'is_undone'   => true,
        ]);

        // Audit log pemulihan eksplisit tercatat
        $this->assertDatabaseHas('audit_action_history', [
            'action_type' => 'UNDO_REVOKE_SERTIFIKAT',
            'entity_id'   => $sertifikat->uuid,
            'user_id'     => $this->admin->id,
        ]);
    }

    /**
     * 8. Undo Revoke ditolak jika sertifikat tidak dalam status REVOKED.
     */
    public function test_cannot_undo_revoke_if_certificate_is_not_revoked(): void
    {
        $pendaftaran = $this->createPendaftaran('DISETUJUI');

        $sertifikat = app(TerbitkanSertifikatAction::class)->execute(
            $pendaftaran,
            Carbon::today()->toDateString(),
            $this->admin->id
        );

        // Sertifikat masih AKTIF
        $response = $this->actingAs($this->admin)
            ->post(route('admin.penerbitan.undo-revoke', $sertifikat->id));

        // Ditolak oleh Policy
        $response->assertStatus(403);
        $this->assertEquals('AKTIF', $sertifikat->fresh()->status_sertifikat);
    }
}
