
<div {{ $attributes->merge([
    'class' => 'overflow-x-auto bg-white shadow-sm sm:rounded-lg'
]) }}>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        {{ $slot }}
    </table>
</div>
