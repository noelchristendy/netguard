<x-guest-layout>
    <p class="ng-muted" style="margin:0">Masuk untuk mengelola rule penyaringan jaringan.</p>

    @if ($errors->any())
        <div class="ng-note err" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="ng-stack">
        @csrf
        <label class="ng-label" for="username">Username
            <input id="username" class="ng-input" type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username">
        </label>
        <label class="ng-label" for="password">Kata sandi
            <input id="password" class="ng-input" type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="ng-btn">Masuk</button>
    </form>
</x-guest-layout>
