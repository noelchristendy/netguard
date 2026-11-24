<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Log Keputusan
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Filter -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">
                    Filter Log Keputusan
                </h3>

                <form method="GET" action="{{ route('logs.decisions') }}"
                      class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Tanggal Mulai
                        </label>
                        <input
                            type="date"
                            name="date_from"
                            value="{{ request('date_from') }}"
                            class="w-full border-gray-300 rounded-md shadow-sm text-sm"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Tanggal Akhir
                        </label>
                        <input
                            type="date"
                            name="date_to"
                            value="{{ request('date_to') }}"
                            class="w-full border-gray-300 rounded-md shadow-sm text-sm"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            IP Address
                        </label>
                        <input
                            type="text"
                            name="ip"
                            value="{{ request('ip') }}"
                            placeholder="Contoh: 192.168.1.10"
                            class="w-full border-gray-300 rounded-md shadow-sm text-sm"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Keputusan
                        </label>
                        <select
                            name="decision"
                            class="w-full border-gray-300 rounded-md shadow-sm text-sm"
                        >
                            <option value="">Semua Keputusan</option>
                            <option value="allow" @selected(request('decision') === 'allow')>
                                Allow
                            </option>
                            <option value="deny" @selected(request('decision') === 'deny')>
                                Deny
                            </option>
                        </select>
                    </div>

                    <div class="md:col-span-2 lg:col-span-4 flex gap-2">
                        <button
                            type="submit"
                            class="px-4 py-2 bg-gray-800 text-white rounded-md text-sm hover:bg-gray-700"
                        >
                            Terapkan Filter
                        </button>

                        <a
                            href="{{ route('logs.decisions') }}"
                            class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50"
                        >
                            Reset
                        </a>
                    </div>
                </form>
                <div class="mt-4 flex flex-wrap gap-2">
                    @php
                        $exportFilters = request()->only([
                            'date_from',
                            'date_to',
                            'ip',
                            'decision',
                        ]);
                    @endphp

                    <a
                        href="{{ route('export.decisions', array_merge($exportFilters, ['format' => 'csv'])) }}"
                        class="px-4 py-2 bg-green-700 text-white rounded-md text-sm hover:bg-green-800"
                    >
                        Export CSV
                    </a>

                    <a
                        href="{{ route('export.decisions', array_merge($exportFilters, ['format' => 'pdf'])) }}"
                        class="px-4 py-2 bg-red-700 text-white rounded-md text-sm hover:bg-red-800"
                    >
                        Export PDF
                    </a>
                </div>
            </div>

            <!-- Tabel Log -->
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-800">
                        Riwayat Keputusan Trafik
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Menampilkan {{ $logs->count() }} dari {{ $logs->total() }} data.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Waktu</th>
                                <th class="px-4 py-3 text-left">IP Sumber</th>
                                <th class="px-4 py-3 text-left">IP Tujuan</th>
                                <th class="px-4 py-3 text-left">Protokol</th>
                                <th class="px-4 py-3 text-left">Port</th>
                                <th class="px-4 py-3 text-left">Keputusan</th>
                                <th class="px-4 py-3 text-left">Rule Penentu</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100">
                            @forelse ($logs as $log)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ $log->timestamp?->format('d-m-Y H:i:s') }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $log->src_ip }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $log->dst_ip }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $log->protocol }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $log->dst_port ?? '-' }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <span class="inline-flex px-2 py-1 rounded text-xs font-semibold
                                            {{ $log->decision === 'allow'
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-red-100 text-red-800' }}">
                                            {{ strtoupper($log->decision) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3">
                                        @if ($log->is_default)
                                            <span class="text-red-700 font-medium">
                                                Default Deny
                                            </span>
                                        @elseif ($log->rule)
                                            <div class="font-medium">
                                                Rule #{{ $log->rule->priority }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{ $log->rule->description }}
                                            </div>
                                        @else
                                            <span class="text-gray-500">
                                                Rule telah dihapus
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                                        Belum ada data log keputusan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-gray-200">
                    {{ $logs->links() }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
