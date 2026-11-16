<?php

namespace App\Benchmark\Security;

/**
 * Helper Enkripsi Probabilistik AES-256-GCM Khusus Benchmark Fase 3.
 *
 * Menggunakan IV acak 12-byte per operasi (probabilistik), menghasilkan
 * ciphertext yang berbeda setiap kali enkripsi dijalankan untuk plaintext yang sama.
 */
class AesGcmHelper
{
    protected static ?string $cachedKey = null;

    public static function getKey(): string
    {
        if (self::$cachedKey === null) {
            $masterKey = config('app.key');
            if (str_starts_with($masterKey, 'base64:')) {
                $masterKey = base64_decode(substr($masterKey, 7));
            }
            self::$cachedKey = hash_hkdf('sha256', $masterKey, 32, 'sismon-tte:gcm:bench:v1');
        }
        return self::$cachedKey;
    }

    public static function encrypt(string $plaintext, ?string $key = null): string
    {
        $k = $key ?? self::getKey();
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plaintext, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag);
        // Format: IV (12B) || Tag (16B) || Ciphertext (16B) = 44 raw bytes -> 60 base64 chars
        return base64_encode($iv . $tag . $ct);
    }

    public static function decrypt(string $encoded, ?string $key = null): ?string
    {
        $k = $key ?? self::getKey();
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct = substr($raw, 28);

        $plain = openssl_decrypt($ct, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag);
        return ($plain !== false) ? $plain : null;
    }
}
