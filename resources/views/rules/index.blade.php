<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Manajemen Rule
            </h2>
            <a href="{{ route('rules.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Tambah Rule
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="border rounded-md px-4 py-3 text-sm bg-blue-50 text-blue-800 border-blue-300">
                Rule dievaluasi dari <strong>prioritas terkecil</strong>. Rule pertama yang cocok dengan aksi
                <strong>allow</strong> atau <strong>deny</strong> menentukan keputusan. Rule <strong>log</strong>
                hanya mencatat lalu evaluasi berlanjut. Trafik yang tidak cocok dengan rule mana pun ditolak (default deny).
            </div>

            @if ($rules->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-600">
                    Belum ada rule. Tanpa rule, semua trafik ditolak (default deny).
                </div>
            @else
                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">Prioritas</th>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">Kondisi</th>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">Aksi</th>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">Status</th>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">Dibuat oleh</th>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
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

                                    $actionClass = [
                                        'allow' => 'bg-green-100 text-green-800',
                                        'deny'  => 'bg-red-100 text-red-800',
                                        'log'   => 'bg-yellow-100 text-yellow-800',
                                    ][$rule->action] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <tr class="{{ $rule->enabled ? '' : 'bg-gray-50 text-gray-400' }}">
                                    <td class="px-4 py-2 font-semibold">{{ $rule->priority }}</td>
                                    <td class="px-4 py-2">
                                        @if ($conditions === [])
                                            <span class="italic">Semua trafik</span>
                                        @else
                                            {{ implode(' · ', $conditions) }}
                                        @endif
                                        @if ($overnight)
                                            <div class="text-xs text-gray-500">Jendela waktu melewati tengah malam</div>
                                        @endif
                                        @if ($rule->description)
                                            <div class="text-xs text-gray-500">{{ $rule->description }}</div>
                                        @endif
                                        @if ($opensAll)
                                            <div class="text-xs font-semibold text-red-700">
                                                Peringatan: rule allow tanpa kondisi membuka seluruh trafik.
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold uppercase {{ $actionClass }}">{{ $rule->action }}</span>
                                    </td>
                                    <td class="px-4 py-2">
                                        @if ($rule->enabled)
                                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">Aktif</span>
                                        @else
                                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-gray-200 text-gray-600">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">{{ $rule->creator?->username ?? '-' }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            @unless ($loop->first)
                                                <form method="POST" action="{{ route('rules.move', $rule) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="up">
                                                    <button type="submit" title="Naikkan prioritas" class="text-gray-600 hover:text-gray-900">&#9650;</button>
                                                </form>
                                            @endunless
                                            @unless ($loop->last)
                                                <form method="POST" action="{{ route('rules.move', $rule) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="down">
                                                    <button type="submit" title="Turunkan prioritas" class="text-gray-600 hover:text-gray-900">&#9660;</button>
                                                </form>
                                            @endunless

                                            <form method="POST" action="{{ route('rules.toggle', $rule) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="underline text-sm {{ $rule->enabled ? 'text-yellow-700' : 'text-green-700' }}">
                                                    {{ $rule->enabled ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>

                                            <a href="{{ route('rules.edit', $rule) }}" class="underline text-sm text-indigo-600">Ubah</a>

                                            <form method="POST" action="{{ route('rules.destroy', $rule) }}"
                                                  onsubmit="return confirm('Hapus rule prioritas {{ $rule->priority }}? Log lama dari rule ini akan kehilangan tautan rule-nya. Pertimbangkan menonaktifkan rule sebagai gantinya.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="underline text-sm text-red-700">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
