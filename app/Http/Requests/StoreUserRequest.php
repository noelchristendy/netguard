<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Hak akses sudah diperiksa middleware role:administrator pada rute.
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'name'     => ['required', 'string', 'max:100'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
            'role_id'  => ['required', 'integer', 'exists:roles,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => 'username',
            'name'     => 'nama',
            'password' => 'kata sandi',
            'role_id'  => 'peran',
        ];
    }

    public function messages(): array
    {
        return [
            'required'        => ':attribute wajib diisi.',
            'username.unique' => 'Username sudah dipakai.',
            'username.regex'  => 'Username hanya boleh huruf, angka, titik, garis bawah, dan strip.',
            'username.min'    => 'Username minimal :min karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min'    => 'Kata sandi minimal :min karakter.',
            'password.letters'   => 'Kata sandi harus mengandung huruf.',
            'password.mixed'     => 'Kata sandi harus mengandung huruf besar dan huruf kecil.',
            'password.numbers'   => 'Kata sandi harus mengandung angka.',
            'role_id.exists'  => 'Peran tidak valid.',
        ];
    }
}
