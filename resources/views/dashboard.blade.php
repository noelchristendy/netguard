<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dashboard NetGuard
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Statistik -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

                <div class="bg-white shadow-sm rounded-lg p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">
                                Total Trafik Allow
                            </p>
                            <h3 class="text-3xl font-bold text-green-700 mt-2">
                                {{ number_format($stats['allow']) }}
                            </h3>
                            <p class="text-xs text-gray-400 mt-1">
                                Trafik yang diizinkan
                            </p>
                        </div>
                        <div class="text-green-600 text-3xl">
                            <span>&#10003;</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">
                                Total Trafik Deny
                            </p>
                            <h3 class="text-3xl font-bold text-red-700 mt-2">
                                {{ number_format($stats['deny']) }}
                            </h3>
                            <p class="text-xs text-gray-400 mt-1">
                                Trafik yang ditolak
                            </p>
                        </div>
                        <div class="text-red-600 text-3xl">
                            <span>&#10005;</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">
                                Total Log
                            </p>
                            <h3 class="text-3xl font-bold text-yellow-700 mt-2">
                                {{ number_format($stats['logs']) }}
                            </h3>
                            <p class="text-xs text-gray-400 mt-1">
                                Trafik yang melewati rule log
                            </p>
                        </div>
                        <div class="text-yellow-600 text-3xl">
                            <span>&#9679;</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Grafik Trafik -->
            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="mb-5">
                    <h3 class="text-lg font-semibold text-gray-800">
                        Grafik Trafik
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Perbandingan trafik Allow dan Deny selama tujuh hari terakhir.
                    </p>
                </div>

                <div class="relative w-full" style="height: 320px;">
                    <canvas
                        id="trafficChart"
                        data-chart="{{ json_encode($trafficChart) }}"
                    ></canvas>
                </div>
            </div>

            <!-- Top 5 Rules -->
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">
                        Top 5 Rules
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Lima kelompok rule dengan jumlah keputusan trafik terbanyak.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left font-semibold text-gray-600">
                                    Peringkat
                                </th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-600">
                                    Rule
                                </th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-600">
                                    Keterangan
                                </th>
                                <th class="px-6 py-3 text-right font-semibold text-gray-600">
                                    Jumlah Trafik
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100">
                            @forelse ($topRules as $rule)
                                <tr>
                                    <td class="px-6 py-4">
                                        {{ $loop->iteration }}
                                    </td>

                                    <td class="px-6 py-4 font-medium text-gray-800">
                                        @if ($rule->rule_id === null)
                                            @if ($rule->is_default)
                                                Default Deny
                                            @else
                                                Rule Terhapus
                                            @endif
                                        @else
                                            Rule #{{ $rule->priority }}
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-gray-600">
                                        @if ($rule->rule_id === null)
                                            @if ($rule->is_default)
                                                Penolakan oleh kebijakan default
                                            @else
                                                Rule sudah dihapus dari database
                                            @endif
                                        @else
                                            {{ $rule->description ?? '-' }}
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-right font-semibold">
                                        {{ number_format($rule->total) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-gray-500">
                                        Belum ada data penggunaan rule.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
