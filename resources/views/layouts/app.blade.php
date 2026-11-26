<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        {{ config('app.name', 'NetGuard') }}
    </title>

    <meta name="theme-color" content="#14171a">

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap"
          rel="stylesheet">

    {{-- CSS and JavaScript --}}
    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    @stack('styles')
</head>

<body class="font-sans antialiased netguard-theme">

    <div
        class="netguard-shell"
        x-data="{
            sidebarOpen: false,
            sidebarCollapsed: false,
            profileOpen: false
        }"
        :class="{ 'sidebar-collapsed': sidebarCollapsed }"
        @keydown.escape.window="
            sidebarOpen = false;
            profileOpen = false;
        "
    >

        {{-- ================================= --}}
        {{-- SIDEBAR NAVIGATION --}}
        {{-- ================================= --}}

        @include('layouts.navigation')


        {{-- MOBILE SIDEBAR BACKDROP --}}
        <div
            class="ng-sidebar-backdrop"
            x-show="sidebarOpen"
            x-cloak
            x-transition.opacity
            @click="sidebarOpen = false"
        ></div>


        {{-- ================================= --}}
        {{-- MAIN APPLICATION --}}
        {{-- ================================= --}}

        <div class="app-main">


            {{-- ================================= --}}
            {{-- TOPBAR --}}
            {{-- ================================= --}}

            <header class="app-topbar">

                <div class="app-topbar-inner">

                    <div class="app-topbar-left">

                        {{-- Mobile menu --}}
                        <button
                            type="button"
                            class="ng-mobile-menu-button"
                            @click="sidebarOpen = true"
                            aria-label="Open navigation menu"
                        >
                            <svg
                                width="20"
                                height="20"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                            >
                                <path d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>


                        {{-- Page heading --}}
                        <div class="app-page-heading">

                            @isset($header)

                                {{ $header }}

                            @else

                                <h2>Network Security Console</h2>

                                <p class="app-topbar-meta">
                                    Network monitoring and management
                                </p>

                            @endisset

                        </div>

                    </div>


                    {{-- Right topbar information --}}
                    <div class="app-topbar-right">

                        <div class="ng-period-chip">

                            <span class="ng-period-dot"></span>

                            <span>NetGuard System</span>

                        </div>

                    </div>

                </div>

            </header>


            {{-- ================================= --}}
            {{-- SESSION NOTIFICATIONS --}}
            {{-- ================================= --}}

            @if (session('success') || session('error'))

                <div
                    class="ng-toast-stack"
                    aria-live="polite"
                    aria-atomic="false"
                >

                    {{-- SUCCESS TOAST --}}
                    @if (session('success'))

                        <div
                            class="ng-toast ng-toast-success"
                            x-data="{ visible: true }"
                            x-show="visible"
                            x-init="
                                setTimeout(() => visible = false, 4500)
                            "
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 translate-x-3"
                            x-transition:enter-end="opacity-100 translate-x-0"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                        >

                            <span class="ng-toast-icon">
                                <svg
                                    width="19"
                                    height="19"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="#34d399"
                                    stroke-width="2"
                                >
                                    <path d="m5 12 4 4L19 6"/>
                                </svg>
                            </span>

                            <div class="ng-toast-message">
                                {{ session('success') }}
                            </div>

                            <button
                                type="button"
                                class="ng-toast-close"
                                @click="visible = false"
                                aria-label="Close notification"
                            >
                                &times;
                            </button>

                        </div>

                    @endif


                    {{-- ERROR TOAST --}}
                    @if (session('error'))

                        <div
                            class="ng-toast ng-toast-error"
                            x-data="{ visible: true }"
                            x-show="visible"
                            x-init="
                                setTimeout(() => visible = false, 4500)
                            "
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 translate-x-3"
                            x-transition:enter-end="opacity-100 translate-x-0"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                        >

                            <span class="ng-toast-icon">
                                <svg
                                    width="19"
                                    height="19"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="#fb7185"
                                    stroke-width="2"
                                >
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="m15 9-6 6m0-6 6 6"/>
                                </svg>
                            </span>

                            <div class="ng-toast-message">
                                {{ session('error') }}
                            </div>

                            <button
                                type="button"
                                class="ng-toast-close"
                                @click="visible = false"
                                aria-label="Close notification"
                            >
                                &times;
                            </button>

                        </div>

                    @endif

                </div>

            @endif


            {{-- ================================= --}}
            {{-- MAIN CONTENT --}}
            {{-- ================================= --}}

            <main id="main-content">

                {{ $slot }}

            </main>


            {{-- ================================= --}}
            {{-- FOOTER --}}
            {{-- ================================= --}}

            <footer class="app-footer">

                <div>
                    NetGuard &copy; {{ date('Y') }}
                    <span> &mdash; Network Security Management System</span>
                </div>

            </footer>

        </div>

    </div>


    @stack('scripts')

</body>
</html>