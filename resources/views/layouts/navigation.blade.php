@use('App\Models\Role')

@php
    $role = Auth::user()->role->name;

    // Menu dan hak akses tetap mengikuti backend asli.
    $menus = [
        ['Dashboard', 'dashboard', Role::ALL, 'dashboard'],
        ['Manajemen Rule', 'rules.index', Role::RULE_MANAGERS, 'rules.*'],
        ['Penyaringan', 'filter.index', Role::RULE_MANAGERS, 'filter.*'],
        ['Log Keputusan', 'logs.decisions', Role::ALL, 'logs.decisions'],
        ['Log Audit', 'logs.audits', Role::AUDIT_VIEWERS, 'logs.audits'],
        ['Pengguna', 'users.index', Role::USER_MANAGERS, 'users.*'],
    ];

    // Menu yang ditampilkan sesuai role dan route yang tersedia.
    $visibleMenus = collect($menus)->filter(
        fn ($m) => in_array($role, $m[2], true) && Route::has($m[1])
    );

    $roleClass = match (strtolower($role)) {
        'administrator' => 'role-administrator',
        'operator' => 'role-operator',
        'auditor' => 'role-auditor',
        default => 'role-operator',
    };
@endphp


{{-- ========================================= --}}
{{-- NETGUARD SIDEBAR --}}
{{-- ========================================= --}}

<aside
    class="ng-sidebar"
    :class="{ 'is-open': sidebarOpen }"
    aria-label="Main navigation"
