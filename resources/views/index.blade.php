<x-layouts.app.base>

<div class="hero min-h-[calc(100vh-4rem)]" style="background-image: url(https://images.unsplash.com/photo-1566073771259-6a8506099945?q=80&w=2070&auto=format&fit=crop);">
    <div class="hero-overlay bg-opacity-60"></div>
    <div class="hero-content text-center text-neutral-content">
        <div class="max-w-md">
            <h1 class="mb-5 text-5xl font-bold">Effortless Hotel Management</h1>
            <p class="mb-5">Streamline your operations, delight your guests. Our all-in-one solution simplifies bookings, check-ins, and everything in between.</p>
            <a href="{{ route('booking.start') }}" class="btn btn-white btn-lg px-8 shadow-xl shadow-primary/30 hover:shadow-2xl hover:shadow-primary/40">Book Now</a>
        </div>
    </div>
</div>

@php
    $roomTypes = [
        [
            'name' => 'Standard Double',
            'capacity' => 2,
            'offPeak' => 120,
            'peak' => 180,
            'icon' => 'bed',
            'gradient' => ['from' => 'from-sky-100', 'to' => 'to-indigo-100', 'text' => 'text-indigo-600'],
            'policy' => 'Adults only · max 2',
            'allowsChildren' => false,
        ],
        [
            'name' => 'Deluxe King',
            'capacity' => 2,
            'offPeak' => 180,
            'peak' => 250,
            'icon' => 'crown',
            'gradient' => ['from' => 'from-amber-100', 'to' => 'to-pink-100', 'text' => 'text-amber-600'],
            'policy' => 'Adults only · max 2',
            'allowsChildren' => false,
        ],
        [
            'name' => 'Family Suite',
            'capacity' => 4,
            'offPeak' => 240,
            'peak' => 320,
            'icon' => 'people',
            'gradient' => ['from' => 'from-emerald-100', 'to' => 'to-teal-100', 'text' => 'text-emerald-600'],
            'policy' => 'Children welcome · up to 2 kids',
            'allowsChildren' => true,
        ],
        [
            'name' => 'Penthouse',
            'capacity' => 4,
            'offPeak' => 500,
            'peak' => 750,
            'icon' => 'building',
            'gradient' => ['from' => 'from-purple-100', 'to' => 'to-blue-100', 'text' => 'text-purple-600'],
            'policy' => 'Children welcome · up to 2 kids',
            'allowsChildren' => true,
        ],
    ];

    $locationList = collect($locations ?? [])->values();
@endphp

