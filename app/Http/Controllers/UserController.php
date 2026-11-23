<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Manajemen pengguna. Rute dilindungi middleware role:administrator (Role::USER_MANAGERS).
 * Tidak ada fitur hapus: akun cukup dikunci agar jejak audit pelakunya tetap utuh.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::with('role')->orderBy('username')->get(),
            'roles' => Role::orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('users.create', [
            'roles' => Role::orderBy('id')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'username' => $data['username'],
            'name'     => $data['name'],
            'password' => Hash::make($data['password']),
            'role_id'  => $data['role_id'],
        ]);

        // Jangan pernah mencatat kata sandi (asli maupun hash) di audit
        AuditService::log(Auth::id(), AuditService::USER_CREATED, [
            'user_id'  => $user->id,
            'username' => $user->username,
            'peran'    => Role::find($data['role_id'])?->name,
        ]);

        return redirect()->route('users.index')
            ->with('success', "Pengguna {$user->username} berhasil dibuat.");
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        // Mencegah Administrator menurunkan perannya sendiri (sistem tidak boleh kehilangan admin)
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Anda tidak dapat mengubah peran akun Anda sendiri.');
        }

        if ((int) $data['role_id'] === $user->role_id) {
            return back();
        }

        $lama = $user->role->name;
        $user->update(['role_id' => $data['role_id']]);
        $baru = Role::find($data['role_id'])->name;

        AuditService::log(Auth::id(), AuditService::USER_ROLE_CHANGED, [
            'user_id'  => $user->id,
            'username' => $user->username,
            'lama'     => $lama,
            'baru'     => $baru,
        ]);

        return back()->with('success', "Peran {$user->username} diubah dari {$lama} menjadi {$baru}.");
    }

    public function lock(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Anda tidak dapat mengunci akun Anda sendiri.');
        }

        // locked_until = null berarti kunci manual: tidak terbuka otomatis.
        // Pengguna yang sedang login dikeluarkan oleh middleware EnsureRole pada request berikutnya.
        $user->forceFill(['is_locked' => true, 'locked_until' => null])->save();

        AuditService::log(Auth::id(), AuditService::USER_LOCKED, [
            'user_id'  => $user->id,
            'username' => $user->username,
        ]);

        return back()->with('success', "Akun {$user->username} dikunci.");
    }

    public function unlock(User $user): RedirectResponse
    {
        $user->forceFill([
            'is_locked'       => false,
            'locked_until'    => null,
            'failed_attempts' => 0,
        ])->save();

        AuditService::log(Auth::id(), AuditService::USER_UNLOCKED, [
            'user_id'  => $user->id,
            'username' => $user->username,
        ]);

        return back()->with('success', "Akun {$user->username} dibuka kuncinya.");
    }
}
