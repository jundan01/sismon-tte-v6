<?php

namespace App\Domain\Pendaftaran\Actions;

use App\Domain\Analytics\Models\AuditActionHistory;
use App\Domain\Master\Models\Skpd;
use App\Domain\Pendaftaran\Models\PendaftaranTte;
use App\Domain\Sertifikat\Models\SertifikatTte;
use App\Services\EmailGeneratorService;
use App\Services\Security\CryptoHelper;
use App\Services\SystemParameterService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Action Impor Data Historis Pegawai & Sertifikat TTE Eksisting (CSV).
 * Memproses batch import dengan sanitasi data, enkripsi deterministik, pencegahan duplikasi,
 * dan penerbitan sertifikat historis secara otomatis.
 * Ref: implementation.md §4.2.2 (V6.5)
 */
class ImportHistoricalDataAction
{
    public function __construct(
        protected EmailGeneratorService $emailGenerator
    ) {}

    /**
     * Eksekusi parsing dan import berkas CSV data historis.
     *
     * @param string $filePath Path file CSV di sistem berkas lokal
     * @param int $userId ID Superadmin pelaksana import
     * @param bool $dryRun Jika true, hanya validasi tanpa persistensi ke basis data
     * @return array{total_rows: int, imported_count: int, skipped_count: int, error_count: int, errors: array, skipped: array}
     */
    public function execute(string $filePath, int $userId, bool $dryRun = false): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \InvalidArgumentException("Berkas file CSV import tidak ditemukan atau tidak dapat dibaca: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Gagal membuka file stream: {$filePath}");
        }

        // 1. Deteksi Delimiter (Koma ',' atau Titik Koma ';')
        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = ',';
        if ($firstLine !== false && substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        }

        // 2. Baca Header dan Mapping Kolom
        $rawHeaders = fgetcsv($handle, 0, $delimiter);
        if ($rawHeaders === false) {
            fclose($handle);
            throw new \InvalidArgumentException("Berkas CSV kosong atau tidak memiliki baris header.");
        }

        $headerMap = $this->mapHeaders($rawHeaders);

        // Validasi ketersediaan kolom primer wajib
        if (!isset($headerMap['nama_lengkap']) || !isset($headerMap['nik'])) {
            fclose($handle);
            throw new \InvalidArgumentException("Format CSV tidak valid. Kolom 'nama_lengkap' dan 'nik' wajib tersedia pada header CSV.");
        }

        // 3. Pre-cache Master SKPD untuk performa lookup cepat
        $skpdList = Skpd::where('is_active', true)->get();
        $fallbackSkpd = $skpdList->first();

        $validityYears = (int) SystemParameterService::get('cert_validity_years', 2);
        if ($validityYears <= 0) {
            $validityYears = 2;
        }

        $totalRows = 0;
        $importedCount = 0;
        $skippedCount = 0;
        $errors = [];
        $skipped = [];

        // 4. Baca Baris Data Secara Iteratif
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $totalRows++;
            $rowNum = $totalRows + 1; // 1-based index termasuk header

            // Lewati baris kosong
            if (empty(array_filter($row, fn($val) => trim((string)$val) !== ''))) {
                continue;
            }

            $namaLengkap = isset($row[$headerMap['nama_lengkap']]) ? trim($row[$headerMap['nama_lengkap']]) : '';
            $rawNik = isset($row[$headerMap['nik']]) ? preg_replace('/\D/', '', trim($row[$headerMap['nik']])) : '';
            $rawNip = isset($headerMap['nip']) && isset($row[$headerMap['nip']]) ? preg_replace('/\D/', '', trim($row[$headerMap['nip']])) : null;
            $rawTipe = isset($headerMap['tipe_pegawai']) && isset($row[$headerMap['tipe_pegawai']]) ? strtoupper(trim($row[$headerMap['tipe_pegawai']])) : 'ASN';
            $rawSkpd = isset($headerMap['skpd']) && isset($row[$headerMap['skpd']]) ? trim($row[$headerMap['skpd']]) : '';
            $noHp = isset($headerMap['no_hp']) && isset($row[$headerMap['no_hp']]) ? trim($row[$headerMap['no_hp']]) : '081200000000';
            $emailDinas = isset($headerMap['email_dinas']) && isset($row[$headerMap['email_dinas']]) ? strtolower(trim($row[$headerMap['email_dinas']])) : null;
            $rawTglTerbit = isset($headerMap['tanggal_penerbitan']) && isset($row[$headerMap['tanggal_penerbitan']]) ? trim($row[$headerMap['tanggal_penerbitan']]) : null;
            $rawStatusSertifikat = isset($headerMap['status_sertifikat']) && isset($row[$headerMap['status_sertifikat']]) ? strtoupper(trim($row[$headerMap['status_sertifikat']])) : 'AKTIF';

            // Validasi nama lengkap
            if (empty($namaLengkap)) {
                $errors[] = "Baris {$rowNum}: Nama lengkap tidak boleh kosong.";
                continue;
            }

            // Validasi NIK (16 digit angka)
            if (strlen($rawNik) !== 16) {
                $errors[] = "Baris {$rowNum} ({$namaLengkap}): NIK harus tepat 16 digit angka (Ditemukan: '{$rawNik}').";
                continue;
            }

            // Validasi Tipe Pegawai
            $tipePegawai = in_array($rawTipe, ['ASN', 'NON_ASN'], true) ? $rawTipe : 'ASN';
            $nipValue = !empty($rawNip) ? $rawNip : null;

            // Resolusi SKPD
            $matchedSkpd = null;
            if (!empty($rawSkpd)) {
                $matchedSkpd = $skpdList->first(function ($s) use ($rawSkpd) {
                    return strcasecmp($s->kode_skpd, $rawSkpd) === 0 ||
                           strcasecmp($s->nama_skpd, $rawSkpd) === 0 ||
                           stripos($s->nama_skpd, $rawSkpd) !== false;
                });
            }
            if (!$matchedSkpd) {
                $matchedSkpd = $fallbackSkpd;
            }

            // Cek Duplikasi NIK aktif (Anti-Collision)
            $encryptedNik = CryptoHelper::encryptField($rawNik, 'nik');
            $existingPendaftaran = PendaftaranTte::where('nik', $encryptedNik)
                ->where('status', '!=', 'DITOLAK')
                ->first();

            if ($existingPendaftaran) {
                $skippedCount++;
                $skipped[] = "Baris {$rowNum} ({$namaLengkap}): NIK telah terdaftar sebelumnya pada no referensi {$existingPendaftaran->nomor_referensi}.";
                continue;
            }

            if ($dryRun) {
                $importedCount++;
                continue;
            }

            // 5. Eksekusi Penyimpanan Database Atomik
            try {
                DB::transaction(function () use (
                    $namaLengkap, $rawNik, $nipValue, $tipePegawai, $noHp, $matchedSkpd,
                    $emailDinas, $rawTglTerbit, $rawStatusSertifikat, $validityYears, $userId, &$importedCount
                ) {
                    $cleanNama = $this->emailGenerator->cleanFullName($namaLengkap);

                    // Buat email usulan jika email dinas belum ada
                    $emailUsulan = null;
                    if (empty($emailDinas)) {
                        $emailUsulan = $this->emailGenerator->generate($cleanNama, $nipValue);
                    }

                    $hasCertificate = !empty($rawTglTerbit);
                    $statusPendaftaran = $hasCertificate ? 'DISETUJUI' : 'MENUNGGU_VERIFIKASI';

                    $pendaftaran = PendaftaranTte::create([
                        'nomor_referensi'           => $this->generateNomorReferensi(),
                        'tipe_pegawai'              => $tipePegawai,
                        'nama_lengkap'              => $cleanNama,
                        'nik'                       => $rawNik,
                        'nip'                       => $nipValue,
                        'no_hp'                     => $noHp,
                        'skpd_id'                   => $matchedSkpd ? $matchedSkpd->id : 1,
                        'status_memiliki_email'     => !empty($emailDinas),
                        'email_existing'            => $emailDinas,
                        'email_usulan'              => $emailUsulan,
                        'email_final_terverifikasi' => $emailDinas,
                        'is_email_recap_confirmed'  => $hasCertificate,
                        'status'                    => $statusPendaftaran,
                    ]);

                    // Jika menyertakan tanggal penerbitan, terbitkan rekaman sertifikat historis
                    if ($hasCertificate) {
                        try {
                            $tglTerbit = Carbon::parse($rawTglTerbit);
                            $tglKadaluarsa = $tglTerbit->copy()->addYears($validityYears);

                            $statusSertifikat = in_array($rawStatusSertifikat, ['AKTIF', 'WARNING_H7', 'WARNING_H3', 'EXPIRED', 'REVOKED'], true)
                                ? $rawStatusSertifikat
                                : ($tglKadaluarsa->isPast() ? 'EXPIRED' : 'AKTIF');

                            SertifikatTte::create([
                                'pendaftaran_id'     => $pendaftaran->id,
                                'email_dinas'        => $emailDinas ?: ($emailUsulan ?: 'tte@cirebonkota.go.id'),
                                'tanggal_penerbitan' => $tglTerbit->toDateString(),
                                'tanggal_kadaluarsa' => $tglKadaluarsa->toDateString(),
                                'status_sertifikat'  => $statusSertifikat,
                                'verified_by_tahap2' => $userId,
                                'verified_at_tahap2' => now(),
                                'verified_ip_tahap2' => request()->ip() ?? '127.0.0.1',
                            ]);
                        } catch (\Throwable $e) {
                            Log::warning("ImportHistoricalDataAction: Gagal mem-parse tanggal terbit historis '{$rawTglTerbit}': " . $e->getMessage());
                        }
                    }

                    $importedCount++;
                });
            } catch (\Throwable $e) {
                Log::error("ImportHistoricalDataAction: Gagal mengimpor baris {$rowNum}: " . $e->getMessage());
                $errors[] = "Baris {$rowNum} ({$namaLengkap}): " . $e->getMessage();
            }
        }

        fclose($handle);

        // 6. Pencatatan Audit Trail
        if (!$dryRun && $importedCount > 0) {
            AuditActionHistory::create([
                'action_type'    => 'IMPORT_HISTORICAL_DATA',
                'entity_type'    => 'PendaftaranTte',
                'entity_id'      => 'BATCH_' . date('Ymd_His'),
                'user_id'        => $userId,
                'previous_state' => [],
                'new_state'      => [
                    'file_name'      => basename($filePath),
                    'total_rows'     => $totalRows,
                    'imported_count' => $importedCount,
                    'skipped_count'  => $skippedCount,
                    'error_count'    => count($errors),
                ],
                'is_undone'      => false,
            ]);
        }

        return [
            'total_rows'     => $totalRows,
            'imported_count' => $importedCount,
            'skipped_count'  => $skippedCount,
            'error_count'    => count($errors),
            'errors'         => $errors,
            'skipped'        => $skipped,
        ];
    }

    /**
     * Map indeks kolom CSV berdasarkan nama header yang fleksibel.
     */
    protected function mapHeaders(array $rawHeaders): array
    {
        $map = [];

        foreach ($rawHeaders as $index => $header) {
            // Bersihkan BOM UTF-8 dan spasi
            $clean = strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header)));

            if (in_array($clean, ['nama', 'nama_lengkap', 'nama lengkap', 'nama pegawai', 'nama_pegawai'], true)) {
                $map['nama_lengkap'] = $index;
            } elseif (in_array($clean, ['nik', 'no_nik', 'nomor_nik', 'nomor induk kependudukan'], true)) {
                $map['nik'] = $index;
            } elseif (in_array($clean, ['nip', 'no_nip', 'nomor_nip', 'nomor induk pegawai'], true)) {
                $map['nip'] = $index;
            } elseif (in_array($clean, ['tipe', 'tipe_pegawai', 'tipe pegawai', 'kategori', 'status_kepegawaian'], true)) {
                $map['tipe_pegawai'] = $index;
            } elseif (in_array($clean, ['skpd', 'nama_skpd', 'nama skpd', 'unit_kerja', 'unit kerja', 'instansi'], true)) {
                $map['skpd'] = $index;
            } elseif (in_array($clean, ['no_hp', 'no hp', 'nohp', 'telepon', 'no telepon', 'kontak', 'whatsapp'], true)) {
                $map['no_hp'] = $index;
            } elseif (in_array($clean, ['email', 'email_dinas', 'email dinas', 'pos-el', 'pos el'], true)) {
                $map['email_dinas'] = $index;
            } elseif (in_array($clean, ['tanggal_penerbitan', 'tanggal penerbitan', 'tgl_terbit', 'tgl terbit', 'tanggal terbit'], true)) {
                $map['tanggal_penerbitan'] = $index;
            } elseif (in_array($clean, ['status_sertifikat', 'status sertifikat', 'status'], true)) {
                $map['status_sertifikat'] = $index;
            }
        }

        return $map;
    }

    /**
     * Generate format nomor referensi unik: TTE-YYYYMMDD-XXXXXX.
     */
    protected function generateNomorReferensi(): string
    {
        $attempts = 0;
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
            $nomor = 'TTE-' . date('Ymd') . '-' . $code;
            $attempts++;
            if ($attempts >= 5) {
                $nomor = 'TTE-' . date('Ymd') . '-' . strtoupper(substr(\Illuminate\Support\Str::uuid()->toString(), 0, 8));
                break;
            }
        } while (PendaftaranTte::where('nomor_referensi', $nomor)->exists());

        return $nomor;
    }
}
