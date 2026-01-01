<x-layouts.app.base :title="'Rooms & Housekeeping'">
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
        <div class="mx-auto flex max-w-5xl items-center gap-2 px-4 text-sm text-base-content/70 flex-wrap">
            <a href="{{ route('staff.frontdesk') }}" class="link text-secondary font-semibold">Front Desk Hub</a>
            <span class="text-base-content/50">→</span>
            <span>Rooms</span>
        </div>
    </div>

    <section class="bg-base-200 py-6">
        <div class="mx-auto max-w-5xl px-4 space-y-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Rooms</h1>
                    <p class="text-sm text-base-content/70">Status and housekeeping overview</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg bg-base-100 shadow">
                <div class="p-4">
                    <h2 class="text-lg font-semibold">All rooms</h2>
                    <p class="text-sm text-base-content/70">View status and jump into room details.</p>
                </div>
                <div class="divide-y divide-base-200">
                    @forelse ($rooms as $room)
                        <a href="{{ route('staff.rooms.show', $room) }}" class="flex items-center justify-between px-4 py-3 hover:bg-base-200">
                            <div>
                                <p class="font-semibold">Room {{ $room->number }}</p>
                                <p class="text-sm text-base-content/70">{{ $room->roomType?->name ?? 'Room' }}</p>
                            </div>
                            <span class="badge badge-outline">{{ $room->status ?? 'Unknown' }}</span>
                        </a>
                    @empty
                        <div class="px-4 py-6 text-sm text-base-content/70">
                            No rooms found for this hotel.
                        </div>
                    @endforelse
                </div>

                @if ($rooms instanceof \Illuminate\Pagination\AbstractPaginator)
                    <div class="p-4">
                        {{ $rooms->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>
</x-layouts.app.base>
