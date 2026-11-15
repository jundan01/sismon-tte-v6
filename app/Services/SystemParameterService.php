<?php

namespace App\Services;

use App\Domain\Master\Models\SystemParameter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service SystemParameterService — Engine Pengelolaan Parameter Kebijakan Dinamis.
 * Terintegrasi dengan Redis Caching untuk performa sub-milidetik (anti hard-coding).
 * Ref: implementation.md §4.2
 */
class SystemParameterService
{
    protected const CACHE_PREFIX = 'system_param:';
    protected const CACHE_TTL_SECONDS = 86400; // 24 Jam

    /**
     * Ambil nilai parameter berdasarkan key dengan caching.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX . $key;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($key, $default) {
            try {
                $param = SystemParameter::where('param_key', $key)->first();
                if (!$param) {
                    return $default;
                }

                return $param->typed_value;
            } catch (\Throwable $e) {
                Log::warning("SystemParameterService: Gagal membaca parameter {$key}. Error: " . $e->getMessage());
                return $default;
            }
        });
    }

    /**
     * Ambil nilai parameter secara strict boolean (memvalidasi 'false', '0', false, dll).
     */
    public static function getBoolean(string $key, bool $default = false): bool
    {
        $val = self::get($key, $default);
        if (is_bool($val)) {
            return $val;
        }
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set atau perbarui nilai parameter dan invalidasi cache.
     */
    public static function set(string $key, mixed $value): bool
    {
        try {
            $param = SystemParameter::where('param_key', $key)->first();

            if ($param) {
                $formattedValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                $param->update(['param_value' => $formattedValue]);
            } else {
                return false;
            }

            Cache::forget(self::CACHE_PREFIX . $key);
            Cache::forget(self::CACHE_PREFIX . 'all_parameters');

            return true;
        } catch (\Throwable $e) {
            Log::error("SystemParameterService: Gagal memperbarui parameter {$key}. Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ambil seluruh parameter sistem terkelompok.
     */
    public static function getAllGrouped(): Collection
    {
        return Cache::remember(self::CACHE_PREFIX . 'all_parameters', 3600, function () {
            return SystemParameter::orderBy('param_group')
                ->orderBy('id')
                ->get()
                ->groupBy('param_group');
        });
    }

    /**
     * Bersihkan seluruh cache parameter sistem.
     */
    public static function clearCache(): void
    {
        try {
            $params = SystemParameter::all();
            foreach ($params as $param) {
                Cache::forget(self::CACHE_PREFIX . $param->param_key);
            }
            Cache::forget(self::CACHE_PREFIX . 'all_parameters');
        } catch (\Throwable $e) {
            Log::warning('SystemParameterService: Gagal membersihkan cache. Error: ' . $e->getMessage());
        }
    }
}
