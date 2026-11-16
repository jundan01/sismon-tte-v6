<?php

namespace App\Services\Security;

use App\Benchmark\Security\BlindIndexService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Utility Enkripsi Deterministik Terautentikasi (Authenticated Deterministic Encryption - DAE).
 *
 * Menerapkan konstruksi SIV-like (Synthetic Initialization Vector via PRF-then-CTR):
 * - Versi 1 (0x01): K_enc dan K_mac diturunkan dari master key via HKDF-SHA256 dengan label konteks terpisah.
 * - V = Truncate_16B(HMAC-SHA256(K_mac, label_kolom || 0x00 || plaintext)).
 * - Ciphertext = versi(1 byte) || V || AES-256-CTR(K_enc, IV=V, plaintext).
 * - Dekripsi: dekripsi CTR, verifikasi ulang V menggunakan timing-safe hash_equals().
 * - Dual-read: otomatis mengenali dan mendekripsi ciphertext warisan v0 (AES-256-CBC) jika bukan v1.
 *
 * Ref: Rogaway & Shrimpton (2006) Deterministic Authenticated-Encryption; UU PDP No. 27/2022.
 */
class CryptoHelper
{
    public const VERSION_1 = "\x01";
    public const CONTEXT_ENC = 'sismon-tte:enc:v1';
    public const CONTEXT_MAC = 'sismon-tte:mac:v1';

    /**
     * Resolve master key dari konfigurasi.
     * Menggunakan DETERMINISTIC_ENCRYPTION_KEY dari .env, fallback ke APP_KEY.
     */
    protected static function resolveDeterministicKey(): string
    {
        $key = config('crypto.deterministic_key');

        if (empty($key)) {
            $key = config('app.key');
        }

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return $key;
    }

    /**
     * Turunkan K_enc dan K_mac dari master key menggunakan HKDF-SHA256 dengan label konteks terpisah.
     *
     * @return array{0: string, 1: string} [K_enc (32 byte), K_mac (32 byte)]
     */
    public static function deriveKeys(string $masterKey): array
    {
        $kEnc = hash_hkdf('sha256', $masterKey, 32, self::CONTEXT_ENC, '');
        $kMac = hash_hkdf('sha256', $masterKey, 32, self::CONTEXT_MAC, '');

        return [$kEnc, $kMac];
    }

    /**
     * Enkripsi field teks secara deterministik terautentikasi (Versi 1: SIV-CTR).
     * Input dan label kolom yang sama selalu menghasilkan ciphertext identik.
     * Perbedaan pada 1 byte plaintext mengacak seluruh 16 byte IV secara total (eliminasi kebocoran prefix).
     *
     * @param string|null $value Nilai plaintext
     * @param string $columnLabel Label konteks kolom (misal: 'nik', 'nip')
     * @return string|null Ciphertext Base64 (1 byte versi + 16 byte V + CTR ciphertext)
     */
    public static function encryptField(?string $value, string $columnLabel = 'generic'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $masterKey = self::resolveDeterministicKey();
        [$kEnc, $kMac] = self::deriveKeys($masterKey);

        // V = HMAC-SHA256(K_mac, label_kolom || 0x00 || plaintext), dipotong 16 byte
        $v = substr(hash_hmac('sha256', $columnLabel . "\x00" . $value, $kMac, true), 0, 16);

        // AES-256-CTR(K_enc, IV=V, plaintext)
        $rawCipher = openssl_encrypt($value, 'aes-256-ctr', $kEnc, OPENSSL_RAW_DATA, $v);
        if ($rawCipher === false) {
            Log::error('CryptoHelper: Gagal mengenkripsi data secara deterministik.');
            return null;
        }

        // Ciphertext biner: versi (1 byte) || V (16 byte) || CTR (panjang plaintext)
        $payload = self::VERSION_1 . $v . $rawCipher;

        return base64_encode($payload);
    }

