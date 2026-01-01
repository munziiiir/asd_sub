<x-layouts.app.base :title="'Locations'">
    <section class="bg-base-200 min-h-[calc(100vh-4rem)] px-6 py-12">
        <div class="max-w-6xl mx-auto space-y-8">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <p class="text-sm uppercase tracking-widest text-primary font-semibold">Footprint</p>
                    <h1 class="text-3xl md:text-4xl font-bold text-base-content">All Hotel Locations</h1>
                    <p class="text-base-content/70 mt-2 max-w-3xl">Browse every country where we currently operate hotels.</p>
                </div>
            </div>

            @php
                $locationList = collect($locations ?? [])->values();
            @endphp

            @if ($locationList->count())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 auto-rows-fr">
                    @foreach ($locationList as $city)
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
                </div>
            @else
                <p class="text-base-content/70">No hotel locations found yet.</p>
            @endif
        </div>
    </section>
</x-layouts.app.base>
