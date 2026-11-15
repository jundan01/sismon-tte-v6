<?php

namespace App\Domain\Analytics\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Model AuditActionHistory — Pencatatan Riwayat Aksi & Mitigasi Undo.
 * Ref: implementation.md §3.1.F
 *
 * @property int         $id
 * @property string      $uuid
 * @property string      $action_type
 * @property string      $entity_type
 * @property string      $entity_id
 * @property int         $user_id
 * @property array       $previous_state
 * @property array       $new_state
 * @property bool        $is_undone
 * @property int|null    $undone_by
 * @property string|null $undone_at
 */
class AuditActionHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_action_history';

    protected $fillable = [
        'uuid',
        'action_type',
        'entity_type',
        'entity_id',
        'user_id',
        'previous_state',
        'new_state',
        'is_undone',
        'undone_by',
        'undone_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'previous_state' => 'array',
            'new_state'      => 'array',
            'is_undone'      => 'boolean',
            'undone_at'      => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function undoUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'undone_by');
    }
}
