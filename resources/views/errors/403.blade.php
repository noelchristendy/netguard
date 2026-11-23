<x-guest-layout>
    <div class="text-center">
        <h1 class="text-5xl font-bold text-gray-800">403</h1>
        <p class="mt-3 text-gray-600">Anda tidak memiliki hak akses ke halaman ini.</p>
        <a href="{{ route('dashboard') }}" class="mt-6 inline-block text-indigo-600 underline">
            Kembali ke Dashboard
        </a>
    </div>
</x-guest-layout>
