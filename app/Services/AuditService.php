<?php

namespace App\Services;

use App\Models\AuditLog;
use Throwable;

/**
 * Pencatatan jejak audit. Dipakai oleh semua modul:
 *
 *   AuditService::log(Auth::id(), AuditService::RULE_UPDATED, ['rule_id' => 7, 'lama' => [...], 'baru' => [...]]);
 *
 * Jangan pernah memasukkan kata sandi (asli maupun hash) ke $detail.
 */
class AuditService
{
    // Autentikasi dan akun
    public const LOGIN_SUCCESS     = 'LOGIN_SUCCESS';
    public const LOGIN_FAILED      = 'LOGIN_FAILED';
    public const LOGOUT            = 'LOGOUT';
    public const ACCOUNT_LOCKED    = 'ACCOUNT_LOCKED';     // terkunci otomatis (gagal login berulang)
    public const ACCESS_DENIED     = 'ACCESS_DENIED';      // ditolak 403

    // Manajemen pengguna
    public const USER_CREATED      = 'USER_CREATED';
    public const USER_ROLE_CHANGED = 'USER_ROLE_CHANGED';
    public const USER_LOCKED       = 'USER_LOCKED';        // dikunci manual oleh Administrator
    public const USER_UNLOCKED     = 'USER_UNLOCKED';

    // Rule
    public const RULE_CREATED      = 'RULE_CREATED';
    public const RULE_UPDATED      = 'RULE_UPDATED';
    public const RULE_DELETED      = 'RULE_DELETED';
    public const RULE_TOGGLED      = 'RULE_TOGGLED';

    // Laporan
    public const REPORT_EXPORTED   = 'REPORT_EXPORTED';

    /** Daftar semua aksi, berguna untuk dropdown filter di halaman Log Audit. */
    public const ALL = [
        self::LOGIN_SUCCESS, self::LOGIN_FAILED, self::LOGOUT, self::ACCOUNT_LOCKED, self::ACCESS_DENIED,
        self::USER_CREATED, self::USER_ROLE_CHANGED, self::USER_LOCKED, self::USER_UNLOCKED,
        self::RULE_CREATED, self::RULE_UPDATED, self::RULE_DELETED, self::RULE_TOGGLED,
        self::REPORT_EXPORTED,
    ];

    /**
     * @param int|null          $userId  pelaku; null bila tidak diketahui (mis. username tidak ada)
     * @param array|string|null $detail  array akan disimpan sebagai JSON
     */
    public static function log(?int $userId, string $action, array|string|null $detail = null): void
    {
        try {
            AuditLog::create([
                'timestamp' => now(),   // diisi dari aplikasi agar mengikuti APP_TIMEZONE
                'user_id'   => $userId,
                'action'    => $action,
                'detail'    => is_array($detail)
                    ? json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : $detail,
            ]);
        } catch (Throwable $e) {
            // Kegagalan mencatat audit tidak boleh membuat login atau fitur lain ikut crash,
            // tetapi harus terlihat di storage/logs/laravel.log.
            report($e);
        }
    }
}
