<x-layouts.app.base :title="'Manage Reservations'">
    <x-slot name="header">
        <x-staff.header
            title="Front Desk Hub"
            titleColor="secondary"
        >
            <x-slot name="actions">
                <a href="{{ route('staff.frontdesk') }}" class="btn btn-ghost btn-sm md:btn-md">
                    &larr; Back to hub
                </a>
            </x-slot>
        </x-staff.header>
    </x-slot>

    <div class="bg-base-200 pt-4">
        <div class="mx-auto flex max-w-6xl items-center gap-2 px-4 text-sm text-base-content/70 flex-wrap">
            <a href="{{ route('staff.frontdesk') }}" class="link text-secondary font-semibold">Front Desk Hub</a>
            <span class="text-base-content/50">→</span>
            <span>Reservations</span>
        </div>
    </div>

    <section class="bg-base-200 py-6">
        @php
            $filters = request()->only(['status', 'code', 'page']);
            if (! request()->has('status')) {
                $filters['status'] = '__ALL__';
            }
        @endphp
        <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Reservations</h1>
                    <p class="text-md text-base-content/70">Review and manage bookings for {{ auth('staff')->user()->hotel->name }}</p>
                </div>
                <a class="btn btn-primary btn-sm md:btn-md" href="{{ route('staff.reservations.create') }}">
                    Create reservation
                </a>
            </div>

            <form method="GET" class="card bg-base-100 shadow overflow-x-auto">
                <div class="card-body">
                    <div class="flex flex-nowrap items-end gap-4 min-w-[700px]">
                        <label class="flex flex-col gap-1 w-52">
                            <span class="label-text font-semibold">Status</span>
                            <select name="status" class="select select-bordered w-full">
                                <option value="">All statuses</option>
                                @foreach ($statuses as $statusOption)
                                    <option value="{{ $statusOption }}" @selected(request('status') === $statusOption)>
                                        {{ $statusOption }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="flex flex-col gap-1 flex-1 min-w-[280px]">
                            <span class="label-text font-semibold">Reservation code</span>
                            <input
                                type="text"
                                name="code"
                                value="{{ request('code') }}"
                                class="input input-bordered w-full"
                                placeholder="Search by code..."
                            />
                        </label>

                        <div class="flex gap-2">
                            <a href="{{ route('staff.reservations.index') }}" class="btn btn-ghost">Reset</a>
                            <button type="submit" class="btn btn-primary">Apply</button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="hidden sm:block">
                {{ $reservations->links() }}
            </div>

            <div class="overflow-x-auto rounded-xl bg-base-100 shadow">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Guest</th>
                            <th>Dates</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reservations as $reservation)
                            <tr>
                                <td class="font-semibold">{{ $reservation->code }}</td>
                                <td>{{ $reservation->customer->name ?? '—' }}</td>
                                <td>
                                    <div class="text-sm">
                                        <p>Check-in: {{ $reservation->check_in_date?->format('M d, Y') }}</p>
                                        <p>Check-out: {{ $reservation->check_out_date?->format('M d, Y') }}</p>
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $statusClass = match ($reservation->status) {
                                            'Confirmed' => 'badge-info',
                                            'CheckedIn' => 'badge-primary',
                                            'CheckedOut' => 'badge-success',
                                            'Cancelled' => 'badge-error',
                                            'NoShow' => 'badge-secondary',
                                            default => 'badge-outline',
                                        };
                                    @endphp
                                    <span class="badge {{ $statusClass }}">
                                        {{ $reservation->status }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('staff.reservations.show', ['reservation' => $reservation, 'filters' => $filters]) }}" class="btn btn-xs btn-ghost">
                                            View
                                        </a>
                                        <button
                                            type="button"
                                            class="btn btn-xs btn-outline btn-error hidden sm:inline-flex"
                                            data-modal-target="cancelModal"
                                            data-reservation-code="{{ $reservation->code }}"
                                            data-cancel-url="{{ route('staff.reservations.cancel', ['reservation' => $reservation, 'filters' => $filters]) }}"
                                            @php $statusLower = strtolower($reservation->status); @endphp
                                            @disabled(in_array($statusLower, ['cancelled', 'checkedout', 'checkedin']))
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-10 text-base-content/70">
                                    No reservations found for these filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="sm:hidden">
                {{ $reservations->links() }}
            </div>

        </div>
    </section>

    <dialog id="cancelModal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg">Cancel reservation</h3>
            <p class="py-4" id="cancelModalText">Are you sure you want to cancel this reservation? This action cannot be undone.</p>
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn btn-ghost">Keep active</button>
                </form>
                <form id="cancelForm" method="POST">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-error">Yes, cancel</button>
                </form>
            </div>
        </div>
    </dialog>

    <x-slot name="scripts">
        @vite('resources/js/staff/reservations-index.js')
    </x-slot>
</x-layouts.app.base>
