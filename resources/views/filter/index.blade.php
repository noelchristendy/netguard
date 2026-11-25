<x-app-layout>
    <x-slot name="header">
        <h1 class="ng-h1">Penyaringan Trafik</h1>
    </x-slot>

    @if ($activeRules === 0)
        <div class="ng-note warn">
            Tidak ada rule aktif, sehingga semua trafik akan ditolak (default deny).
            @if (Route::has('rules.index')) <a href="{{ route('rules.index') }}">Kelola rule</a> @endif
        </div>
    @else
        <div class="ng-note">
            {{ $activeRules }} rule aktif akan dipakai. Trafik dievaluasi dari prioritas terkecil.
            @if (Route::has('rules.index')) <a href="{{ route('rules.index') }}">Lihat rule</a> @endif
        </div>
    @endif

    <div class="ng-grid2">
        <form method="POST" action="{{ route('filter.upload') }}" enctype="multipart/form-data" class="ng-panel ng-pad ng-stack">
            @csrf
            <b>Unggah CSV</b>
            <input type="file" name="csv" required accept=".csv,.txt,text/csv,text/plain" class="ng-input" style="padding-top:9px">
            <x-input-error :messages="$errors->get('csv')" />
            <p class="ng-muted" style="margin:0;font-size:12px">
                Kolom: <span class="ng-mono">timestamp, src_ip, dst_ip, protocol, dst_port</span>. Port dikosongkan untuk ICMP.
                Berkas .csv atau .txt (UTF-8), maksimal 5 MB dan 50.000 baris. Baris yang tidak valid dilewati dan dilaporkan.
            </p>
            <div><x-primary-button>Proses CSV</x-primary-button></div>
        </form>

        <form method="POST" action="{{ route('filter.single') }}" class="ng-panel ng-pad ng-stack"
              x-data="{ protocol: @js(old('protocol', 'TCP')) }">
            @csrf
            <b>Input Satu Trafik</b>
            <label class="ng-label" for="timestamp">Waktu
                <x-text-input id="timestamp" name="timestamp" type="datetime-local" step="1"
                              :value="old('timestamp', now()->format('Y-m-d\TH:i:s'))" required />
            </label>
            <div class="ng-grid2">
                <label class="ng-label" for="src_ip">IP sumber
                    <x-text-input id="src_ip" name="src_ip" class="ng-input ng-mono" :value="old('src_ip')" placeholder="192.168.1.10" required autocomplete="off" />
                </label>
                <label class="ng-label" for="dst_ip">IP tujuan
                    <x-text-input id="dst_ip" name="dst_ip" class="ng-input ng-mono" :value="old('dst_ip')" placeholder="10.0.0.5" required autocomplete="off" />
                </label>
                <label class="ng-label" for="protocol">Protokol
                    <select id="protocol" name="protocol" x-model="protocol" class="ng-input">
                        @foreach (['TCP', 'UDP', 'ICMP'] as $proto)
                            <option value="{{ $proto }}" @selected(old('protocol', 'TCP') === $proto)>{{ $proto }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ng-label" for="dst_port">Port tujuan
                    <x-text-input id="dst_port" name="dst_port" type="number" min="1" max="65535" class="ng-input ng-mono"
                                  :value="old('dst_port')" placeholder="443" x-bind:disabled="protocol === 'ICMP'" />
                </label>
            </div>
            <x-input-error :messages="$errors->get('traffic')" />
            <div><x-primary-button>Evaluasi</x-primary-button></div>
        </form>
    </div>

    @if ($result)
        <section class="ng-stack">
            <h2 class="ng-h1" style="font-size:16px">
                Hasil Penyaringan
                @if ($result['filename']) <span class="ng-muted" style="font-weight:400">({{ $result['filename'] }})</span> @endif
            </h2>

            <div style="display:flex;flex-wrap:wrap;gap:8px 32px">
                <span><b>{{ number_format($result['total'], 0, ',', '.') }}</b> trafik dievaluasi</span>
                <span class="ng-ok">{{ number_format($result['allow'], 0, ',', '.') }} allow</span>
                <span class="ng-no">{{ number_format($result['deny'], 0, ',', '.') }} deny</span>
                <span class="ng-muted">{{ number_format($result['default_deny'], 0, ',', '.') }} default deny</span>
                <span class="ng-lg">{{ number_format($result['logged'], 0, ',', '.') }} dicatat rule log</span>
            </div>

            @if ($result['truncated'])
                <div class="ng-note warn">Berkas melebihi batas baris. Hanya bagian pertama yang diproses.</div>
            @endif

            @if ($result['rows'] !== [])
                <div class="ng-panel ng-scroll">
                    <table class="ng-table">
                        <thead>
                            <tr><th>Waktu</th><th>Sumber</th><th>Tujuan</th><th>Proto</th><th>Port</th><th>Keputusan</th><th>Rule penentu</th><th>Rule log</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($result['rows'] as $row)
                                <tr class="ng-mono">
                                    <td style="white-space:nowrap">{{ $row['timestamp'] }}</td>
                                    <td>{{ $row['src_ip'] }}</td>
                                    <td>{{ $row['dst_ip'] }}</td>
                                    <td>{{ $row['protocol'] }}</td>
                                    <td>{{ $row['dst_port'] ?? '-' }}</td>
                                    <td><span class="{{ $row['decision'] === 'allow' ? 'ng-ok' : 'ng-no' }}">{{ $row['decision'] }}</span></td>
                                    <td>
                                        @if ($row['is_default'])
                                            <span class="ng-muted">default deny</span>
                                        @else
                                            {{ $row['rule'] }}
                                        @endif
                                    </td>
                                    <td>{{ $row['logged'] === [] ? '-' : implode(', ', $row['logged']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($result['rows_shown'] < $result['total'])
                    <p class="ng-muted" style="margin:0">
                        Menampilkan {{ number_format($result['rows_shown'], 0, ',', '.') }} dari {{ number_format($result['total'], 0, ',', '.') }} trafik.
                        Seluruh hasil sudah tersimpan
                        @if (Route::has('logs.decisions'))
                            dan bisa dilihat di <a href="{{ route('logs.decisions') }}">Log Keputusan</a>.
                        @else
                            di log keputusan.
                        @endif
                    </p>
                @endif
            @endif

            @if ($result['error_count'] > 0)
                <b class="ng-no" style="text-transform:none;letter-spacing:0;font-size:14px">
                    {{ number_format($result['error_count'], 0, ',', '.') }} baris dilewati karena tidak valid
                </b>
                <div class="ng-panel ng-scroll">
                    <table class="ng-table">
                        <thead><tr><th style="width:96px">Baris</th><th>Masalah</th></tr></thead>
                        <tbody>
                            @foreach ($result['errors'] as $error)
                                <tr><td>{{ $error['line'] }}</td><td class="ng-mono">{{ $error['message'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($result['error_count'] > count($result['errors']))
                    <p class="ng-muted" style="margin:0">
                        Menampilkan {{ count($result['errors']) }} galat pertama dari {{ number_format($result['error_count'], 0, ',', '.') }}.
                    </p>
                @endif
            @endif
        </section>
    @endif
</x-app-layout>
