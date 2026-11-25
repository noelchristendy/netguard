<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ config('app.name', 'NetGuard') }}</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono&family=IBM+Plex+Sans:wght@400;600&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px;box-sizing:border-box">
        <div class="ng-panel ng-pad ng-stack" style="width:100%;max-width:360px">
            <div style="font-weight:600;font-size:20px">NetGuard</div>
            {{ $slot }}
        </div>
    </div>
</body>
</html>
