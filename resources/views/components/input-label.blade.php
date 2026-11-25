@props(['value'])
<label {{ $attributes->merge(['class' => 'ng-muted', 'style' => 'display:block;margin-bottom:4px']) }}>{{ $value ?? $slot }}</label>
