<x-layouts.app.base :title="'Create Reservation'">
    <x-slot name="header">
        <x-staff.header
            title="Front Desk Hub"
            titleColor="secondary"
        >
            <x-slot name="actions">
                <a href="{{ route('staff.reservations.index') }}" class="btn btn-ghost btn-sm md:btn-md">
                    &larr; Back to reservations
                </a>
            </x-slot>
        </x-staff.header>
    </x-slot>

    <div class="bg-base-200 pt-4">
        <div class="mx-auto flex max-w-6xl items-center gap-2 px-4 text-sm text-base-content/70 flex-wrap">
            <a href="{{ route('staff.frontdesk') }}" class="link text-secondary font-semibold">Front Desk Hub</a>
            <span class="text-base-content/50">→</span>
            <a href="{{ route('staff.reservations.index') }}" class="link">Reservations</a>
            <span class="text-base-content/50">→</span>
            <span>Create reservation</span>
        </div>
    </div>

    <section class="bg-base-200 py-6">
        <div class="mx-auto max-w-6xl space-y-6 px-4">
            <div>
                <h1 class="text-2xl font-bold">Create a reservation</h1>
                <p class="text-base-content/70">Capture a new booking for {{ $hotelName ?? 'this hotel' }}.</p>
            </div>

            <livewire:staff.reservations.create-reservation />
        </div>
    </section>
</x-layouts.app.base>