>

    {{-- SIDEBAR HEADER --}}
    <div class="ng-sidebar-head">

        <a href="{{ route('dashboard') }}" class="ng-brand">

            <span class="ng-brand-mark">

                <svg
                    width="23"
                    height="23"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>

            </span>

            <span>
                <span class="ng-brand-name">NetGuard</span>

                <span class="ng-brand-caption">
                    SECURITY CONSOLE
                </span>
            </span>

        </a>


        {{-- DESKTOP COLLAPSE --}}
        <button
            type="button"
            class="ng-collapse-button"
            @click="sidebarCollapsed = !sidebarCollapsed"
            aria-label="Collapse or expand sidebar"
            title="Collapse sidebar"
        >
            <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <rect x="3" y="3" width="18" height="18" rx="3"/>
                <path d="M9 3v18"/>
            </svg>
        </button>


        {{-- MOBILE CLOSE --}}
        <button
            type="button"
            class="ng-mobile-close"
            @click="sidebarOpen = false"
            aria-label="Close navigation"
        >
            <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
                <path d="m18 6-12 12M6 6l12 12"/>
            </svg>
        </button>

    </div>


    {{-- ========================================= --}}
    {{-- NAVIGATION LINKS --}}
    {{-- ========================================= --}}

    <div class="ng-sidebar-section">
        Workspace
    </div>

    <nav class="ng-sidebar-nav">

        @foreach ($visibleMenus as [$label, $route, $roles, $pattern])

            <a
                href="{{ route($route) }}"
                class="ng-sidebar-link {{ request()->routeIs($pattern) ? 'is-active' : '' }}"
                :title="sidebarCollapsed ? '{{ $label }}' : ''"
                @click="sidebarOpen = false"
                @if(request()->routeIs($pattern))
                    aria-current="page"
                @endif
            >

                <span class="ng-sidebar-icon">

                    @switch($route)

                        {{-- DASHBOARD --}}
                        @case('dashboard')

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <rect x="3" y="3" width="8" height="8" rx="2"/>
                                <rect x="13" y="3" width="8" height="5" rx="2"/>
                                <rect x="13" y="10" width="8" height="11" rx="2"/>
                                <rect x="3" y="13" width="8" height="8" rx="2"/>
                            </svg>

                            @break


                        {{-- RULE MANAGEMENT --}}
                        @case('rules.index')

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M4 6h16M4 12h16M4 18h16"/>
                                <circle cx="9" cy="6" r="1"/>
                                <circle cx="15" cy="12" r="1"/>
                                <circle cx="7" cy="18" r="1"/>
                            </svg>

                            @break


                        {{-- FILTER --}}
                        @case('filter.index')

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M4 5h16l-6.5 7.5V19l-3 1v-7.5L4 5z"/>
                            </svg>

                            @break


                        {{-- DECISION LOG --}}
                        @case('logs.decisions')

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M3 12h4l3-8 4 16 3-8h4"/>
                            </svg>

                            @break


                        {{-- AUDIT LOG --}}
                        @case('logs.audits')

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <rect x="4" y="3" width="16" height="18" rx="2"/>
                                <path d="M8 8h8M8 12h8M8 16h5"/>
                            </svg>

                            @break


                        {{-- USER MANAGEMENT --}}
                        @case('users.index')

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M20 8v6M23 11h-6"/>
                            </svg>

                            @break

                    @endswitch

                </span>


                <span class="ng-sidebar-label">
                    {{ $label }}
                </span>


                {{-- ACTIVE INDICATOR --}}
                @if(request()->routeIs($pattern))

                    <span
                        class="ml-auto"
                        style="
                            width: 5px;
                            height: 5px;
                            background: #38bdf8;
                            border-radius: 50%;
                        "
                    ></span>

                @endif

            </a>

        @endforeach

    </nav>


    {{-- ========================================= --}}
    {{-- SIDEBAR FOOTER / PROFILE --}}
    {{-- ========================================= --}}

    <div class="ng-sidebar-footer">

        <div class="ng-profile-wrap">


            {{-- PROFILE BUTTON --}}
            <button
                type="button"
                class="ng-user-card ng-profile-trigger"
                @click="profileOpen = !profileOpen"
                :aria-expanded="profileOpen"
                aria-label="User profile menu"
            >

                {{-- AVATAR --}}
                <span class="ng-user-avatar">
                    {{ strtoupper(substr(Auth::user()->username, 0, 1)) }}
                </span>


                {{-- USER INFORMATION --}}
                <span class="ng-user-meta">

                    <span class="ng-user-name">
                        {{ Auth::user()->username }}
                    </span>

                    <span class="role-chip {{ $roleClass }}">
                        {{ ucfirst(strtolower($role)) }}
                    </span>

                </span>


                {{-- CHEVRON --}}
                <svg
                    class="ng-profile-chevron"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    :style="profileOpen ? 'transform:rotate(180deg)' : ''"
                >
                    <path d="m6 9 6 6 6-6"/>
                </svg>

            </button>


            {{-- PROFILE DROPDOWN --}}
            <div
                class="ng-profile-menu"
                x-show="profileOpen"
                x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click.outside="profileOpen = false"
            >

                <div class="ng-profile-menu-heading">
                    ACCOUNT
                </div>


                <div
                    style="
                        padding: 6px 9px 12px;
                        border-bottom: 1px solid #30363d;
                    "
                >
                    <div style="
                        color: #e6edf3;
                        font-weight: 600;
                        font-size: 13px;
                    ">
                        {{ Auth::user()->username }}
                    </div>

                    <div style="
                        color: #8b949e;
                        font-size: 11px;
                        margin-top: 3px;
                    ">
                        {{ ucfirst(strtolower($role)) }}
                    </div>
                </div>


                {{-- LOGOUT --}}
                <form
                    method="POST"
                    action="{{ route('logout') }}"
                >
                    @csrf

                    <button
                        type="submit"
                        @click="profileOpen = false"
                    >

                        <svg
                            width="17"
                            height="17"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <path d="M16 17l5-5-5-5"/>
                            <path d="M21 12H9"/>
                        </svg>

                        Logout

                    </button>

                </form>

            </div>

        </div>


        {{-- SYSTEM INFORMATION --}}
        <div class="ng-logout-button">

            <span class="ng-sidebar-icon">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 11v5M12 8h.01"/>
                </svg>
            </span>

            <span class="ng-logout-label">
                Network Security
            </span>

        </div>

    </div>

</aside>