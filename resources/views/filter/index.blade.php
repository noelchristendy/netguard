<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Penyaringan Trafik
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($activeRules === 0)
                <div class="border rounded-md px-4 py-3 text-sm bg-yellow-50 text-yellow-800 border-yellow-300">
                    Tidak ada rule aktif, sehingga semua trafik akan ditolak (default deny).
                    @if (Route::has('rules.index'))
                        <a href="{{ route('rules.index') }}" class="underline">Kelola rule</a>
                    @endif
                </div>
            @else
                <div class="border rounded-md px-4 py-3 text-sm bg-blue-50 text-blue-800 border-blue-300">
                    {{ $activeRules }} rule aktif akan dipakai. Trafik dievaluasi dari prioritas terkecil.
                    @if (Route::has('rules.index'))
                        <a href="{{ route('rules.index') }}" class="underline">Lihat rule</a>
                    @endif
                </div>
            @endif

            <div class="grid gap-6 md:grid-cols-2">

                {{-- Unggah CSV --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-3">Unggah CSV</h3>
                    <form method="POST" action="{{ route('filter.upload') }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <input type="file" name="csv" required accept=".csv,.txt,text/csv,text/plain"
                               class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md">
                        <x-input-error :messages="$errors->get('csv')" class="mt-1" />
                        <p class="text-xs text-gray-500">
                            Baris pertama berisi nama kolom: <code>timestamp,src_ip,dst_ip,protocol,dst_port</code>.
                            Contoh baris: <code>2026-11-20 09:15:02,192.168.1.10,10.0.0.5,TCP,443</code>.
                            Port dikosongkan untuk ICMP. Berkas .csv atau .txt (UTF-8), maksimal 5 MB dan 50.000 baris.
                            Baris yang tidak valid dilewati dan dilaporkan.
                        </p>
                        <x-primary-button>Proses CSV</x-primary-button>
                    </form>
                </div>

                {{-- Input satu trafik --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-3">Input Satu Trafik</h3>
                    <form method="POST" action="{{ route('filter.single') }}" class="space-y-3"
                          x-data="{ protocol: @js(old('protocol', 'TCP')) }">
                        @csrf

                        <div>
                            <x-input-label for="timestamp" value="Waktu" />
                            <x-text-input id="timestamp" name="timestamp" type="datetime-local" step="1"
                                          class="block mt-1 w-full" :value="old('timestamp', now()->format('Y-m-d\TH:i:s'))" required />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <x-input-label for="src_ip" value="IP sumber" />
                                <x-text-input id="src_ip" name="src_ip" type="text" class="block mt-1 w-full"
                                              :value="old('src_ip')" placeholder="192.168.1.10" required autocomplete="off" />
                            </div>
                            <div>
                                <x-input-label for="dst_ip" value="IP tujuan" />
                                <x-text-input id="dst_ip" name="dst_ip" type="text" class="block mt-1 w-full"
                                              :value="old('dst_ip')" placeholder="10.0.0.5" required autocomplete="off" />
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <x-input-label for="protocol" value="Protokol" />
                                <select id="protocol" name="protocol" x-model="protocol"
                                        class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                    @foreach (['TCP', 'UDP', 'ICMP'] as $proto)
                                        <option value="{{ $proto }}" @selected(old('protocol', 'TCP') === $proto)>{{ $proto }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="dst_port" value="Port tujuan" />
                                <x-text-input id="dst_port" name="dst_port" type="number" min="1" max="65535"
                                              class="block mt-1 w-full" :value="old('dst_port')" placeholder="443"
                                              x-bind:disabled="protocol === 'ICMP'" />
                            </div>
                        </div>

                        <x-input-error :messages="$errors->get('traffic')" class="mt-1" />
                        <x-primary-button>Evaluasi</x-primary-button>
                    </form>
                </div>
            </div>

            @if ($result)
                <div class="space-y-4">
                    <h3 class="font-semibold text-lg text-gray-800">
                        Hasil Penyaringan
                        @if ($result['filename'])
                            <span class="text-sm font-normal text-gray-500">({{ $result['filename'] }})</span>
                        @endif
                    </h3>

                    <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                        @foreach ([
                            ['Total', $result['total'], 'text-gray-900'],
                            ['Allow', $result['allow'], 'text-green-700'],
                            ['Deny', $result['deny'], 'text-red-700'],
                            ['Default deny', $result['default_deny'], 'text-gray-700'],
                            ['Dicatat rule log', $result['logged'], 'text-yellow-700'],
                        ] as [$label, $value, $color])
                            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                                <div class="text-xs text-gray-500">{{ $label }}</div>
                                <div class="text-2xl font-bold {{ $color }}">{{ number_format($value, 0, ',', '.') }}</div>
                            </div>
                        @endforeach
                    </div>

                    @if ($result['truncated'])
                        <div class="border rounded-md px-4 py-3 text-sm bg-yellow-50 text-yellow-800 border-yellow-300">
                            Berkas melebihi batas baris. Hanya bagian pertama yang diproses.
                        </div>
                    @endif

                    @if ($result['rows'] !== [])
                        <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-600">Waktu</th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-600">Sumber</th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-600">Tujuan</th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-600">Protokol</th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-600">Port</th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-600">Keputusan</th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-600">Rule penentu</th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-600">Rule log</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($result['rows'] as $row)
                                        <tr>
                                            <td class="px-4 py-2 whitespace-nowrap">{{ $row['timestamp'] }}</td>
                                            <td class="px-4 py-2">{{ $row['src_ip'] }}</td>
                                            <td class="px-4 py-2">{{ $row['dst_ip'] }}</td>
                                            <td class="px-4 py-2">{{ $row['protocol'] }}</td>
                                            <td class="px-4 py-2">{{ $row['dst_port'] ?? '-' }}</td>
                                            <td class="px-4 py-2">
                                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold uppercase {{ $row['decision'] === 'allow' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ $row['decision'] }}</span>
                                            </td>
                                            <td class="px-4 py-2">
                                                @if ($row['is_default'])
                                                    <span class="italic text-gray-500">default deny</span>
                                                @else
                                                    {{ $row['rule'] }}
                                                @endif
                                            </td>
                                            <td class="px-4 py-2">{{ $row['logged'] === [] ? '-' : implode(', ', $row['logged']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if ($result['rows_shown'] < $result['total'])
                            <p class="text-sm text-gray-600">
                                Menampilkan {{ number_format($result['rows_shown'], 0, ',', '.') }} dari
                                {{ number_format($result['total'], 0, ',', '.') }} trafik. Seluruh hasil sudah tersimpan
                                @if (Route::has('logs.decisions'))
                                    dan bisa dilihat di <a href="{{ route('logs.decisions') }}" class="underline">Log Keputusan</a>.
                                @else
                                    di log keputusan.
                                @endif
                            </p>
                        @endif
                    @endif

                    @if ($result['error_count'] > 0)
                        <div class="space-y-2">
                            <h4 class="font-semibold text-red-700">
                                {{ number_format($result['error_count'], 0, ',', '.') }} baris dilewati karena tidak valid
                            </h4>
                            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left font-semibold text-gray-600">Baris</th>
                                            <th class="px-4 py-2 text-left font-semibold text-gray-600">Masalah</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($result['errors'] as $error)
                                            <tr>
                                                <td class="px-4 py-2 whitespace-nowrap">{{ $error['line'] }}</td>
                                                <td class="px-4 py-2">{{ $error['message'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if ($result['error_count'] > count($result['errors']))
                                <p class="text-sm text-gray-600">
                                    Menampilkan {{ count($result['errors']) }} galat pertama dari
                                    {{ number_format($result['error_count'], 0, ',', '.') }}.
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
