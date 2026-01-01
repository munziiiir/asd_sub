<x-layouts.app.base :title="$title ?? null">
    <x-slot name="header"></x-slot>

    {{ $slot }}

    <x-slot name="scripts">
        @fluxScripts
    </x-slot>
</x-layouts.app.base>