<section id="room-types" class="bg-base-100 px-6 py-16 lg:min-h-[calc(100vh-4rem)] scroll-mt-24">
    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between gap-4 flex-wrap mb-8">
            <div>
                <p class="text-sm uppercase tracking-widest text-primary font-semibold">Rooms</p>
                <h2 class="text-3xl font-bold text-base-content">Room Types</h2>
                <p class="text-base-content/70 mt-2 max-w-2xl">At-a-glance pricing and capacity for every room we manage. Guests can quickly pick the stay that fits their trip.</p>
            </div>
            <div class="bg-gradient-to-r from-primary/10 to-secondary/10 px-4 py-3 rounded-xl text-sm text-base-content/80">
                <span class="font-semibold text-primary">Live rates</span> · Off-peak vs peak season in GBP
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach ($roomTypes as $room)
                <div class="card bg-base-200 shadow-lg border border-base-300/60">
                    <div class="card-body gap-4">
                        <div class="flex items-start gap-4">
                            <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br {{ $room['gradient']['from'] }} {{ $room['gradient']['to'] }} {{ $room['gradient']['text'] }}">
                                @if ($room['icon'] === 'bed')
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-7 w-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="10" width="18" height="5" rx="1.25"></rect>
                                        <path d="M7 10V8.5a2.5 2.5 0 0 1 2.5-2.5H12"></path>
                                        <path d="M3 15v3"></path>
                                        <path d="M21 15v3"></path>
                                    </svg>
                                @elseif ($room['icon'] === 'crown')
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-7 w-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 17.5h16"></path>
                                        <path d="M5 17.5 7 9l5 4 5-4 2 8.5"></path>
                                        <circle cx="7" cy="7" r="1"></circle>
                                        <circle cx="12" cy="8.5" r="1"></circle>
                                        <circle cx="17" cy="7" r="1"></circle>
                                    </svg>
                                @elseif ($room['icon'] === 'people')
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-7 w-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="9" cy="8" r="2.5"></circle>
                                        <path d="M4.5 17.5c.3-2.8 2.2-4.5 4.5-4.5s4.2 1.7 4.5 4.5"></path>
                                        <circle cx="16" cy="9" r="2"></circle>
                                        <path d="M13.5 15.5c.6-1.7 1.9-2.8 3.5-2.8 1.6 0 2.8 1.1 3.5 2.8"></path>
                                    </svg>
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-7 w-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="5" y="5" width="6" height="14" rx="1"></rect>
                                        <rect x="13" y="8" width="6" height="11" rx="1"></rect>
                                        <path d="M8 9h1.5"></path>
                                        <path d="M8 12h1.5"></path>
                                        <path d="M16 11h1.5"></path>
                                        <path d="M16 14h1.5"></path>
                                    </svg>
                                @endif
                            </span>
                            <div class="flex-1">
                                <div class="flex items-center justify-between gap-3 flex-wrap">
                                    <h3 class="text-xl font-semibold text-base-content">{{ $room['name'] }}</h3>
                                    <div class="flex items-center gap-2">
                                        <span class="badge badge-primary badge-outline">Sleeps {{ $room['capacity'] }}</span>
                                        @if ($room['allowsChildren'])
                                            <span class="badge badge-success badge-outline">Kids (up to 2)</span>
                                        @else
                                            <span class="badge badge-ghost border-base-300 text-sm text-base-content/80">Adults only</span>
                                        @endif
                                    </div>
                                </div>
                                <p class="text-base-content/70 mt-1">{{ $room['policy'] }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 pt-2 text-sm">
                            <div class="flex items-center justify-between bg-base-100 px-3 py-2 rounded-lg border border-base-300/60">
                                <span class="text-base-content/70">Off-peak</span>
                                <span class="font-semibold text-base-content">£{{ $room['offPeak'] }}</span>
                            </div>
                            <div class="flex items-center justify-between bg-base-100 px-3 py-2 rounded-lg border border-base-300/60">
                                <span class="text-base-content/70">Peak</span>
                                <span class="font-semibold text-base-content">£{{ $room['peak'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section id="locations" class="bg-base-200 px-6 py-16 lg:min-h-[calc(100vh-4rem)] scroll-mt-24">
    <div class="max-w-6xl mx-auto space-y-8">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <p class="text-sm uppercase tracking-widest text-primary font-semibold">Footprint</p>
                <h2 class="text-3xl font-bold text-base-content">Hotel Locations</h2>
                <p class="text-base-content/70 mt-2 max-w-2xl">Your current network of properties. We highlight a curated set here and let guests explore every location with one click.</p>
            </div>
        </div>

        @if ($locationList->count())
            @php
                $variants = [
                    ['class' => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 auto-rows-fr block sm:hidden', 'threshold' => 4],
                    ['class' => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 auto-rows-fr hidden sm:grid lg:hidden', 'threshold' => 6],
                    ['class' => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 auto-rows-fr hidden lg:grid xl:hidden', 'threshold' => 9],
                    ['class' => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 auto-rows-fr hidden xl:grid', 'threshold' => 12],
                ];
            @endphp
            @foreach ($variants as $variant)
                @php
                    $threshold = $variant['threshold'];
                    $hasMore = $locationList->count() > $threshold;
                    $slice = $hasMore ? $locationList->take($threshold - 1) : $locationList->take($threshold);
                @endphp
                <div class="{{ $variant['class'] }}">
                    @foreach ($slice as $city)
                        <div class="rounded-2xl border border-base-300/60 bg-base-100 p-4 shadow-sm flex items-center gap-3">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-secondary/15 to-primary/15 text-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-6 w-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 3.5c-3.6 0-6.5 2.8-6.5 6.2 0 3.6 3.3 7.3 6.5 10.8 3.2-3.5 6.5-7.2 6.5-10.8 0-3.4-2.9-6.2-6.5-6.2z"></path>
                                    <circle cx="12" cy="9.5" r="2"></circle>
                                </svg>
                            </span>
                            <div>
                                <p class="text-base-content font-semibold">{{ $city }}</p>
                                <p class="text-xs text-base-content/70">Active bookings & staffing</p>
                            </div>
                        </div>
                    @endforeach
                    @if ($hasMore)
                        <a href="/locations" class="rounded-2xl border border-primary/40 bg-base-200 p-4 shadow-md flex items-center gap-3 hover:border-primary transition">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-primary/20 text-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-6 w-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 5l7 7-7 7" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-base-content font-semibold">View all locations</p>
                                <p class="text-xs text-base-content/70">Open full list</p>
                            </div>
                        </a>
                    @endif
                </div>
            @endforeach
        @else
            <p class="text-base-content/70">No hotel locations found yet. Add hotels to see them listed here.</p>
        @endif
    </div>
</section>

</x-layouts.app.base>
