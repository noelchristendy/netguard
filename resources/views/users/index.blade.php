<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Manajemen Pengguna
            </h2>
            <a href="{{ route('users.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Tambah Pengguna
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Pesan sementara. Hapus blok ini bila layout utama (N1) sudah menampilkan flash message. --}}
            @if ($errors->any())
                <div class="border rounded-md px-4 py-3 text-sm bg-red-50 text-red-800 border-red-300">
                    <ul class="list-disc ml-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold text-gray-600">Username</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-600">Nama</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-600">Peran</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-600">Status</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-600">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($users as $u)
                            @php
                                $locked = $u->is_locked && ($u->locked_until === null || $u->locked_until->isFuture());
                                $isSelf = $u->id === auth()->id();
                            @endphp
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900">
                                    {{ $u->username }}
                                    @if ($isSelf)
                                        <span class="text-xs text-gray-400">(Anda)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-700">{{ $u->name }}</td>
                                <td class="px-4 py-2">
                                    @if ($isSelf)
                                        <span class="capitalize">{{ $u->role->name }}</span>
                                    @else
                                        <form method="POST" action="{{ route('users.role', $u) }}" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <select name="role_id" class="border-gray-300 rounded-md text-sm py-1">
                                                @foreach ($roles as $r)
                                                    <option value="{{ $r->id }}" @selected($r->id === $u->role_id)>{{ ucfirst($r->name) }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="text-indigo-600 underline text-sm">Simpan</button>
                                        </form>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if ($locked)
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">Terkunci</span>
                                        @if ($u->locked_until)
                                            <div class="text-xs text-gray-500">sampai {{ $u->locked_until->format('d M Y H:i') }}</div>
                                        @endif
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">Aktif</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @unless ($isSelf)
                                        @if ($locked)
                                            <form method="POST" action="{{ route('users.unlock', $u) }}">
                                                @csrf
                                                <button type="submit" class="text-green-700 underline text-sm">Buka kunci</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('users.lock', $u) }}"
                                                  onsubmit="return confirm('Kunci akun {{ $u->username }}?')">
                                                @csrf
                                                <button type="submit" class="text-red-700 underline text-sm">Kunci</button>
                                            </form>
                                        @endif
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
