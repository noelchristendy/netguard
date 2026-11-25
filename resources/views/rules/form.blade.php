@php
    $isEdit = $rule->exists;
    $timeStart = old('time_start', $rule->time_start ? substr($rule->time_start, 0, 5) : '');
    $timeEnd   = old('time_end', $rule->time_end ? substr($rule->time_end, 0, 5) : '');
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $isEdit ? 'Ubah Rule' : 'Tambah Rule' }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST"
                      action="{{ $isEdit ? route('rules.update', $rule) : route('rules.store') }}"
                      class="space-y-4"
                      x-data="{ protocol: @js(old('protocol', $rule->protocol ?? 'any')) }">
                    @csrf
                    @if ($isEdit)
                        @method('PUT')
                    @endif

                    <div>
                        <x-input-label for="priority" value="Prioritas" />
                        <x-text-input id="priority" name="priority" type="number" min="1" max="65535"
                                      class="block mt-1 w-full" :value="old('priority', $rule->priority)" required autofocus />
                        <p class="mt-1 text-xs text-gray-500">Angka kecil dievaluasi lebih dulu dan harus unik. Contoh: 10, 20, 30, dan 999 untuk rule penutup.</p>
                        <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="src_ip" value="IP sumber" />
                            <x-text-input id="src_ip" name="src_ip" type="text" class="block mt-1 w-full"
                                          :value="old('src_ip', $rule->src_ip)" placeholder="192.168.1.0/24" autocomplete="off" />
                            <x-input-error :messages="$errors->get('src_ip')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="dst_ip" value="IP tujuan" />
                            <x-text-input id="dst_ip" name="dst_ip" type="text" class="block mt-1 w-full"
                                          :value="old('dst_ip', $rule->dst_ip)" placeholder="10.0.0.5" autocomplete="off" />
                            <x-input-error :messages="$errors->get('dst_ip')" class="mt-2" />
                        </div>
                    </div>
                    <p class="-mt-2 text-xs text-gray-500">IPv4 tunggal atau CIDR (prefix 0 sampai 32). Kosongkan untuk semua alamat.</p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="protocol" value="Protokol" />
                            <select id="protocol" name="protocol" x-model="protocol"
                                    class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                @foreach (['any' => 'Semua protokol (any)', 'TCP' => 'TCP', 'UDP' => 'UDP', 'ICMP' => 'ICMP'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('protocol', $rule->protocol ?? 'any') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('protocol')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="dst_port" value="Port tujuan" />
                            <x-text-input id="dst_port" name="dst_port" type="number" min="1" max="65535"
                                          class="block mt-1 w-full" :value="old('dst_port', $rule->dst_port)"
                                          placeholder="443" x-bind:disabled="protocol === 'ICMP'" />
                            <p class="mt-1 text-xs text-gray-500">Kosongkan untuk semua port. ICMP tidak memiliki port.</p>
                            <x-input-error :messages="$errors->get('dst_port')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="time_start" value="Jam mulai" />
                            <x-text-input id="time_start" name="time_start" type="time" class="block mt-1 w-full" :value="$timeStart" />
                            <x-input-error :messages="$errors->get('time_start')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="time_end" value="Jam selesai" />
                            <x-text-input id="time_end" name="time_end" type="time" class="block mt-1 w-full" :value="$timeEnd" />
                            <x-input-error :messages="$errors->get('time_end')" class="mt-2" />
                        </div>
                    </div>
                    <p class="-mt-2 text-xs text-gray-500">
                        Isi keduanya atau kosongkan keduanya. Jam mulai termasuk, jam selesai tidak termasuk.
                        Untuk jendela yang melewati tengah malam (di luar jam kerja), isi jam mulai 17:00 dan jam selesai 08:00.
                    </p>

                    <div>
                        <x-input-label for="action" value="Aksi" />
                        <select id="action" name="action" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach (['allow' => 'allow (izinkan, hentikan evaluasi)', 'deny' => 'deny (tolak, hentikan evaluasi)', 'log' => 'log (catat saja, evaluasi lanjut)'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('action', $rule->action) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Hati-hati: rule allow tanpa kondisi apa pun membuka seluruh trafik.</p>
                        <x-input-error :messages="$errors->get('action')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Keterangan (opsional)" />
                        <x-text-input id="description" name="description" type="text" maxlength="255"
                                      class="block mt-1 w-full" :value="old('description', $rule->description)" autocomplete="off" />
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-2">
                        {{-- Nilai 0 selalu terkirim; checkbox yang dicentang menimpanya dengan 1 --}}
                        <input type="hidden" name="enabled" value="0">
                        <input id="enabled" name="enabled" type="checkbox" value="1"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm"
                               @checked((bool) old('enabled', $rule->enabled))>
                        <label for="enabled" class="text-sm text-gray-700">Rule aktif</label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <a href="{{ route('rules.index') }}" class="text-sm text-gray-600 underline">Batal</a>
                        <x-primary-button>{{ $isEdit ? 'Simpan Perubahan' : 'Simpan' }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
