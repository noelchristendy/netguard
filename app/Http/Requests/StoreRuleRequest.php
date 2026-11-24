<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi form rule, dipakai untuk tambah (store) dan ubah (update).
 *
 * Penjaga bagi RuleMatcher dan FilterService: apa pun yang lolos di sini tidak boleh membuat
 * keduanya melempar exception (jendela waktu setengah terisi, start == end, format jam salah,
 * aksi tidak dikenal).
 *
 * Saat ubah, rute harus memakai parameter bernama "rule" (Route::resource('rules', ...) sudah begitu)
 * agar pengecekan prioritas unik mengecualikan rule itu sendiri.
 */
class StoreRuleRequest extends FormRequest
{
    private const TIME_REGEX = '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';

    public function authorize(): bool
    {
        // Hak akses sudah diperiksa middleware role:administrator,operator pada rute.
        return true;
    }

    public function rules(): array
    {
        return [
            'priority'    => ['required', 'integer', 'between:1,65535', Rule::unique('rules', 'priority')->ignore($this->ruleId())],
            'src_ip'      => ['nullable', $this->ipOrCidr()],
            'dst_ip'      => ['nullable', $this->ipOrCidr()],
            'protocol'    => ['required', Rule::in(['TCP', 'UDP', 'ICMP', 'any'])],
            'dst_port'    => ['nullable', 'integer', 'between:1,65535', 'prohibited_if:protocol,ICMP'],
            'time_start'  => ['nullable', 'regex:'.self::TIME_REGEX, 'required_with:time_end'],
            'time_end'    => ['nullable', 'regex:'.self::TIME_REGEX, 'required_with:time_start'],
            'action'      => ['required', Rule::in(['allow', 'deny', 'log'])],
            'enabled'     => ['boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Merapikan input sebelum divalidasi, supaya validasi dan data tersimpan konsisten. */
    protected function prepareForValidation(): void
    {
        $data = [];

        // Teks kosong atau spasi saja dianggap tidak diisi (null)
        foreach (['src_ip', 'dst_ip', 'dst_port', 'time_start', 'time_end', 'description'] as $key) {
            $value = $this->input($key);
            if (is_string($value)) {
                $value = trim($value);
            }
            $data[$key] = $value === '' ? null : $value;
        }

        $protocol = $this->input('protocol');
        if (is_string($protocol)) {
            $protocol = strtoupper(trim($protocol));
            $protocol = $protocol === 'ANY' ? 'any' : $protocol;
        }

        $action = $this->input('action');
        if (is_string($action)) {
            $action = strtolower(trim($action));
        }

        $data['protocol'] = $protocol;
        $data['action']   = $action;
        // Checkbox yang tidak dicentang tidak dikirim browser. Form harus selalu menyertakan nilai enabled.
        $data['enabled']  = $this->boolean('enabled');

        $this->merge($data);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->hasAny(['time_start', 'time_end'])) {
                return;
            }

            $start = $this->input('time_start');
            $end   = $this->input('time_end');

            // '08:00' dan '08:00:00' dianggap sama
            if ($start !== null && $end !== null && self::seconds($start) === self::seconds($end)) {
                $v->errors()->add('time_end', 'Jam selesai tidak boleh sama dengan jam mulai.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'priority'    => 'prioritas',
            'src_ip'      => 'IP sumber',
            'dst_ip'      => 'IP tujuan',
            'protocol'    => 'protokol',
            'dst_port'    => 'port tujuan',
            'time_start'  => 'jam mulai',
            'time_end'    => 'jam selesai',
            'action'      => 'aksi',
            'enabled'     => 'status aktif',
            'description' => 'keterangan',
        ];
    }

    public function messages(): array
    {
        return [
            'required'                 => ':attribute wajib diisi.',
            'priority.integer'         => 'Prioritas harus berupa angka bulat.',
            'priority.between'         => 'Prioritas harus antara :min dan :max.',
            'priority.unique'          => 'Prioritas :input sudah dipakai rule lain.',
            'protocol.in'              => 'Protokol harus TCP, UDP, ICMP, atau any.',
            'dst_port.integer'         => 'Port harus berupa angka.',
            'dst_port.between'         => 'Port harus antara :min dan :max.',
            'dst_port.prohibited_if'   => 'Protokol ICMP tidak memiliki port. Kosongkan kolom port.',
            'time_start.regex'         => 'Format jam mulai harus HH:MM (contoh 08:00).',
            'time_end.regex'           => 'Format jam selesai harus HH:MM (contoh 17:00).',
            'time_start.required_with' => 'Jam mulai wajib diisi bila jam selesai diisi.',
            'time_end.required_with'   => 'Jam selesai wajib diisi bila jam mulai diisi.',
            'action.in'                => 'Aksi harus allow, deny, atau log.',
            'description.max'          => 'Keterangan maksimal :max karakter.',
        ];
    }

    // ------------------------------------------------------------------ helper

    /** id rule yang sedang diubah (dari parameter rute), atau null saat membuat rule baru. */
    private function ruleId(): int|string|null
    {
        $rule = $this->route('rule');

        return $rule instanceof Model ? $rule->getKey() : $rule;
    }

    /** IPv4 tunggal (10.0.0.5) atau CIDR dengan prefix 0 sampai 32 (192.168.1.0/24). */
    private function ipOrCidr(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null) {
                return;
            }

            if (! is_string($value) || ! self::isIpOrCidr(trim($value))) {
                $fail('Isi :attribute dengan IPv4 (contoh 10.0.0.5) atau CIDR (contoh 192.168.1.0/24).');
            }
        };
    }

    private static function isIpOrCidr(string $value): bool
    {
        $ip = $value;

        if (str_contains($value, '/')) {
            [$ip, $prefix] = explode('/', $value, 2);

            if (! ctype_digit($prefix) || strlen($prefix) > 2 || (int) $prefix > 32) {
                return false;
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /** Detik sejak 00:00:00 untuk HH:MM atau HH:MM:SS yang sudah lolos regex. */
    private static function seconds(string $time): int
    {
        $parts = array_map('intval', explode(':', $time));

        return $parts[0] * 3600 + $parts[1] * 60 + ($parts[2] ?? 0);
    }
}
