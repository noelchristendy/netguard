@props(['type' => 'info'])

@php
    $styles = [
        'success' => 'bg-green-50 text-green-800 border-green-300',
        'error' => 'bg-red-50 text-red-800 border-red-300',
        'warning' => 'bg-yellow-50 text-yellow-800 border-yellow-300',
        'info' => 'bg-blue-50 text-blue-800 border-blue-300',
    ];

    $style = $styles[$type] ?? $styles['info'];
@endphp

<div
    {{ $attributes->merge([
        'class' => "border rounded-md px-4 py-3 text-sm {$style}"
    ]) }}
    role="alert"
>
    {{ $slot }}
</div>