    /**
     * Dekripsi field terenkripsi dengan mode Dual-Read:
     * - Jika berawalan Versi 1 (0x01): Dekripsi AES-256-CTR dan validasi integritas MAC timing-safe.
     * - Jika format lama (v0): Dekripsi fallback menggunakan AES-256-CBC static IV.
     *
     * @param string|null $ciphertext Ciphertext Base64
     * @param string $columnLabel Label konteks kolom yang diharapkan
     * @return string|null Plaintext asli jika valid, null jika gagal/tampered
     */
    public static function decryptField(?string $ciphertext, string $columnLabel = 'generic'): ?string
    {
        if ($ciphertext === null || $ciphertext === '') {
            return null;
        }

        try {
            $decoded = base64_decode($ciphertext, true);
            if ($decoded === false) {
                Log::warning('CryptoHelper: Ciphertext bukan format Base64 valid.');
                return null;
            }

            // Jalur Versi 1: Authenticated Deterministic Encryption (DAE)
            if (strlen($decoded) >= 17 && $decoded[0] === self::VERSION_1) {
                $v = substr($decoded, 1, 16);
                $rawCipher = substr($decoded, 17);

                $masterKey = self::resolveDeterministicKey();
                [$kEnc, $kMac] = self::deriveKeys($masterKey);

                $plaintext = openssl_decrypt($rawCipher, 'aes-256-ctr', $kEnc, OPENSSL_RAW_DATA, $v);
                if ($plaintext === false) {
                    Log::warning('CryptoHelper: Operasi dekripsi kriptografi gagal diproses.');
                    return null;
                }

                // Hitung ulang V dan bandingkan dengan timing-safe hash_equals
                $expectedV = substr(hash_hmac('sha256', $columnLabel . "\x00" . $plaintext, $kMac, true), 0, 16);
                if (!hash_equals($v, $expectedV)) {
                    Log::warning('CryptoHelper: Integritas ciphertext ditolak (tamper terdeteksi / MAC mismatch).');
                    return null;
                }

                return $plaintext;
            }

            // Jalur Dual-Read: Fallback ke dekripsi skema lama (v0: AES-256-CBC)
            return self::decryptLegacy($ciphertext);
        } catch (\Throwable $e) {
            Log::warning('CryptoHelper: Gagal memproses dekripsi ciphertext: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Skema Enkripsi Lama (v0: AES-256-CBC dengan static IV).
     * Disediakan khusus untuk benchmarking komparasi dan simulasi data sebelum migrasi.
     */
    public static function encryptFieldLegacy(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $key = self::resolveDeterministicKey();
        $iv = self::deriveDeterministicIvLegacy($key);
        $cipher = config('crypto.cipher', 'aes-256-cbc');

        $encrypted = openssl_encrypt($value, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            return null;
        }

        return base64_encode($encrypted);
    }

    /**
     * Dekripsi Skema Lama (v0: AES-256-CBC).
     */
    public static function decryptLegacy(?string $ciphertext): ?string
    {
        if ($ciphertext === null || $ciphertext === '') {
            return null;
        }

        try {
            $key = self::resolveDeterministicKey();
            $iv = self::deriveDeterministicIvLegacy($key);
            $cipher = config('crypto.cipher', 'aes-256-cbc');

            $decoded = base64_decode($ciphertext, true);
            if ($decoded === false) {
                return null;
            }

            $decrypted = openssl_decrypt($decoded, $cipher, $key, OPENSSL_RAW_DATA, $iv);
            if ($decrypted === false) {
                return null;
            }

            return $decrypted;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Derive IV statis untuk skema warisan v0 (AES-256-CBC).
     */
    protected static function deriveDeterministicIvLegacy(string $key): string
    {
        return substr(hash('sha256', 'deterministic-iv:' . $key, true), 0, 16);
    }

    /**
     * @deprecated Dipindahkan ke App\Benchmark\Security\BlindIndexService.
     * Dipertahankan hanya sebagai alias kompatibilitas backward untuk pengujian benchmark.
     */
    public static function generateBlindIndex(?string $value): ?string
    {
        return BlindIndexService::generate($value);
    }

    /**
     * Hitung SHA-256 Checksum dari berkas fisik.
     */
    public static function calculateFileChecksum(string $filePath): ?string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }

        return hash_file('sha256', $filePath);
    }

    /**
     * Verifikasi integritas berkas terhadap checksum yang diharapkan (Timing-Attack Safe).
     */
    public static function verifyFileChecksum(string $filePath, string $expectedChecksum): bool
    {
        $actual = self::calculateFileChecksum($filePath);
        if ($actual === null) {
            return false;
        }

        return hash_equals(strtolower($expectedChecksum), strtolower($actual));
    }

    /**
     * Buat signed token berbatas waktu (TTL).
     */
    public static function createSignedPayload(array $payload, int $ttlSeconds = 300): string
    {
        $data = [
            'p' => $payload,
            'exp' => now()->timestamp + $ttlSeconds,
            'iat' => now()->timestamp,
        ];

        return Crypt::encryptString(json_encode($data));
    }

    /**
     * Verifikasi dan ekstrak payload dari signed token.
     */
    public static function extractSignedPayload(string $token): ?array
    {
        try {
            $decrypted = Crypt::decryptString($token);
            $data = json_decode($decrypted, true);

            if (!is_array($data) || !isset($data['exp'], $data['p'])) {
                return null;
            }

            if (now()->timestamp > $data['exp']) {
                return null; // Token kedaluwarsa
            }

            return $data['p'];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
