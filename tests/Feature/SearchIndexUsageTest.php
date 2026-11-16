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
    protected PendaftaranTte $activeRecord;

    protected function setUp(): void
    {
        parent::setUp();

        // Guard integritas: pengujian ini HANYA boleh berjalan pada database tte_testing
        if (DB::getDatabaseName() !== 'tte_testing') {
            $this->markTestSkipped('Pengujian ini HANYA diizinkan berjalan pada database tte_testing, terdeteksi: ' . DB::getDatabaseName());
        }

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

        // Pastikan tabel memiliki baseline baris yang cukup dan statistik optimizer up-to-date
        // agar PostgreSQL memilih Index Scan secara natural TANPA 'enable_seqscan = off'
        $existingCount = DB::table('pendaftaran_tte')->count();
        if ($existingCount < 1000) {
            $needed = 1200 - $existingCount;
            DB::statement("
                INSERT INTO pendaftaran_tte (uuid, nomor_referensi, nama_lengkap, nik, nip, no_hp, skpd_id, status, status_memiliki_email, is_email_recap_confirmed, tipe_pegawai, deskripsi)
                SELECT gen_random_uuid(), 'REF-TEST-AUTO-' || i, 'Pegawai Auto ' || i, 'nik_auto_' || i, 'nip_auto_' || i, '08123', 1, 'MENUNGGU_VERIFIKASI', false, false, 'ASN', 'desc'
                FROM generate_series(1, {$needed}) s(i);
            ");
            DB::statement('ANALYZE pendaftaran_tte;');
        }

        // Buat record aktif target pengujian
        $this->activeRecord = PendaftaranTte::create([
            'uuid'                     => (string) Str::uuid(),
            'nomor_referensi'          => 'REF-INDEX-TEST-001',
            'nama_lengkap'             => 'Pegawai Uji Indeks Asli',
            'nik'                      => $this->sampleNik,
            'nip'                      => $this->sampleNip,
            'no_hp'                    => '081234567890',
            'skpd_id'                  => $this->skpd->id,
            'status'                   => 'MENUNGGU_VERIFIKASI',
            'status_memiliki_email'    => false,
            'is_email_recap_confirmed' => false,
            'tipe_pegawai'             => 'ASN',
            'deskripsi'                => 'Pengujian indeks B-Tree alami',
        ]);
    }

    /**
     * Helper: Menangkap query SQL asli dari controller dan menjalankan EXPLAIN (FORMAT JSON)
     */
    protected function explainControllerQuery(callable $action): array
    {
        $capturedQueries = [];

        DB::listen(function ($query) use (&$capturedQueries) {
            $sqlLower = strtolower($query->sql);
            if (str_contains($sqlLower, 'pendaftaran_tte') && str_contains($sqlLower, 'select') && !str_contains($sqlLower, 'count(')) {
                $capturedQueries[] = [
                    'sql'      => $query->sql,
                    'bindings' => $query->bindings,
                ];
            }
        });

        $action();

        $this->assertNotEmpty($capturedQueries, 'Harus ada query SELECT ke pendaftaran_tte yang tereksekusi.');

        $targetQuery = end($capturedQueries);
        $pdo = DB::getPdo();
        $stmt = $pdo->prepare("EXPLAIN (FORMAT JSON) " . $targetQuery['sql']);
        $stmt->execute($targetQuery['bindings']);
        $rawJson = $stmt->fetchColumn();

        $planJson = json_decode($rawJson, true);
        return [
            'sql'      => $targetQuery['sql'],
            'bindings' => $targetQuery['bindings'],
            'plan'     => $planJson[0]['Plan'] ?? [],
        ];
    }

    /**
     * Uji 1: Query SQL asli dari VerifikasiController untuk pencarian 16 digit NIK
     * membuktikan pemanfaatan Index Scan secara alami tanpa enable_seqscan = off.
     */
    public function test_verifikasi_controller_nik_search_generates_and_executes_index_scan(): void
    {
        $result = $this->explainControllerQuery(function () {
            $response = $this->actingAs($this->superadmin)
                ->getJson(route('admin.verifikasi.index', ['q' => $this->sampleNik]));

            $response->assertStatus(200);
            $data = $response->json('data.data');
            $this->assertNotEmpty($data, 'Data hasil pencarian NIK tidak boleh kosong.');
            $this->assertEquals($this->activeRecord->id, $data[0]['id']);
        });

        // Verifikasi query SQL terkompilasi Eloquent menggunakan parameter binding pada kolom nik
        $this->assertStringContainsString('"nik" = ?', $result['sql']);
        $this->assertStringNotContainsString('ilike', strtolower($result['sql']), 'Pencarian NIK tidak boleh memuat ILIKE.');

        // Verifikasi rencana eksekusi database menggunakan Index Scan
        $planString = json_encode($result['plan']);
        $this->assertStringContainsString('Index Scan', $planString, 'Query asli VerifikasiController harus menggunakan Index Scan.');
        $this->assertTrue(
            str_contains($planString, 'idx_pendaftaran_nik_all') || str_contains($planString, 'idx_pendaftaran_nik_active'),
            'Query harus memanfaatkan indeks NIK (idx_pendaftaran_nik_all atau idx_pendaftaran_nik_active).'
        );
    }

    /**
     * Uji 2: Query SQL asli dari VerifikasiController untuk pencarian 18 digit NIP
     * membuktikan pemanfaatan Index Scan secara alami tanpa enable_seqscan = off.
     */
    public function test_verifikasi_controller_nip_search_generates_and_executes_index_scan(): void
    {
        $result = $this->explainControllerQuery(function () {
            $response = $this->actingAs($this->superadmin)
                ->getJson(route('admin.verifikasi.index', ['q' => $this->sampleNip]));

            $response->assertStatus(200);
            $data = $response->json('data.data');
            $this->assertNotEmpty($data, 'Data hasil pencarian NIP tidak boleh kosong.');
            $this->assertEquals($this->activeRecord->id, $data[0]['id']);
        });

        $this->assertStringContainsString('"nip" = ?', $result['sql']);
        $this->assertStringNotContainsString('ilike', strtolower($result['sql']));

        $planString = json_encode($result['plan']);
        $this->assertStringContainsString('Index Scan', $planString, 'Query asli VerifikasiController harus menggunakan Index Scan.');
        $this->assertTrue(
            str_contains($planString, 'idx_pendaftaran_nip_all') || str_contains($planString, 'idx_pendaftaran_nip_active'),
            'Query harus memanfaatkan indeks NIP (idx_pendaftaran_nip_all atau idx_pendaftaran_nip_active).'
        );
    }

    /**
     * Uji 3: Query SQL asli dari DashboardController untuk pencarian 16 digit NIK
     * membuktikan pemanfaatan Index Scan secara alami.
     */
    public function test_dashboard_controller_nik_search_generates_and_executes_index_scan(): void
    {
        $result = $this->explainControllerQuery(function () {
            $response = $this->actingAs($this->superadmin)
                ->get(route('admin.dashboard', ['q' => $this->sampleNik]));

            $response->assertStatus(200);
        });

        $this->assertStringContainsString('"nik" = ?', $result['sql']);

        $planString = json_encode($result['plan']);
        $this->assertStringContainsString('Index Scan', $planString, 'Query asli DashboardController harus menggunakan Index Scan.');
    }

    /**
     * Uji 4: VerifikasiController memisahkan pencarian teks dan melakukan escaping wildcard SQL
     */
    public function test_verifikasi_controller_routes_text_to_wildcard_with_escaping(): void
    {
        // Buat record khusus yang mengandung karakter spesial %, _, dan \
        PendaftaranTte::create([
            'uuid'                     => (string) Str::uuid(),
            'nomor_referensi'          => 'REF-ESC-TEST-001',
            'nama_lengkap'             => 'Pegawai 100% Valid_Escaped',
            'nik'                      => '3274090909990003',
            'no_hp'                    => '081234567892',
            'skpd_id'                  => $this->skpd->id,
            'status'                   => 'MENUNGGU_VERIFIKASI',
            'status_memiliki_email'    => false,
            'is_email_recap_confirmed' => false,
            'tipe_pegawai'             => 'ASN',
            'deskripsi'                => 'Uji escaping wildcard',
        ]);

        $result = $this->explainControllerQuery(function () {
            // Pencarian dengan karakter wildcard SQL: %, _, dan \
            $response = $this->actingAs($this->superadmin)
                ->getJson(route('admin.verifikasi.index', ['q' => '100% Valid_Escaped']));

            $response->assertStatus(200);
            $data = $response->json('data.data');
            $this->assertNotEmpty($data, 'Data hasil pencarian teks escaping harus ditemukan.');
        });

        $sql = strtolower($result['sql']);
        $this->assertStringContainsString('ilike', $sql, 'Query teks harus menggunakan ILIKE.');
        $this->assertStringNotContainsString('"nik" = ?', $sql, 'Query teks tidak boleh mencampur equality nik.');
        $this->assertStringNotContainsString('"nip" = ?', $sql, 'Query teks tidak boleh mencampur equality nip.');

        // Verifikasi bahwa karakter wildcard berhasil di-escape dalam binding
        $bindings = $result['bindings'];
        $hasEscapedBinding = false;
        foreach ($bindings as $b) {
            if (is_string($b) && str_contains($b, '100\% Valid\_Escaped')) {
                $hasEscapedBinding = true;
                break;
            }
        }
        $this->assertTrue($hasEscapedBinding, 'Binding ILIKE harus meng-escape karakter % dan _ dengan backslash.');
    }

    /**
     * Uji 5: Rekaman dengan status DITOLAK tetap terindeks dan menggunakan Index Scan (idx_pendaftaran_nik_all)
     */
    public function test_rejected_status_is_indexed_and_uses_index_scan(): void
    {
        $rejectedNik = '3274090909990002';
        $record = PendaftaranTte::create([
            'uuid'                     => (string) Str::uuid(),
            'nomor_referensi'          => 'REF-REJECT-TEST-002',
            'nama_lengkap'             => 'Pegawai Berstatus Ditolak',
            'nik'                      => $rejectedNik,
            'nip'                      => '199501012020121002',
            'no_hp'                    => '081234567891',
            'skpd_id'                  => $this->skpd->id,
            'status'                   => 'DITOLAK',
            'status_memiliki_email'    => false,
            'is_email_recap_confirmed' => false,
            'tipe_pegawai'             => 'ASN',
            'deskripsi'                => 'Pengujian indeks pada data ditolak',
        ]);

        $result = $this->explainControllerQuery(function () use ($rejectedNik, $record) {
            $response = $this->actingAs($this->superadmin)
                ->getJson(route('admin.verifikasi.index', ['status' => 'DITOLAK', 'q' => $rejectedNik]));

            $response->assertStatus(200);
            $data = $response->json('data.data');
            $this->assertNotEmpty($data, 'Data berstatus DITOLAK harus dapat ditemukan.');
            $this->assertEquals('DITOLAK', $data[0]['status']);
            $this->assertEquals($record->id, $data[0]['id']);
        });

        $planString = json_encode($result['plan']);
        $this->assertStringContainsString('Index Scan', $planString, 'Data DITOLAK harus menggunakan Index Scan.');

        // Uji query langsung pada NIK tanpa status filter untuk membuktikan index idx_pendaftaran_nik_all bekerja mulus
        $encryptedNik = CryptoHelper::encryptField($rejectedNik);
        $explainDirect = DB::select("EXPLAIN (FORMAT JSON) SELECT id, nama_lengkap, nik, status FROM pendaftaran_tte WHERE nik = ?", [$encryptedNik]);
        $directPlanString = json_encode(json_decode($explainDirect[0]->{'QUERY PLAN'}, true));
        $this->assertStringContainsString('Index Scan', $directPlanString);
        $this->assertStringContainsString('idx_pendaftaran_nik_all', $directPlanString, 'Pencarian NIK tanpa filter status harus memanfaatkan idx_pendaftaran_nik_all.');
    }
}
