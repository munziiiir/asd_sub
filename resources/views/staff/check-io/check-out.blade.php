<x-layouts.app.base :title="'Guest check-out'">
    <x-slot name="header">
        <x-staff.header
            title="Front Desk Hub"
            titleColor="secondary"
        >
            <x-slot name="actions">
                <a href="{{ route('staff.check-io.index') }}" class="btn btn-ghost btn-sm md:btn-md">
                    &larr; Back to overview
                </a>
            </x-slot>
        </x-staff.header>
    </x-slot>

    <div class="bg-base-200 pt-4">
        <div class="mx-auto flex max-w-6xl items-center gap-2 px-4 text-sm text-base-content/70 flex-wrap">
            <a href="{{ route('staff.frontdesk') }}" class="link text-secondary font-semibold">Front Desk Hub</a>
            <span class="text-base-content/50">→</span>
            <a href="{{ route('staff.check-io.index') }}" class="link">Check-in / Check-out</a>
            <span class="text-base-content/50">→</span>
            <span>Check-out</span>
        </div>
    </div>

    <section class="bg-base-200 py-6">
        <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4">
            <div>
                <h1 class="text-2xl font-bold">Check-out {{ $hotelName }}</h1>
                <p class="text-md text-base-content/70">
                    Confirm folios, capture extra charges, and finalize payments before releasing rooms.
                </p>
            </div>

            <livewire:staff.check-io.check-out-form />
        </div>
    </section>
</x-layouts.app.base>
