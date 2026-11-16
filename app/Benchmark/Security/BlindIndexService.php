<?php

namespace App\Benchmark\Security;

/**
 * Modul Blind Index (HMAC-SHA256) Terisolasi Khusus Benchmark.
 *
 * PERHATIAN: Modul ini TIDAK DIGUNAKAN pada jalur produksi aplikasi Sismon-TTE.
 * Modul ini dipindahkan ke namespace App\Benchmark\Security untuk digunakan
 * secara eksklusif sebagai pembanding tolok ukur (Skenario D) pada pengujian Fase 3.
 */
class BlindIndexService
{
    /**
     * Hasilkan Blind Index HMAC-SHA256 deterministik untuk data sensitif.
     */
    public static function generate(?string $value, ?string $key = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($key === null) {
            $key = config('crypto.deterministic_key') ?: config('app.key');
            if (str_starts_with($key, 'base64:')) {
                $key = base64_decode(substr($key, 7));
            }
        }

        return hash_hmac('sha256', trim((string)$value), $key);
    }
}
