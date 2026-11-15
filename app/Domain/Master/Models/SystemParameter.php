<?php

namespace App\Domain\Master\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model SystemParameter — Konfigurasi Kebijakan Dinamis (Anti Hard-Coding).
 * Ref: implementation.md §3.1.C & §4.2
 *
 * @property int    $id
 * @property string $param_key
 * @property string $param_value
 * @property string $param_group
 * @property string $data_type
 * @property string $label
 * @property string $description
 */
class SystemParameter extends Model
{
    protected $table = 'system_parameters';

    protected $fillable = [
        'param_key',
        'param_value',
        'param_group',
        'data_type',
        'label',
        'description',
    ];

    /**
     * Konversi nilai parameter ke tipe data aslinya.
     */
    public function getTypedValueAttribute(): mixed
    {
        return match (strtoupper($this->data_type)) {
            'INTEGER', 'INT' => (int) $this->param_value,
            'BOOLEAN', 'BOOL' => filter_var($this->param_value, FILTER_VALIDATE_BOOLEAN),
            'JSON' => json_decode($this->param_value, true),
            'FLOAT', 'DECIMAL' => (float) $this->param_value,
            default => $this->param_value,
        };
    }
}
