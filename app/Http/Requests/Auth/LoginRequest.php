<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\AuditService;
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
            AuditService::log($user->id, AuditService::LOGIN_FAILED, [
                'alasan' => 'akun terkunci',
                'ip'     => $this->ip(),
            ]);

            throw $this->failure();
        }

        if (! Auth::attempt($this->only('username', 'password'))) {
            RateLimiter::hit($this->throttleKey());

            if ($user) {
                $justLocked = $user->registerFailedLogin();

                AuditService::log($user->id, AuditService::LOGIN_FAILED, [
                    'alasan' => 'kata sandi salah',
                    'ip'     => $this->ip(),
                ]);

                if ($justLocked) {
                    AuditService::log($user->id, AuditService::ACCOUNT_LOCKED, [
                        'gagal_berturut'  => $user->failed_attempts,
                        'terkunci_sampai' => $user->locked_until?->toDateTimeString(),
                        'ip'              => $this->ip(),
                    ]);
                }
            } else {
                // Pelaku tidak dikenal. Username dipotong karena orang kadang salah mengetik
                // kata sandi di kolom username.
                AuditService::log(null, AuditService::LOGIN_FAILED, [
                    'alasan'   => 'username tidak dikenal',
                    'username' => Str::limit((string) $this->input('username'), 50),
                    'ip'       => $this->ip(),
                ]);
            }

            throw $this->failure();
        }

        RateLimiter::clear($this->throttleKey());
        Auth::user()->resetFailedLogin();
        AuditService::log(Auth::id(), AuditService::LOGIN_SUCCESS, ['ip' => $this->ip()]);
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
