<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const MAX_FAILED_ATTEMPTS = 5;   // gagal berturut-turut sebelum akun dikunci
    public const LOCK_MINUTES        = 15;  // lama penguncian otomatis

    protected $fillable = [
        'username', 'name', 'password', 'role_id',
        'failed_attempts', 'is_locked', 'locked_until',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'     => 'hashed',
            'is_locked'    => 'boolean',
            'locked_until' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Akun terkunci bila is_locked = true, kecuali masa kunci sementara sudah lewat.
     * locked_until = null berarti dikunci manual oleh Administrator (tidak terbuka otomatis).
     */
    public function isLocked(): bool
    {
        if (! $this->is_locked) {
            return false;
        }

        if ($this->locked_until !== null && $this->locked_until->isPast()) {
            $this->forceFill([
                'is_locked'       => false,
                'locked_until'    => null,
                'failed_attempts' => 0,
            ])->save();

            return false;
        }

        return true;
    }

    /**
     * Catat satu kegagalan login. Mengembalikan true bila kegagalan ini
     * menyebabkan akun terkunci.
     */
    public function registerFailedLogin(): bool
    {
        $this->increment('failed_attempts');   // atomik di database
        $this->refresh();

        if ($this->failed_attempts >= self::MAX_FAILED_ATTEMPTS) {
            $this->forceFill([
                'is_locked'    => true,
                'locked_until' => now()->addMinutes(self::LOCK_MINUTES),
            ])->save();

            return true;
        }

        return false;
    }

    public function resetFailedLogin(): void
    {
        if ($this->failed_attempts !== 0 || $this->is_locked) {
            $this->forceFill([
                'failed_attempts' => 0,
                'is_locked'       => false,
                'locked_until'    => null,
            ])->save();
        }
    }
}
