<x-app-layout>
    <x-slot name="header">
        <div class="ng-head">
            <h1>Manajemen Rule</h1>
            <a href="{{ route('rules.create') }}" class="ng-btn">Tambah Rule</a>
        </div>
    </x-slot>

    <p class="ng-muted" style="margin:0;max-width:720px">
        Rule dievaluasi dari <strong>prioritas terkecil</strong>. Rule <strong>allow</strong> atau <strong>deny</strong>
        pertama yang cocok menentukan keputusan, rule <strong>log</strong> hanya mencatat lalu evaluasi berlanjut.
        Trafik yang tidak cocok dengan rule mana pun ditolak (default deny).
    </p>

    @if ($rules->isEmpty())
        <div class="ng-panel ng-pad ng-muted">Belum ada rule. Tanpa rule, semua trafik ditolak (default deny).</div>
    @else
        <div class="ng-panel ng-scroll">
            <table class="ng-table">
                <thead>
                    <tr><th>Prioritas</th><th>Kondisi</th><th>Aksi</th><th>Status</th><th>Dibuat oleh</th><th>Tindakan</th></tr>
                </thead>
                <tbody>
                    @foreach ($rules as $rule)
                        @php
                            $conditions = [];
                            if ($rule->src_ip) { $conditions[] = 'Sumber '.$rule->src_ip; }
                            if ($rule->dst_ip) { $conditions[] = 'Tujuan '.$rule->dst_ip; }
                            if ($rule->protocol !== 'any') { $conditions[] = 'Protokol '.$rule->protocol; }
                            if ($rule->dst_port) { $conditions[] = 'Port '.$rule->dst_port; }

                            $overnight = false;
                            if ($rule->time_start && $rule->time_end) {
                                $conditions[] = 'Jam '.substr($rule->time_start, 0, 5).' - '.substr($rule->time_end, 0, 5);
                                $overnight = $rule->time_start > $rule->time_end;
                            }

                            $opensAll = $rule->enabled && $rule->action === 'allow' && $conditions === [];
                            $actionClass = ['allow' => 'ng-ok', 'deny' => 'ng-no', 'log' => 'ng-lg'][$rule->action] ?? '';
                        @endphp
                        <tr @class(['ng-muted' => ! $rule->enabled])>
                            <td><b>{{ $rule->priority }}</b></td>
                            <td>
                                @if ($conditions === [])
                                    <span class="ng-muted">Semua trafik</span>
                                @else
                                    <span class="ng-mono">{{ implode(' · ', $conditions) }}</span>
                                @endif
                                @if ($overnight)
                                    <div class="ng-muted" style="font-size:12px">Jendela waktu melewati tengah malam</div>
                                @endif
                                @if ($rule->description)
                                    <div class="ng-muted" style="font-size:12px">{{ $rule->description }}</div>
                                @endif
                                @if ($opensAll)
                                    <div class="ng-no" style="text-transform:none;letter-spacing:0;font-size:13px">Peringatan: rule allow tanpa kondisi membuka seluruh trafik.</div>
                                @endif
                            </td>
                            <td><span class="{{ $actionClass }}">{{ $rule->action }}</span></td>
                            <td>{{ $rule->enabled ? 'Aktif' : 'Nonaktif' }}</td>
                            <td>{{ $rule->creator?->username ?? '-' }}</td>
                            <td>
                                <div class="ng-actions">
                                    @unless ($loop->first)
                                        <form method="POST" action="{{ route('rules.move', $rule) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="up">
                                            <button type="submit" title="Naikkan prioritas" class="ng-link">Naik</button>
                                        </form>
                                    @endunless
                                    @unless ($loop->last)
                                        <form method="POST" action="{{ route('rules.move', $rule) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="down">
                                            <button type="submit" title="Turunkan prioritas" class="ng-link">Turun</button>
                                        </form>
                                    @endunless
                                    <form method="POST" action="{{ route('rules.toggle', $rule) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="ng-link">{{ $rule->enabled ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </form>
                                    <a href="{{ route('rules.edit', $rule) }}">Ubah</a>
                                    <form method="POST" action="{{ route('rules.destroy', $rule) }}"
                                          onsubmit="return confirm('Hapus rule prioritas {{ $rule->priority }}? Log lama dari rule ini akan kehilangan tautan rule-nya. Pertimbangkan menonaktifkan rule sebagai gantinya.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ng-link bad">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
