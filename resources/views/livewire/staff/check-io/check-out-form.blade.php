<div class="card bg-base-100 shadow">
    <form class="card-body space-y-6" wire:submit.prevent="save">
        <div>
            <h2 class="card-title">Check a guest out</h2>
            <p class="text-sm text-base-content/70">
                Review folio totals, add last-minute services, and settle the folio before releasing rooms.
            </p>
        </div>

        <label class="flex flex-col gap-1 w-full">
            <span class="label-text font-semibold">Reservation</span>
            <select class="select select-bordered" wire:model.live="reservationId" required>
                <option value="">Select checked-in guest…</option>
                @foreach ($departureOptions as $option)
                    <option value="{{ $option['id'] }}">
                        {{ $option['code'] }} — {{ $option['guest'] }}
                        (Departs {{ $option['check_out'] }})
                    </option>
                @endforeach
            </select>
            @if (empty($departureOptions))
                <span class="text-sm text-base-content/70">
                    No in-house guests are currently ready to depart.
                </span>
            @endif
            @error('reservationId')
                <span class="text-sm text-error">{{ $message }}</span>
            @enderror
        </label>

        @php
            $selectedDeparture = collect($departureOptions)->firstWhere('id', $reservationId);
        @endphp
        @if ($selectedDeparture)
            <div class="rounded-lg border border-base-200 bg-base-100 p-4 space-y-4">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-base-content/80">Stay summary</p>
                        <p class="text-xs text-base-content/60">Guest nights, rooms, and folio balances.</p>
                    </div>
                    <div class="text-xs text-base-content/60">
                        Folio status:
                        <span class="font-semibold text-base-content">
                            {{ data_get($selectedSummary, 'folio.status', '—') }}
                        </span>
                    </div>
                </div>
                <div class="grid gap-3 text-sm md:grid-cols-4">
                    <div>
                        <p class="text-base-content/60">Nights</p>
                        <p class="font-semibold">{{ $selectedSummary['nights'] ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-base-content/60">Nightly rate (total)</p>
                        <p class="font-semibold">
                            {{ isset($selectedSummary['nightly_rate']) ? '£'.number_format($selectedSummary['nightly_rate'], 2) : '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-base-content/60">Room total</p>
                        <p class="font-semibold">
                            {{ isset($selectedSummary['room_total']) ? '£'.number_format($selectedSummary['room_total'], 2) : '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-base-content/60">Assigned room(s)</p>
                        <p class="font-semibold">
                            {{ $selectedDeparture['room'] ?? 'Room assignment pending' }}
                        </p>
                    </div>
                </div>
                <div class="grid gap-3 text-sm md:grid-cols-2">
                    <div>
                        <p class="text-base-content/60">Room charges (folio)</p>
                        <p class="font-semibold">
                            {{ isset($selectedSummary['folio']['charges_total']) ? '£'.number_format($selectedSummary['folio']['charges_total'], 2) : 'Awaiting folio' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-base-content/60">Payments received</p>
                        <p class="font-semibold">
                            {{ isset($selectedSummary['folio']['payments_total']) ? '£'.number_format($selectedSummary['folio']['payments_total'], 2) : 'Awaiting folio' }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        @if ($reservationId)
        <div class="space-y-3 rounded-lg border border-base-200 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-semibold">Additional services</p>
                    <p class="text-sm text-base-content/70">Late fees, spa passes, damages. Preset add-ons already on the folio (including online bookings) are disabled.</p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" wire:click="addExtra">
                    + Add service
                </button>
            </div>

            @php
                $optionsByCode = collect($chargeOptions)->keyBy('code');
                $selectedCodes = collect($extras)->pluck('code')->filter()->values()->all();
                $guestCount = max(1, (int) data_get($selectedSummary, 'guest_count', 1));
            @endphp
            @foreach ($extras as $index => $extra)
                <div class="grid gap-3 md:grid-cols-2" wire:key="extra-row-{{ $index }}">
                    <label class="flex flex-col gap-1 w-full">
                        <span class="label-text font-semibold">Service</span>
                        <select class="select select-bordered" wire:model.live="extras.{{ $index }}.code">
                            <option value="">Select add-on…</option>
                            @foreach ($chargeOptions as $option)
                                @php
                                    $code = $option['code'];
                                    $blocked = $blockedChargeCodes[$code] ?? false;
                                    $alreadySelected = $code !== 'custom' && in_array($code, $selectedCodes, true) && ($extra['code'] ?? null) !== $code;
                                @endphp
                                <option value="{{ $code }}" @disabled($blocked || $alreadySelected)>
                                    {{ $option['label'] }}{{ $option['amount'] > 0 ? ' (£' . number_format($option['amount'], 2) . ')' : '' }}
                                    @if ($blocked) — on folio @endif
                                </option>
                            @endforeach
                        </select>
                        @error("extras.$index.code")
                            <span class="text-sm text-error">{{ $message }}</span>
                        @enderror
                        @if (($blockedChargeCodes[$extra['code'] ?? ''] ?? false) && ($extra['code'] ?? '') !== 'custom')
                            <span class="text-xs text-warning">This add-on is already on the folio from an online booking or prior staff entry.</span>
                        @endif

                        @if (($extra['code'] ?? '') === 'custom')
                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="flex flex-col gap-1 w-full">
                                    <span class="label-text font-semibold">Custom name</span>
                                    <input
                                        type="text"
                                        class="input input-bordered"
                                        wire:model="extras.{{ $index }}.custom_name"
                                        placeholder="Describe the charge"
                                    >
                                    @error("extras.$index.custom_name")
                                        <span class="text-sm text-error">{{ $message }}</span>
                                    @enderror
                                </label>
                                <label class="flex flex-col gap-1 w-full">
                                    <span class="label-text font-semibold">Amount (GBP)</span>
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="input input-bordered"
                                        wire:model.live.number="extras.{{ $index }}.custom_amount"
                                        placeholder="0.00"
                                    >
                                    @error("extras.$index.custom_amount")
                                        <span class="text-sm text-error">{{ $message }}</span>
                                    @enderror
                                </label>
                            </div>
                        @endif
                    </label>

                    <div class="flex gap-2">
                        <div class="flex flex-col gap-1 w-full">
                            <span class="label-text font-semibold">Amount</span>
                            @php
                                $code = $extra['code'] ?? '';
                                $option = $code ? ($optionsByCode[$code] ?? null) : null;
                                $unit = $option['amount'] ?? null;
                                $total = $option ? round(($unit ?? 0) * $guestCount, 2) : null;
                            @endphp
                            @if ($code === 'custom')
                                <p class="text-sm text-base-content/70">Enter amount above for custom charges.</p>
                            @elseif ($option)
                                <div class="rounded-lg border border-base-200 bg-base-100 p-3">
                                    <p class="font-semibold">£{{ number_format($total, 2) }}</p>
                                    <p class="text-xs text-base-content/60">£{{ number_format($unit, 2) }} × {{ $guestCount }} guest{{ $guestCount === 1 ? '' : 's' }}</p>
                                </div>
                            @else
                                <p class="text-sm text-base-content/70">Select an add-on to see the total.</p>
                            @endif
                        </div>
                        <button
                            type="button"
                            class="btn btn-ghost btn-square mt-8"
                            wire:click="removeExtra({{ $index }})"
                            aria-label="Remove service"
                        >
                            &times;
                        </button>
                    </div>
                </div>
            @endforeach

            <div class="text-sm text-base-content/80">
                New charges to post: <span class="font-semibold">£{{ number_format($extrasTotal, 2) }}</span>
            </div>
        </div>
        @endif

        <div class="space-y-3 rounded-lg border border-base-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold">Folio balance</p>
                    <p class="text-sm text-base-content/70">Charges + new services vs payments already collected.</p>
                </div>
            </div>

            @if ($balanceSummary)
                <dl class="grid gap-3 md:grid-cols-2 text-sm">
                    <div>
                        <dt class="text-base-content/60">Charges on folio</dt>
                        <dd class="font-semibold">£{{ number_format($balanceSummary['charges'], 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-base-content/60">Payments received</dt>
                        <dd class="font-semibold">£{{ number_format($balanceSummary['payments'], 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-base-content/60">New charges to post</dt>
                        <dd class="font-semibold">£{{ number_format($balanceSummary['planned_charges'], 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-base-content/60">Remaining to settle</dt>
                        <dd class="font-semibold {{ $balanceSummary['overpaid'] ? 'text-warning' : ($balanceSummary['needs_payment'] ? 'text-error' : 'text-success') }}">
                            £{{ number_format($balanceSummary['balance_after_charges'], 2) }}
                        </dd>
                    </div>
                </dl>

                @if ($balanceSummary['overpaid'])
                    <p class="text-sm text-warning">Folio is overpaid. Resolve via billing adjustments or refunds before continuing.</p>
                @elseif ($balanceSummary['needs_payment'])
                    <p class="text-sm text-base-content/70">A payment for £{{ number_format($balanceSummary['settle_amount'], 2) }} will be posted on checkout.</p>
                @else
                    <p class="text-sm text-success">Folio is balanced. No additional payment required.</p>
                @endif
            @else
                <p class="text-sm text-base-content/70">Select a reservation to load folio balances.</p>
            @endif
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <label class="flex flex-col gap-1 w-full">
                <span class="label-text font-semibold">Final payment method</span>
                <select class="select select-bordered" wire:model.lazy="finalPaymentMethod">
                    <option value="">Select method…</option>
                    @foreach (['Cash', 'Card', 'Wire'] as $method)
                        <option value="{{ $method }}">{{ $method }}</option>
                    @endforeach
                </select>
                @error('finalPaymentMethod')
                    <span class="text-sm text-error">{{ $message }}</span>
                @enderror
                <p class="text-xs text-base-content/60">Required when there is a balance to settle.</p>
            </label>
        </div>

        <label class="flex flex-col gap-1 w-full">
            <span class="label-text font-semibold">Notes to billing</span>
            <textarea
                class="textarea textarea-bordered min-h-20"
                wire:model.lazy="notes"
                maxlength="1000"
                placeholder="Outstanding disputes, promised adjustments, etc."
            ></textarea>
            @error('notes')
                <span class="text-sm text-error">{{ $message }}</span>
            @enderror
        </label>

        <div class="card-actions justify-end">
            @php
                $settleBlocked = $balanceSummary && $balanceSummary['overpaid'];
            @endphp
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" @disabled($settleBlocked)>
                <span wire:loading.remove wire:target="save">Complete check-out</span>
                <span wire:loading wire:target="save">Processing…</span>
            </button>
            @if ($settleBlocked)
                <p class="text-sm text-warning text-right w-full md:w-auto">Cannot complete checkout while folio is overpaid. Adjust in billing first.</p>
            @endif
        </div>
    </form>
</div>
