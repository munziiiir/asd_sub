<x-layouts.app.base :title="'Front Desk Hub'">
    @php
        $staffUser = auth('staff')->user();
        $isManager = $staffUser && $staffUser->role === 'manager';
    @endphp
    <x-slot name="header">
        <x-staff.header
            title="Front Desk Hub"
            titleColor="secondary"
        >
            <x-slot name="actions">
                <a href="{{ route('staff.dashboard') }}" class="btn btn-ghost hidden md:inline-flex">Back to staff home</a>
            </x-slot>
        </x-staff.header>
    </x-slot>

    <section class="bg-base-200 min-h-[calc(100vh-4rem)] md:min-h-[calc(100vh-5rem)] py-10 flex flex-col justify-center">
        <div class="mx-auto max-w-6xl px-4 w-full space-y-10">
            <div class="grid gap-6 md:grid-cols-2">
                <a href="{{ route('staff.reservations.index') }}" class="group relative block overflow-hidden w-full rounded-2xl border border-base-200 bg-base-100 p-6 md:p-8 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-primary/60 hover:bg-primary/5 hover:shadow-md focus:outline-none focus-visible:ring focus-visible:ring-primary min-h-[190px]">
                    <div class="flex h-full items-center justify-center md:justify-start gap-4 px-2 md:px-4 text-center md:text-left transition-all duration-300 group-hover:opacity-0 group-hover:-translate-y-4 group-hover:scale-95">
                        <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary transition-all duration-300 group-hover:scale-95">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 2h6a2 2 0 0 1 2 2v2h-2V4H9v2H7V4a2 2 0 0 1 2-2Zm-3 6h12a2 2 0 0 1 2 2v8a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3v-8a2 2 0 0 1 2-2Zm0 2v8a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-8H6Zm3 3h2v2H9v-2Zm4 0h2v2h-2v-2Z"/>
                            </svg>
                        </span>
                        <h2 class="text-3xl font-semibold transition-all duration-300">Manage Reservations</h2>
                    </div>
                    <div class="pointer-events-none absolute inset-0 flex items-center px-6 opacity-0 translate-y-6 transition-all duration-300 group-hover:opacity-100 group-hover:translate-y-0">
                        <div class="flex items-start gap-3 md:gap-4">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary transition-all duration-300 group-hover:-translate-y-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M9 2h6a2 2 0 0 1 2 2v2h-2V4H9v2H7V4a2 2 0 0 1 2-2Zm-3 6h12a2 2 0 0 1 2 2v8a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3v-8a2 2 0 0 1 2-2Zm0 2v8a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-8H6Zm3 3h2v2H9v-2Zm4 0h2v2h-2v-2Z"/>
                                </svg>
                            </span>
                            <div class="flex flex-col gap-1">
                                <h3 class="text-2xl font-semibold">Manage Reservations</h3>
                                <p class="text-sm md:text-base text-base-content/70 leading-snug">Create and manage bookings fast.</p>
                            </div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('staff.check-io.index') }}" class="group relative block overflow-hidden w-full rounded-2xl border border-base-200 bg-base-100 p-6 md:p-8 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-primary/60 hover:bg-primary/5 hover:shadow-md focus:outline-none focus-visible:ring focus-visible:ring-primary min-h-[190px]">
                    <div class="flex h-full items-center justify-center md:justify-start gap-4 px-2 md:px-4 text-center md:text-left transition-all duration-300 group-hover:opacity-0 group-hover:-translate-y-4 group-hover:scale-95">
                        <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary transition-all duration-300 group-hover:scale-95">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M5 4h14a2 2 0 0 1 2 2v6h-2V6H5v12h6v2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm12 12.5V15h2v1.5a1 1 0 0 1-1 1H14v1.5l-3-2.5 3-2.5V15h4Z"/>
                            </svg>
                        </span>
                        <h2 class="text-3xl font-semibold transition-all duration-300">Manage Check I/O</h2>
                    </div>
                    <div class="pointer-events-none absolute inset-0 flex items-center px-6 opacity-0 translate-y-6 transition-all duration-300 group-hover:opacity-100 group-hover:translate-y-0">
                        <div class="flex items-start gap-3 md:gap-4">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary transition-all duration-300 group-hover:-translate-y-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M5 4h14a2 2 0 0 1 2 2v6h-2V6H5v12h6v2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm12 12.5V15h2v1.5a1 1 0 0 1-1 1H14v1.5l-3-2.5 3-2.5V15h4Z"/>
                                </svg>
                            </span>
                            <div class="flex flex-col gap-1">
                                <h3 class="text-2xl font-semibold">Manage Check I/O</h3>
                                <p class="text-sm md:text-base text-base-content/70 leading-snug">Run arrivals and departures.</p>
                            </div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('staff.rooms.index') }}" class="group relative block overflow-hidden w-full rounded-2xl border border-base-200 bg-base-100 p-6 md:p-8 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-primary/60 hover:bg-primary/5 hover:shadow-md focus:outline-none focus-visible:ring focus-visible:ring-primary min-h-[190px]">
                    <div class="flex h-full items-center justify-center md:justify-start gap-4 px-2 md:px-4 text-center md:text-left transition-all duration-300 group-hover:opacity-0 group-hover:-translate-y-4 group-hover:scale-95">
                        <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary transition-all duration-300 group-hover:scale-95">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M4 5a2 2 0 0 1 2-2h2v2h8V3h2a2 2 0 0 1 2 2v14H4V5Zm2 2v10h2V7H6Zm4 0v10h4V7h-4Zm6 0v10h2V7h-2Z"/>
                            </svg>
                        </span>
                        <h2 class="text-3xl font-semibold transition-all duration-300">Manage Rooms</h2>
                    </div>
                    <div class="pointer-events-none absolute inset-0 flex items-center px-6 opacity-0 translate-y-6 transition-all duration-300 group-hover:opacity-100 group-hover:translate-y-0">
                        <div class="flex items-start gap-3 md:gap-4">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary transition-all duration-300 group-hover:-translate-y-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M4 5a2 2 0 0 1 2-2h2v2h8V3h2a2 2 0 0 1 2 2v14H4V5Zm2 2v10h2V7H6Zm4 0v10h4V7h-4Zm6 0v10h2V7h-2Z"/>
                                </svg>
                            </span>
                            <div class="flex flex-col gap-1">
                                <h3 class="text-2xl font-semibold">Manage Rooms</h3>
                                <p class="text-sm md:text-base text-base-content/70 leading-snug">View room status at a glance.</p>
                            </div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('staff.billing.index') }}" class="group relative block overflow-hidden w-full rounded-2xl border border-base-200 bg-base-100 p-6 md:p-8 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-primary/60 hover:bg-primary/5 hover:shadow-md focus:outline-none focus-visible:ring focus-visible:ring-primary min-h-[190px]">
                    <div class="flex h-full items-center justify-center md:justify-start gap-4 px-2 md:px-4 text-center md:text-left transition-all duration-300 group-hover:opacity-0 group-hover:-translate-y-4 group-hover:scale-95">
                        <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary transition-all duration-300 group-hover:scale-95">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M5 4h14a2 2 0 0 1 2 2v2H3V6a2 2 0 0 1 2-2Zm-2 6h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8Zm10 2v2h4v-2h-4Zm-6 0v2h4v-2H7Z"/>
                            </svg>
                        </span>
                        <h2 class="text-3xl font-semibold transition-all duration-300">Manage Folios</h2>
                    </div>
                    <div class="pointer-events-none absolute inset-0 flex items-center px-6 opacity-0 translate-y-6 transition-all duration-300 group-hover:opacity-100 group-hover:translate-y-0">
                        <div class="flex items-start gap-3 md:gap-4">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary transition-all duration-300 group-hover:-translate-y-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M5 4h14a2 2 0 0 1 2 2v2H3V6a2 2 0 0 1 2-2Zm-2 6h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8Zm10 2v2h4v-2h-4Zm-6 0v2h4v-2H7Z"/>
                                </svg>
                            </span>
                            <div class="flex flex-col gap-1">
                                <h3 class="text-2xl font-semibold">Manage Folios</h3>
                                <p class="text-sm md:text-base text-base-content/70 leading-snug">Review charges and payments.</p>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            @if ($isManager)
                <div class="flex items-center gap-3 text-sm text-base-content/60">
                    <div class="h-px flex-1 bg-base-300"></div>
                    <span class="uppercase tracking-wide text-xs font-semibold">Manager Controls</span>
                    <div class="h-px flex-1 bg-base-300"></div>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <a href="{{ route('staff.manager.frontdesk-staff.index') }}" class="group relative block overflow-hidden w-full rounded-2xl border border-base-200 bg-base-100 p-6 md:p-8 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-secondary/60 hover:bg-secondary/5 hover:shadow-md focus:outline-none focus-visible:ring focus-visible:ring-secondary min-h-[190px]">
                        <div class="flex h-full items-center justify-center md:justify-start gap-4 px-2 md:px-4 text-center md:text-left transition-all duration-300 group-hover:opacity-0 group-hover:-translate-y-4 group-hover:scale-95">
                            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-secondary/10 text-secondary transition-all duration-300 group-hover:scale-95">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm-9 8a7 7 0 0 1 14 0v1H3Z"/>
                                </svg>
                            </span>
                            <h2 class="text-2xl font-semibold transition-all duration-300">Staff Accounts</h2>
                        </div>
                        <div class="pointer-events-none absolute inset-0 flex items-center px-6 opacity-0 translate-y-6 transition-all duration-300 group-hover:opacity-100 group-hover:translate-y-0">
                            <div class="flex items-start gap-3 md:gap-4">
                                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary/10 text-secondary transition-all duration-300 group-hover:-translate-y-0.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm-9 8a7 7 0 0 1 14 0v1H3Z"/>
                                    </svg>
                                </span>
                                <div class="flex flex-col gap-1">
                                <h3 class="text-2xl font-semibold">Staff Accounts</h3>
                                <p class="text-sm md:text-base text-base-content/70 leading-snug">Manage front desk logins.</p>
                                </div>
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('staff.manager.room-types.index') }}" class="group relative block overflow-hidden w-full rounded-2xl border border-base-200 bg-base-100 p-6 md:p-8 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-secondary/60 hover:bg-secondary/5 hover:shadow-md focus:outline-none focus-visible:ring focus-visible:ring-secondary min-h-[190px]">
                        <div class="flex h-full items-center justify-center md:justify-start gap-4 px-2 md:px-4 text-center md:text-left transition-all duration-300 group-hover:opacity-0 group-hover:-translate-y-4 group-hover:scale-95">
                            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-secondary/10 text-secondary transition-all duration-300 group-hover:scale-95">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M4 4h16v2H4V4Zm0 5h16v2H4V9Zm0 5h16v2H4v-2Zm0 5h10v2H4v-2Z"/>
                                </svg>
                            </span>
                            <h2 class="text-2xl font-semibold transition-all duration-300">Rates &amp; Availability</h2>
                        </div>
                        <div class="pointer-events-none absolute inset-0 flex items-center px-6 opacity-0 translate-y-6 transition-all duration-300 group-hover:opacity-100 group-hover:translate-y-0">
                            <div class="flex items-start gap-3 md:gap-4">
                                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary/10 text-secondary transition-all duration-300 group-hover:-translate-y-0.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M4 4h16v2H4V4Zm0 5h16v2H4V9Zm0 5h16v2H4v-2Zm0 5h10v2H4v-2Z"/>
                                    </svg>
                                </span>
                                <div class="flex flex-col gap-1">
                                <h3 class="text-2xl font-semibold">Rates &amp; Availability</h3>
                                <p class="text-sm md:text-base text-base-content/70 leading-snug">Update room prices and seasons.</p>
                                </div>
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('staff.manager.reports.index') }}" class="group relative block overflow-hidden w-full rounded-2xl border border-base-200 bg-base-100 p-6 md:p-8 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-secondary/60 hover:bg-secondary/5 hover:shadow-md focus:outline-none focus-visible:ring focus-visible:ring-secondary min-h-[190px]">
                        <div class="flex h-full items-center justify-center md:justify-start gap-4 px-2 md:px-4 text-center md:text-left transition-all duration-300 group-hover:opacity-0 group-hover:-translate-y-4 group-hover:scale-95">
                            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-secondary/10 text-secondary transition-all duration-300 group-hover:scale-95">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M4 4h16v2H4V4Zm0 5h10v2H4V9Zm0 5h16v2H4v-2Zm0 5h10v2H4v-2Z"/>
                                </svg>
                            </span>
                            <h2 class="text-2xl font-semibold transition-all duration-300">Reports &amp; Analytics</h2>
                        </div>
                        <div class="pointer-events-none absolute inset-0 flex items-center px-6 opacity-0 translate-y-6 transition-all duration-300 group-hover:opacity-100 group-hover:translate-y-0">
                            <div class="flex items-start gap-3 md:gap-4">
                                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary/10 text-secondary transition-all duration-300 group-hover:-translate-y-0.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M4 4h16v2H4V4Zm0 5h10v2H4V9Zm0 5h16v2H4v-2Zm0 5h10v2H4v-2Z"/>
                                    </svg>
                                </span>
                                <div class="flex flex-col gap-1">
                                    <h3 class="text-2xl font-semibold">Reports &amp; Analytics</h3>
                                    <p class="text-sm md:text-base text-base-content/70 leading-snug">Occupancy, revenue, and booking patterns.</p>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endif
        </div>
    </section>
</x-layouts.app.base>
