<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Urutan pemeriksaan:
     * 1. pembatas laju bawaan (cache), 2. akun terkunci di database,
     * 3. kredensial. Semua kegagalan memakai pesan yang SAMA supaya
     * penyerang tidak bisa mengetahui username mana yang valid atau terkunci.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = User::where('username', $this->input('username'))->first();

        // Akun terkunci: tolak walaupun kata sandi benar
        if ($user && $user->isLocked()) {
            RateLimiter::hit($this->throttleKey());
            // TODO (A7): AuditService::log($user->id, 'LOGIN_FAILED', 'akun terkunci');
            throw $this->failure();
        }

        if (! Auth::attempt($this->only('username', 'password'))) {
            RateLimiter::hit($this->throttleKey());

            if ($user) {
                $justLocked = $user->registerFailedLogin();
                // TODO (A7): AuditService::log($user->id, 'LOGIN_FAILED', 'kata sandi salah');
                // TODO (A7): if ($justLocked) AuditService::log($user->id, 'ACCOUNT_LOCKED', 'gagal login berulang');
            } else {
                // TODO (A7): AuditService::log(null, 'LOGIN_FAILED', 'username tidak dikenal: '.$this->input('username'));
            }

            throw $this->failure();
        }

        RateLimiter::clear($this->throttleKey());
        Auth::user()->resetFailedLogin();
        // TODO (A7): AuditService::log(Auth::id(), 'LOGIN_SUCCESS');
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('username')).'|'.$this->ip());
    }

    private function failure(): ValidationException
    {
        return ValidationException::withMessages([
            'username' => 'Username atau kata sandi salah, atau akun terkunci sementara.',
        ]);
    }
}
