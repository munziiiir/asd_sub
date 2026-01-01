<x-layouts.app.base :title="'Check-in & Check-out Hub'">
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
            <span>Check-in / Check-out</span>
        </div>
    </div>

    <section class="bg-base-200 py-6">
        <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4">
            <div>
                <h1 class="text-2xl font-bold">Daily arrivals & departures</h1>
                <p class="text-md text-base-content/70">
                    Review the queue, then launch the workflow you need.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div class="stat bg-base-100 shadow">
                    <div class="stat-title">Today's arrivals</div>
                    <div class="stat-value text-primary">{{ $stats['today_arrivals'] }}</div>
                    <div class="stat-desc text-base-content/70">Awaiting check-in</div>
                </div>
                <div class="stat bg-base-100 shadow">
                    <div class="stat-title">Today's departures</div>
                    <div class="stat-value text-success">{{ $stats['today_departures'] }}</div>
                    <div class="stat-desc text-base-content/70">Need check-out</div>
                </div>
                <div class="stat bg-base-100 shadow">
                    <div class="stat-title">In-house guests</div>
                    <div class="stat-value text-secondary">{{ $stats['in_house'] }}</div>
                    <div class="stat-desc text-base-content/70">Currently checked-in</div>
                </div>
                <div class="stat bg-base-100 shadow">
                    <div class="stat-title">Pending arrivals</div>
                    <div class="stat-value text-warning">{{ $stats['pending_arrivals'] }}</div>
                    <div class="stat-desc text-base-content/70">Future reservations</div>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title">Need to check guests in?</h2>
                        <p class="text-base-content/70">
                            Verify ID, assign rooms, and trigger the arrival events.
                        </p>
                        <a href="{{ route('staff.check-io.check-in') }}" class="btn btn-primary btn-sm self-start">
                            Open check-in flow
                        </a>
                    </div>
                </div>
                <div class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title">Need to check guests out?</h2>
                        <p class="text-base-content/70">
                            Review folios, capture extra services, and push the final payment to billing.
                        </p>
                        <a href="{{ route('staff.check-io.check-out') }}" class="btn btn-primary btn-sm self-start">
                            Open check-out flow
                        </a>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold">Upcoming arrivals</h3>
                                <p class="text-sm text-base-content/70">Pending & confirmed reservations.</p>
                            </div>
                            <span class="badge badge-outline">{{ count($arrivals) }}</span>
                        </div>
                        <ul class="space-y-3">
                            @forelse ($arrivals as $reservation)
                                <li class="rounded-lg border border-base-200 p-3">
                                    <p class="font-semibold">{{ $reservation->code }}</p>
                                    <p class="text-sm text-base-content/70">
                                        {{ $reservation->customer->name ?? 'Guest profile missing' }}
                                        · Arrives {{ optional($reservation->check_in_date)->format('M d, Y') }}
                                    </p>
                                    <p class="text-xs text-base-content/60">
                                        Rooms: {{ $reservation->roomNumberLabel() }}
                                    </p>
                                </li>
                            @empty
                                <li class="text-sm text-base-content/70">
                                    No pending arrivals. Enjoy the calm!
                                </li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold">In-house departures</h3>
                                <p class="text-sm text-base-content/70">Guests currently marked as checked-in.</p>
                            </div>
                            <span class="badge badge-outline">{{ count($departures) }}</span>
                        </div>
                        <ul class="space-y-3">
                            @forelse ($departures as $reservation)
                                @php $summary = $departureSummaries[$reservation->id] ?? null; @endphp
                                <li class="rounded-lg border border-base-200 p-3">
                                    <p class="font-semibold">{{ $reservation->code }}</p>
                                    <p class="text-sm text-base-content/70">
                                        {{ $reservation->customer->name ?? 'Guest profile missing' }}
                                        · Departs {{ optional($reservation->check_out_date)->format('M d, Y') }}
                                    </p>
                                    @if ($summary)
                                        <p class="text-xs text-base-content/60">
                                            {{ $summary['nights'] }} nights · £{{ number_format($summary['room_total'], 2) }} room total
                                        </p>
                                    @endif
                                    <p class="text-xs text-base-content/60">
                                        Rooms: {{ $reservation->roomNumberLabel() }}
                                    </p>
                                </li>
                            @empty
                                <li class="text-sm text-base-content/70">
                                    No active departures right now.
                                </li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body grid gap-6 md:grid-cols-2">
                    <div>
                        <h3 class="text-lg font-semibold">Recent check-ins</h3>
                        <ul class="space-y-3">
                            @forelse ($recentCheckIns as $checkIn)
                                <li class="rounded-lg border border-base-200 p-3">
                                    <p class="font-semibold">{{ $checkIn->reservation->code }}</p>
                                    <p class="text-sm text-base-content/70">
                                        {{ $checkIn->reservation->customer->name ?? 'Guest' }}
                                        · {{ optional($checkIn->checked_in_at)->format('M d, H:i') }}
                                    </p>
                                </li>
                            @empty
                                <li class="text-sm text-base-content/70">No check-ins logged yet.</li>
                            @endforelse
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold">Recent check-outs</h3>
                        <ul class="space-y-3">
                            @forelse ($recentCheckOuts as $checkOut)
                                <li class="rounded-lg border border-base-200 p-3">
                                    <p class="font-semibold">{{ $checkOut->reservation->code }}</p>
                                    <p class="text-sm text-base-content/70">
                                        {{ $checkOut->reservation->customer->name ?? 'Guest' }}
                                        · {{ optional($checkOut->checked_out_at)->format('M d, H:i') }}
                                    </p>
                                </li>
                            @empty
                                <li class="text-sm text-base-content/70">No check-outs logged yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app.base>
