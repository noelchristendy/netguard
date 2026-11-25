<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ config('app.name', 'NetGuard') }}</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono&family=IBM+Plex+Sans:wght@400;600&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @include('layouts.navigation')
    <main class="ng-wrap ng-stack">
        @isset($header)
            <div>{{ $header }}</div>
        @endisset
        @if (session('success'))
            <div class="ng-note" role="status">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="ng-note err" role="alert">{{ session('error') }}</div>
        @endif
        {{ $slot }}
    </main>
</body>
</html>
