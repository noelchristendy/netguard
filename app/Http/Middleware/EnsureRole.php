<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pemeriksaan hak akses di sisi server (RBAC).
 *
 * Pemakaian di rute:  ->middleware(['auth', 'role:administrator,operator'])
 * atau lewat Role::allow(...) supaya tidak menulis string peran berulang kali.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Belum login: arahkan ke halaman login
        if (! $user) {
            return redirect()->route('login');
        }

        // Akun dikunci (mis. oleh Administrator) saat sesinya masih aktif:
        // keluarkan pada request berikutnya, tanpa menunggu sesi habis.
        if ($user->isLocked()) {
            AuditService::log($user->id, AuditService::LOGOUT, ['alasan' => 'akun dikunci saat sesi aktif']);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['username' => 'Sesi Anda telah berakhir. Silakan login kembali.']);
        }

        $user->loadMissing('role');

        // Gagal tertutup: tanpa daftar peran, atau peran tidak cocok, tolak.
        if ($roles === [] || ! in_array($user->role?->name, $roles, true)) {
            AuditService::log($user->id, AuditService::ACCESS_DENIED, [
                'peran'  => $user->role?->name,
                'metode' => $request->method(),
                'path'   => '/'.ltrim($request->path(), '/'),
            ]);

            abort(403);
        }

        return $next($request);
    }
}
