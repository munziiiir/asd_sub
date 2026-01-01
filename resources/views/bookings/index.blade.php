<x-layouts.app.base :title="'My Bookings'">
    <section class="bg-base-200 min-h-[calc(100vh-4rem)] px-6 py-12">
        <div class="max-w-5xl mx-auto space-y-6">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <p class="text-sm uppercase tracking-widest text-primary font-semibold">Bookings</p>
                    <h1 class="text-3xl md:text-4xl font-bold text-base-content">Your reservations</h1>
                    <p class="text-base-content/70 mt-2">Current and past bookings, sorted by your most recent updates.</p>
                </div>
                <form method="get" class="flex items-center gap-2">
                    <label class="text-sm text-base-content/70">Sort</label>
                    <select name="sort" class="select select-bordered select-sm" onchange="this.form.submit()">
                        <option value="updated_desc" @selected($sort === 'updated_desc')>Most recently updated</option>
                        <option value="updated_asc" @selected($sort === 'updated_asc')>Least recently updated</option>
                        <option value="status" @selected($sort === 'status')>Status</option>
                        <option value="checkin" @selected($sort === 'checkin')>Check-in date</option>
                    </select>
                </form>
            </div>

            @if ($reservations->count())
                <div class="space-y-3">
                    @foreach ($reservations as $reservation)
                        <a href="{{ route('bookings.show', $reservation) }}" class="block rounded-2xl border border-base-300/60 bg-base-100 p-4 shadow-sm hover:border-primary transition">
                            <div class="flex items-center justify-between gap-3 flex-wrap">
                                <div>
                                    <p class="text-sm text-base-content/70">{{ $reservation->code }}</p>
                                    <p class="text-lg font-semibold text-base-content">{{ $reservation->hotel?->name ?? 'Hotel' }}</p>
                                </div>
                                <span class="badge badge-outline">{{ $reservation->status }}</span>
                            </div>
                            @php
                                $roomsLabel = $reservation->reservationRooms->map(fn ($rr) => $rr->room?->number)->filter()->implode(', ');
                            @endphp
                            <div class="mt-2 text-sm text-base-content/80 flex flex-wrap gap-3">
                                <span>{{ optional($reservation->check_in_date)->toDateString() }} → {{ optional($reservation->check_out_date)->toDateString() }}</span>
                                <span>· {{ $reservation->adults }} adult{{ $reservation->adults > 1 ? 's' : '' }}</span>
                                @if ($reservation->children > 0)
                                    <span>· {{ $reservation->children }} child{{ $reservation->children > 1 ? 'ren' : '' }}</span>
                                @endif
                                <span>· Rooms: {{ $roomsLabel ?: 'TBD' }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="pt-4">
                    {{ $reservations->links() }}
                </div>
            @else
                <p class="text-base-content/70">No bookings yet.</p>
            @endif
        </div>
    </section>
</x-layouts.app.base>
