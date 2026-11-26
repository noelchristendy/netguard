<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        {{ config('app.name', 'NetGuard') }} - Secure Login
    </title>

    <meta name="theme-color" content="#14171a">

    {{-- Font --}}
    <link rel="preconnect" href="https://fonts.bunny.net">

    <link
        href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap"
        rel="stylesheet"
    >

    {{-- Vite --}}
    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    @stack('styles')

</head>

<body class="font-sans antialiased netguard-theme">

    <main class="guest-shell">

        {{-- ================================= --}}
        {{-- BRAND --}}
        {{-- ================================= --}}

        <div class="guest-brand">

            <div class="guest-brand-mark">

                <svg
                    width="30"
                    height="30"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>

            </div>

            <h1 class="guest-brand-name">
                NetGuard
            </h1>

            <p class="guest-brand-subtitle">
                Network Security Management System
            </p>

        </div>


        {{-- ================================= --}}
        {{-- LOGIN CONTENT --}}
        {{-- ================================= --}}

        <section class="guest-card">

            <div class="mb-6">

                <p class="ng-eyebrow">
                    SECURE AUTHENTICATION
                </p>

                <h2 class="text-xl font-semibold text-white">
                    Welcome back
                </h2>

                <p class="mt-2 text-sm text-gray-400">
                    Sign in to access your network security console.
                </p>

            </div>


            {{-- Laravel authentication slot --}}
            {{ $slot }}


        </section>


        {{-- ================================= --}}
        {{-- FOOTER --}}
        {{-- ================================= --}}

        <div class="guest-footnote">

            <div class="flex items-center justify-center gap-2">

                <span
                    style="
                        width: 6px;
                        height: 6px;
                        background: #34d399;
                        border-radius: 50%;
                    "
                ></span>

                <span>
                    Secure Network Environment
                </span>

            </div>

            <p class="mt-2 text-center">
                NetGuard &copy; {{ date('Y') }}
                All rights reserved.
            </p>

        </div>

    </main>

</body>
</html>