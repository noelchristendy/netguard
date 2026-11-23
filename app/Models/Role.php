<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const ADMINISTRATOR = 'administrator';
    public const OPERATOR      = 'operator';
    public const AUDITOR       = 'auditor';

    // ---- Matriks hak akses (bagian 5.1 laporan), satu-satunya tempat yang perlu diubah ----
    public const ALL            = [self::ADMINISTRATOR, self::OPERATOR, self::AUDITOR];
    public const USER_MANAGERS  = [self::ADMINISTRATOR];                               // kelola pengguna
    public const RULE_MANAGERS  = [self::ADMINISTRATOR, self::OPERATOR];               // rule + penyaringan
    public const AUDIT_VIEWERS  = [self::ADMINISTRATOR, self::AUDITOR];                // log audit
    // Log keputusan dan ekspor laporan: semua peran (Role::ALL)

    public $timestamps = false;

    protected $fillable = ['name', 'description'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Membuat string middleware, mis. Role::allow(...Role::RULE_MANAGERS) => 'role:administrator,operator'
     */
    public static function allow(string ...$roles): string
    {
        return 'role:'.implode(',', $roles);
    }
}
