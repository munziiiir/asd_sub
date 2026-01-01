<x-layouts.app.base :title="'Room Types & Rates'">
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
            <span>Manager</span>
            <span class="text-base-content/50">→</span>
            <span>Rates & availability</span>
        </div>
    </div>

    <section class="bg-base-200 py-6">
        <div class="mx-auto max-w-6xl px-4 space-y-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Rates & availability</h1>
                    <p class="text-sm text-base-content/70">Manage room types, capacity, and peak vs off-peak pricing for {{ $hotelName ?? 'your hotel' }}.</p>
                </div>
                <a href="{{ route('staff.manager.room-types.create') }}" class="btn btn-primary btn-sm md:btn-md">
                    Add room type
                </a>
            </div>

            <form method="GET" action="{{ route('staff.manager.room-types.index') }}" class="card bg-base-100 shadow overflow-x-auto">
                <div class="card-body">
                    <div class="flex flex-nowrap items-end gap-4 min-w-[640px]">
                        <label class="flex flex-col gap-1 flex-1 min-w-[320px]">
                            <span class="label-text font-semibold">Search</span>
                            <input
                                id="search"
                                type="search"
                                name="search"
                                value="{{ $filters['search'] ?? '' }}"
                                class="input input-bordered w-full"
                                placeholder="Search by room type name"
                            >
                        </label>
                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm md:btn-md">Apply</button>
                            @if (($filters['search'] ?? '') !== '')
                                <a href="{{ route('staff.manager.room-types.index') }}" class="btn btn-ghost btn-sm md:btn-md">Reset</a>
                            @endif
                        </div>
                    </div>
                </div>
            </form>

            <div class="card bg-base-100 shadow">
                <div class="card-body p-0">
                    <div class="overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th>Room type</th>
                                    <th>Capacity</th>
                                    <th>Off-peak</th>
                                    <th>Peak</th>
                                    <th>Active rate</th>
                                    <th>Rooms</th>
                                    <th class="text-right"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($roomTypes as $roomType)
                                    <tr>
                                        <td>
                                            <div class="font-semibold">{{ $roomType->name }}</div>
                                            <div class="text-xs text-base-content/70">Base occupancy: {{ $roomType->base_occupancy }}</div>
                                        </td>
                                        <td class="text-sm">
                                            <div>{{ $roomType->max_adults }} adults</div>
                                            <div class="text-base-content/70">{{ $roomType->max_children }} children</div>
                                        </td>
                                        <td>£{{ number_format((float) $roomType->price_off_peak, 2) }}</td>
                                        <td>£{{ number_format((float) $roomType->price_peak, 2) }}</td>
                                        <td>
                                            @php $activeRate = $roomType->active_rate === 'peak' ? 'peak' : 'off_peak'; @endphp
                                            <div class="badge badge-sm {{ $activeRate === 'peak' ? 'badge-secondary' : 'badge-primary' }}">
                                                {{ $activeRate === 'peak' ? 'Peak' : 'Off-peak' }} active
                                            </div>
                                            <div class="text-xs text-base-content/70">£{{ number_format($roomType->activeRate(), 2) }} per night</div>
                                        </td>
                                        <td class="text-sm text-base-content/70">{{ $roomType->rooms_count ?? 0 }}</td>
                                        <td class="text-right">
                                            <a href="{{ route('staff.manager.room-types.edit', $roomType) }}" class="btn btn-xs">Edit</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-6 text-center text-base-content/70">No room types yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($roomTypes instanceof \Illuminate\Pagination\AbstractPaginator)
                        <div class="p-4">
                            {{ $roomTypes->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-layouts.app.base>
