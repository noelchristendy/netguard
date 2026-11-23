<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Tambah Pengguna
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="username" value="Username" />
                        <x-text-input id="username" name="username" type="text" class="block mt-1 w-full"
                                      :value="old('username')" required autofocus autocomplete="off" />
                        <x-input-error :messages="$errors->get('username')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="name" value="Nama" />
                        <x-text-input id="name" name="name" type="text" class="block mt-1 w-full"
                                      :value="old('name')" required autocomplete="off" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="role_id" value="Peran" />
                        <select id="role_id" name="role_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            <option value="">-- Pilih peran --</option>
                            @foreach ($roles as $r)
                                <option value="{{ $r->id }}" @selected((string) old('role_id') === (string) $r->id)>{{ ucfirst($r->name) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password" value="Kata sandi awal" />
                        <x-text-input id="password" name="password" type="password" class="block mt-1 w-full"
                                      required autocomplete="new-password" />
                        <p class="mt-1 text-xs text-gray-500">Minimal 8 karakter, mengandung huruf besar, huruf kecil, dan angka.</p>
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password_confirmation" value="Konfirmasi kata sandi" />
                        <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                                      class="block mt-1 w-full" required autocomplete="new-password" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <a href="{{ route('users.index') }}" class="text-sm text-gray-600 underline">Batal</a>
                        <x-primary-button>Simpan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
