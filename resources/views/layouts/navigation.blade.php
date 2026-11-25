@use('App\Models\Role')
@php
    $role = Auth::user()->role->name;

    // [label, nama route, peran yang boleh melihat, pola route untuk status aktif]
    $menus = [
        ['Dashboard',      'dashboard',      Role::ALL,           'dashboard'],
        ['Manajemen Rule', 'rules.index',    Role::RULE_MANAGERS, 'rules.*'],
        ['Penyaringan',    'filter.index',   Role::RULE_MANAGERS, 'filter.*'],
        ['Log Keputusan',  'logs.decisions', Role::ALL,           'logs.decisions'],
        ['Log Audit',      'logs.audits',    Role::AUDIT_VIEWERS, 'logs.audits'],
        ['Pengguna',       'users.index',    Role::USER_MANAGERS, 'users.*'],
    ];

    // Menu hanya tampilan. Keamanan sebenarnya ada di middleware EnsureRole.
    $visibleMenus = collect($menus)->filter(fn ($m) => in_array($role, $m[2], true) && Route::has($m[1]));
@endphp
<header class="ng-bar">
    <a href="{{ route('dashboard') }}" class="ng-brand">NetGuard</a>
    <nav class="ng-nav" aria-label="Menu utama">
        @foreach ($visibleMenus as [$label, $route, $roles, $pattern])
            <a href="{{ route($route) }}" @class(['on' => request()->routeIs($pattern)]) @if (request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <span class="ng-muted">{{ Auth::user()->username }} · {{ $role }}</span>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="ng-link">Keluar</button>
    </form>
</header>
