<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['timestamp', 'user_id', 'action', 'detail'];

    protected function casts(): array
    {
        return ['timestamp' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Jejak audit bersifat tambah-saja (append-only): tidak boleh diubah atau dihapus
     * lewat aplikasi, sesuai prinsip pemisahan tugas di bagian 5.1 laporan.
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Log audit tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Log audit tidak boleh dihapus.'));
    }
}
