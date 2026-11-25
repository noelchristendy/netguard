@props(['messages'])
@if ($messages)
    <ul {{ $attributes->merge(['class' => 'ng-no', 'style' => 'list-style:none;margin:4px 0 0;padding:0;text-transform:none;letter-spacing:0;font-size:13px']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
