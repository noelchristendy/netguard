
@props(['type' => 'allow'])

@php
    $styles = [
        'allow' => 'bg-green-100 text-green-800',
        'deny' => 'bg-red-100 text-red-800',
        'log' => 'bg-yellow-100 text-yellow-800',
    ];

    $style = $styles[$type] ?? $styles['log'];
@endphp

<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $style }}">
    {{ $slot }}
</span>
