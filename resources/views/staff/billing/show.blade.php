<x-layouts.app.base :title="'Folio for '.$reservation->code">
    <x-slot name="header">
        <x-staff.header
            title="Folio {{ $folio->folio_no ?? $reservation->code }}"
            titleColor="accent"
            description="Manage charges before check-out."
        >
            <x-slot name="actions">
                <a href="{{ route('staff.billing.index') }}" class="btn btn-ghost btn-sm md:btn-md">
                    &larr; Back to billing
                </a>
                <a href="{{ route('staff.reservations.show', $reservation) }}" class="btn btn-ghost btn-sm md:btn-md">
                    Reservation
                </a>
            </x-slot>
        </x-staff.header>
    </x-slot>

    <section class="bg-base-200 py-8">
        <div class="mx-auto max-w-5xl px-4 space-y-6">
            <div class="card bg-base-100 shadow">
                <div class="card-body space-y-2">
                    <h2 class="card-title">Stay summary</h2>
                    <p class="text-sm text-base-content/70">
                        {{ $reservation->code }} — {{ $reservation->customer?->name ?? 'Guest' }} · Rooms {{ $reservation->roomNumberLabel() }}
                    </p>
                    <p class="text-sm text-base-content/70">
                        {{ optional($reservation->check_in_date)->toDateString() }} to {{ optional($reservation->check_out_date)->toDateString() }}
                    </p>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="card-title">Charges</h3>
                            <p class="text-sm text-base-content/70">Post incidentals or adjustments before check-out.</p>
                        </div>
                        <span class="badge {{ $folio->status === 'Closed' ? 'badge-success' : 'badge-outline' }}">{{ $folio->status ?? 'Open' }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($folio->charges as $charge)
                                    <tr>
                                        <td>{{ optional($charge->post_date)->toDateString() }}</td>
                                        <td>
                                            <div class="space-y-1">
                                                <div>{{ $charge->description }}</div>
                                                <div class="text-xs text-base-content/60">Qty {{ $charge->qty ?? 1 }} @ £{{ number_format($charge->unit_price, 2) }}</div>
                                            </div>
                                        </td>
                                        <td class="text-right">£{{ number_format($charge->total_amount, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-base-content/70 py-4">No charges yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($folio->status !== 'Closed')
                        <div x-data="{ open: false, code: '{{ old('charge_code', 'airport_transfer') }}' }" class="space-y-3">
                            <div class="flex justify-end">
                                <button class="btn btn-primary btn-sm" @click.prevent="open = !open">
                                    <span x-text="open ? 'Cancel' : 'New charge'"></span>
                                </button>
                            </div>
                            <form
                                x-show="open"
                                x-transition
                                method="POST"
                                action="{{ route('staff.billing.charges.store', $reservation) }}"
                                class="space-y-3"
                            >
                                @csrf
                                <div class="flex flex-col gap-1">
                                    <label class="label">
                                        <span class="label-text font-semibold">Description</span>
                                    </label>
                                    <select name="charge_code" class="select select-bordered w-full" x-model="code">
                                        @foreach ($chargeOptions as $option)
                                            @php
                                                $blocked = $blockedCharges[$option['code']] ?? false;
                                            @endphp
                                            <option value="{{ $option['code'] }}" @disabled($blocked)>
                                                {{ $option['label'] }}{{ $option['amount'] > 0 ? ' (£' . number_format($option['amount'], 2) . ')' : '' }}
                                                @if($option['code'] !== 'custom')
                                                    — applied to {{ max(1, $reservation->adults + $reservation->children) }} guest{{ ($reservation->adults + $reservation->children) === 1 ? '' : 's' }}
                                                    @if($blocked) (already on folio) @endif
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('charge_code')
                                        <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div x-show="code === 'custom'" x-transition>
                                    <div class="flex flex-col gap-1">
                                        <label class="label">
                                            <span class="label-text font-semibold">Name</span>
                                        </label>
                                        <input type="text" name="custom_name" class="input input-bordered w-full" value="{{ old('custom_name') }}">
                                        @error('custom_name')
                                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="label">
                                            <span class="label-text font-semibold">Amount</span>
                                        </label>
                                        <input type="number" step="0.01" min="0" name="custom_amount" class="input input-bordered w-full" value="{{ old('custom_amount') }}">
                                        @error('custom_amount')
                                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" class="btn btn-primary">Add charge</button>
                                </div>
                                <p class="text-xs text-base-content/60 text-right">Standard charges auto-apply to all guests and cannot be added twice (including online selections).</p>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-layouts.app.base>
