@props([
    'title' => null,
    'titleColor' => 'primary',
    'description' => null,
])

<header {{ $attributes->class('bg-base-100 shadow-sm') }}>
    <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-4 md:flex-row md:items-center md:justify-between">
        <div>
            @if ($title)
                <p class="text-md font-semibold uppercase tracking-widest text-{{ $titleColor }}">{{ $title }}</p>
            @endif

            @if ($description)
                <p class="text-md text-base-content/70">{{ $description }}</p>
            @endif

            {{-- remove slot if no use case? --}}
            {{ $slot }}
        </div>

        @isset($actions)
            <div class="flex flex-wrap gap-3">
                {{ $actions }}
            </div>
        @endisset
    </div>
</header>
