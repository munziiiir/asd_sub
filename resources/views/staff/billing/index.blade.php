<x-layouts.app.base :title="'Payments & Billing'">
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
            <span>Billing</span>
        </div>
    </div>

    <section class="bg-base-200 py-6">
        <div class="mx-auto max-w-6xl px-4 space-y-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Payments & billing</h1>
                    <p class="text-sm text-base-content/70">Collect deposits, post extras, and close folios.</p>
                </div>
                <a href="{{ route('staff.check-io.check-out') }}" class="btn btn-primary btn-sm md:btn-md">
                    Go to check-out
                </a>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="card-title">Folio overview</h2>
                            <p class="text-sm text-base-content/70">Recent checked-in/out stays with room and extras totals.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Resv</th>
                                    <th>Guest</th>
                                    <th>Rooms</th>
                                    <th>Stay</th>
                                    <th>Status</th>
                                    <th class="text-right">Room</th>
                                    <th class="text-right">Extras</th>
                                    <th class="text-right">Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($reservations as $res)
                                    <tr>
                                        <td class="font-semibold">{{ $res['code'] }}</td>
                                        <td>{{ $res['guest'] }}</td>
                                        <td>{{ $res['room'] ?? '—' }}</td>
                                        <td class="text-sm text-base-content/70">
                                            {{ $res['check_in'] }} → {{ $res['check_out'] }}
                                        </td>
                                        <td>
                                            <span class="badge {{ $res['folio_status'] === 'closed' ? 'badge-success' : 'badge-outline' }}">
                                                {{ ucfirst($res['folio_status']) }}
                                            </span>
                                        </td>
                                        <td class="text-right">£{{ number_format($res['room_total'], 2) }}</td>
                                        <td class="text-right">£{{ number_format($res['extras_total'], 2) }}</td>
                                        <td class="text-right font-semibold">£{{ number_format($res['grand_total'], 2) }}</td>
                                        <td class="text-right">
                                            <div class="flex gap-2 justify-end">
                                                <a href="{{ route('staff.reservations.show', $res['id']) }}" class="btn btn-ghost btn-xs">
                                                    View
                                                </a>
                                                <a href="{{ route('staff.billing.show', $res['id']) }}" class="btn btn-primary btn-xs">
                                                    Manage folio
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-base-content/70 py-6">
                                            No stays yet. Process a check-in to begin billing.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body space-y-2">
                    <h3 class="card-title">How it works</h3>
                    <ul class="list-disc pl-5 text-sm text-base-content/80 space-y-1">
                        <li>Extras added during check-out appear here as “Extras”.</li>
                        <li>Room totals are based on nightly rate × nights.</li>
                        <li>Use “Add charges” to jump into the check-out flow and attach more items.</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app.base>
