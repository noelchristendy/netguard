@props(['value'])
@php
    $styles = [
        'allow' => 'bg-green-100 text-green-800',
        'deny'  => 'bg-red-100 text-red-800',
        'log'   => 'bg-yellow-100 text-yellow-800',
    ][strtolower($value)] ?? 'bg-gray-100 text-gray-800';
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex px-2 py-0.5 rounded text-xs font-semibold uppercase '.$styles]) }}>{{ $value }}</span>
