<x-layouts.app.base :title="'Reservation '. $reservation->code">
    <x-slot name="header">
        <x-staff.header
            title="Front Desk Hub"
            titleColor="secondary"
        >
            <x-slot name="actions">
                <a href="{{ route('staff.reservations.index', $backQuery) }}" class="btn btn-ghost btn-sm md:btn-md">
                    &larr; Back to reservations
                </a>
            </x-slot>
        </x-staff.header>
    </x-slot>

    @php
        $statusLower = strtolower($reservation->status);
        $isCancelled = $statusLower === 'cancelled';
        $isCheckedOut = $statusLower === 'checkedout';
        $isCheckedIn = $statusLower === 'checkedin';
    @endphp

    <div class="bg-base-200 pt-4">
        <div class="mx-auto flex max-w-6xl items-center gap-2 px-4 text-sm text-base-content/70 flex-wrap">
            <a href="{{ route('staff.frontdesk') }}" class="link text-secondary font-semibold">Front Desk Hub</a>
            <span class="text-base-content/50">→</span>
            <a href="{{ route('staff.reservations.index', $backQuery) }}" class="link">Reservations</a>
            <span class="text-base-content/50">→</span>
            <span>{{ $reservation->code }}</span>
        </div>
    </div>

    <section class="bg-base-200 py-6">
        <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="space-y-1">
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        {{ $reservation->code }}
                        <span class="badge badge-outline badge-lg">{{ $reservation->status }}</span>
                    </h1>
                    <p class="text-md text-base-content/70">Guest: {{ ($reservation->customer->name ?? 'Guest profile missing') }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="btn btn-outline btn-error btn-sm md:btn-md"
                        onclick="document.getElementById('cancelModal').showModal()"
                        @disabled($isCancelled || $isCheckedOut || $isCheckedIn)
                    >
                        Cancel reservation
                    </button>
                </div>
            </div>

            <livewire:staff.reservations.edit-reservation
                :reservation="$reservation"
                :statuses="$statuses"
                :back-query="$backQuery"
            />
        </div>

        <dialog id="cancelModal" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Cancel reservation</h3>
                <p class="py-4">Are you sure you want to mark reservation {{ $reservation->code }} as cancelled? This action cannot be undone.</p>
                <div class="modal-action">
                    <form method="dialog">
                        <button class="btn btn-ghost">Keep active</button>
                    </form>
                    <form method="POST" action="{{ route('staff.reservations.cancel', array_merge([$reservation], $backQuery)) }}">
                        @csrf
                        @method('PATCH')
                        @foreach ($filterPayload as $key => $value)
                            <input type="hidden" name="__filter[{{ $key }}]" value="{{ $value }}">
                        @endforeach
                        <button type="submit" class="btn btn-error">Yes, cancel</button>
                    </form>
                </div>
            </div>
        </dialog>
    </section>
</x-layouts.app.base>
