<?php

namespace App\Domain\Pendaftaran\Models;

use App\Domain\Master\Models\Skpd;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Model PengajuanKunjunganLayanan — Permohonan Kunjungan Layanan Asistensi On-Site SKPD.
 * Ref: implementation.md §3.1.E
 *
 * @property int         $id
 * @property string      $uuid
 * @property string      $nomor_tiket
 * @property int         $skpd_id
 * @property string      $nama_pemohon
 * @property string      $jabatan_pemohon
 * @property string      $kontak_pemohon
 * @property string      $tanggal_usulan_kunjungan
 * @property int         $perkiraan_peserta
 * @property string      $catatan_kebutuhan
 * @property string|null $file_surat_permohonan
 * @property string      $status
 * @property string|null $catatan_petugas
 * @property string|null $tanggal_kunjungan_final
 */
class PengajuanKunjunganLayanan extends Model
{
    protected $table = 'pengajuan_kunjungan_layanan';

    protected $fillable = [
        'uuid',
        'nomor_tiket',
        'skpd_id',
        'nama_pemohon',
        'jabatan_pemohon',
        'kontak_pemohon',
        'tanggal_usulan_kunjungan',
        'perkiraan_peserta',
        'catatan_kebutuhan',
        'file_surat_permohonan',
        'status',
        'catatan_petugas',
        'tanggal_kunjungan_final',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->nomor_tiket)) {
                $model->nomor_tiket = 'VISIT-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'tanggal_usulan_kunjungan' => 'date',
            'tanggal_kunjungan_final'  => 'date',
            'perkiraan_peserta'        => 'integer',
        ];
    }

    public function skpd(): BelongsTo
    {
        return $this->belongsTo(Skpd::class, 'skpd_id');
    }
}
