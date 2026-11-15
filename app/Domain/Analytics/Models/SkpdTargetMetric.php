<?php

namespace App\Domain\Analytics\Models;

use App\Domain\Master\Models\Skpd;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model SkpdTargetMetric — Target Capaian Adopsi SKPD Bulanan.
 * Ref: implementation.md §3.1.D
 *
 * @property int   $id
 * @property int   $skpd_id
 * @property int   $tahun
 * @property int   $bulan
 * @property float $target_persentase
 * @property int   $target_jumlah_pegawai
 */
class SkpdTargetMetric extends Model
{
    protected $table = 'skpd_target_metrics';

    protected $fillable = [
        'skpd_id',
        'tahun',
        'bulan',
        'target_persentase',
        'target_jumlah_pegawai',
    ];

    protected function casts(): array
    {
        return [
            'tahun'                 => 'integer',
            'bulan'                 => 'integer',
            'target_persentase'     => 'float',
            'target_jumlah_pegawai' => 'integer',
        ];
    }

    public function skpd(): BelongsTo
    {
        return $this->belongsTo(Skpd::class, 'skpd_id');
    }
}
