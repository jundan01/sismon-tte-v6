<?php

namespace Tests\Feature;

use App\Domain\Master\Models\Skpd;
use App\Domain\Pendaftaran\Models\PendaftaranTte;
use App\Models\User;
use App\Services\Security\CryptoHelper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SearchIndexUsageTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superadmin;
    protected Skpd $skpd;
    protected string $sampleNik = '3274010101900001';
    protected string $sampleNip = '199001012020121001';
    protected PendaftaranTte $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::firstOrCreate(
            ['email' => 'admin@cirebonkota.go.id'],
            [
                'name'     => 'Superadmin Test',
                'password' => bcrypt('password'),
                'role'     => 'SUPERADMIN',
            ]
        );

        $this->skpd = Skpd::first() ?? Skpd::create([
            'kode_skpd' => 'DISKOMINFO',
            'nama_skpd' => 'Dinas Komunikasi dan Informatika',
            'is_active' => true,
        ]);

        $this->record = PendaftaranTte::create([
            'uuid'                     => (string) Str::uuid(),
            'nomor_referensi'          => 'REF-INDEX-TEST-001',
            'nama_lengkap'             => 'Pegawai Uji Indeks',
            'nik'                      => $this->sampleNik, // Mutator mengenkripsi secara deterministik
            'nip'                      => $this->sampleNip, // Mutator mengenkripsi secara deterministik
            'no_hp'                    => '081234567890',
            'skpd_id'                  => $this->skpd->id,
            'status'                   => 'MENUNGGU_VERIFIKASI',
            'status_memiliki_email'    => false,
            'is_email_recap_confirmed' => false,
            'tipe_pegawai'             => 'ASN',
            'deskripsi'                => 'Pengujian indeks B-Tree',
        ]);
    }

    /**
     * Uji 1: Memastikan query pencarian dengan input 16-digit (NIK) menggunakan partial index idx_pendaftaran_nik_active.
     */
    public function test_exact_nik_search_uses_partial_btree_index(): void
    {
        // Nonaktifkan sequential scan sementara pada sesi ini agar PostgreSQL membuktikan kelayakan indeks B-Tree
        DB::statement('SET enable_seqscan = OFF');

        $encryptedNik = CryptoHelper::encryptField($this->sampleNik);
        $quotedNik = DB::getPdo()->quote($encryptedNik);

        $explainResults = DB::select("
            EXPLAIN (FORMAT JSON)
            SELECT id, nama_lengkap, nik, status
            FROM pendaftaran_tte
            WHERE status = 'MENUNGGU_VERIFIKASI'
              AND nik = {$quotedNik}
        ");

        DB::statement('SET enable_seqscan = ON');

        $this->assertNotEmpty($explainResults);
        $planJson = json_decode($explainResults[0]->{'QUERY PLAN'}, true);
        $rootPlan = $planJson[0]['Plan'];

        $planString = json_encode($rootPlan);
        $this->assertStringContainsString('Index Scan', $planString, 'Kueri harus menggunakan Index Scan.');
        $this->assertStringContainsString('idx_pendaftaran_nik_active', $planString, 'Kueri harus menggunakan index idx_pendaftaran_nik_active.');
    }

    /**
     * Uji 2: Memastikan query pencarian dengan input 18-digit (NIP) menggunakan partial index idx_pendaftaran_nip_active.
     */
    public function test_exact_nip_search_uses_partial_btree_index(): void
    {
        DB::statement('SET enable_seqscan = OFF');

        $encryptedNip = CryptoHelper::encryptField($this->sampleNip);
        $quotedNip = DB::getPdo()->quote($encryptedNip);

        $explainResults = DB::select("
            EXPLAIN (FORMAT JSON)
            SELECT id, nama_lengkap, nip, status
            FROM pendaftaran_tte
            WHERE status = 'MENUNGGU_VERIFIKASI'
              AND nip = {$quotedNip}
        ");

        DB::statement('SET enable_seqscan = ON');

        $this->assertNotEmpty($explainResults);
        $planJson = json_decode($explainResults[0]->{'QUERY PLAN'}, true);
        $rootPlan = $planJson[0]['Plan'];

        $planString = json_encode($rootPlan);
        $this->assertStringContainsString('Index Scan', $planString, 'Kueri harus menggunakan Index Scan.');
        $this->assertStringContainsString('idx_pendaftaran_nip_active', $planString, 'Kueri harus menggunakan index idx_pendaftaran_nip_active.');
    }

    /**
     * Uji 3: Memastikan VerifikasiController memisahkan rute 16-digit ke pencarian NIK dan berhasil menemukan data.
     */
    public function test_verifikasi_controller_routes_16_digits_to_nik_exact_search(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->getJson(route('admin.verifikasi.index', ['q' => $this->sampleNik]));

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertNotEmpty($data, 'Data hasil pencarian NIK tidak boleh kosong.');
        $this->assertEquals($this->record->id, $data[0]['id']);
    }

    /**
     * Uji 4: Memastikan VerifikasiController memisahkan rute 18-digit ke pencarian NIP dan berhasil menemukan data.
     */
    public function test_verifikasi_controller_routes_18_digits_to_nip_exact_search(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->getJson(route('admin.verifikasi.index', ['q' => $this->sampleNip]));

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertNotEmpty($data, 'Data hasil pencarian NIP tidak boleh kosong.');
        $this->assertEquals($this->record->id, $data[0]['id']);
    }

    /**
     * Uji 5: Memastikan VerifikasiController memisahkan rute teks (nama) ke wildcard text search.
     */
    public function test_verifikasi_controller_routes_text_to_wildcard_search(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->getJson(route('admin.verifikasi.index', ['q' => 'Uji Indeks']));

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertNotEmpty($data, 'Data hasil pencarian nama tidak boleh kosong.');
        $this->assertEquals($this->record->id, $data[0]['id']);
    }
}
