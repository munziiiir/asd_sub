<x-layouts.app.base :title="'Booking ' . $reservation->code">
    <section class="bg-base-200 min-h-[calc(100vh-4rem)] px-6 py-12">
        <div class="max-w-5xl mx-auto space-y-6">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <p class="text-sm uppercase tracking-widest text-primary font-semibold">Booking</p>
                    <h1 class="text-3xl md:text-4xl font-bold text-base-content">{{ $reservation->code }}</h1>
                    <p class="text-base-content/70 mt-2">{{ $reservation->hotel?->name }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('bookings.index') }}" class="btn btn-ghost btn-sm">← Back to bookings</a>
                </div>
            </div>

            <div class="card bg-gradient-to-br from-primary/10 via-base-100 to-secondary/10 border border-base-300/60 shadow-md">
                <div class="card-body space-y-4">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-outline">{{ $reservation->status }}</span>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('edit-section').classList.toggle('hidden')">Edit</button>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($reservation->status === 'Pending')
                                <a href="{{ route('bookings.pay', $reservation) }}" class="btn btn-primary btn-sm">Complete payment</a>
                            @endif
                            <label for="cancel-modal" class="btn btn-error btn-sm" @disabled(in_array($reservation->status, ['Cancelled','CheckedOut']))>Cancel</label>
                        </div>
                    </div>

                    <dl class="space-y-3 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-base-content/70">Country</dt>
                            <dd class="font-semibold text-base-content">{{ $reservation->hotel?->country?->name ?? 'Not set' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-base-content/70">Hotel</dt>
                            <dd class="font-semibold text-base-content">{{ $reservation->hotel?->name ?? 'Not set' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-base-content/70">Rooms</dt>
                            <dd class="font-semibold text-base-content">
                                @if($reservation->reservationRooms->count())
                                    @foreach ($reservation->reservationRooms as $room)
                                        Room {{ $room->room?->number }} ({{ $room->room?->roomType?->name }}) @if(!$loop->last), @endif
                                    @endforeach
                                @else
                                    Not set
                                @endif
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-base-content/70">Guests</dt>
                            <dd class="font-semibold text-base-content">{{ $reservation->adults }} adult{{ $reservation->adults > 1 ? 's' : '' }} @if($reservation->children) + {{ $reservation->children }} child{{ $reservation->children > 1 ? 'ren' : '' }} @endif</dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-base-content/70">Additional names</dt>
                            <dd class="font-semibold text-base-content">
                                @forelse ($reservation->occupants as $occupant)
                                    <span class="block">{{ $occupant->full_name }}</span>
                                @empty
                                    None needed (solo stay)
                                @endforelse
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-base-content/70">Dates</dt>
                            <dd class="font-semibold text-base-content">
                                @if ($reservation->check_in_date && $reservation->check_out_date)
                                    {{ optional($reservation->check_in_date)->toDateString() }} → {{ optional($reservation->check_out_date)->toDateString() }}
                                @else
                                    Not set
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <div id="edit-section" class="hidden space-y-4">
                        <div class="divider">Edit booking</div>
                        @php
                            $roomTypeOptions = $reservation->hotel?->roomTypes->map(function($rt){
                                $cap = max(1, max($rt->base_occupancy ?? 0, ($rt->max_adults ?? 0) + ($rt->max_children ?? 0)));
                                $name = strtolower($rt->name ?? '');
                                if (str_contains($name, 'family suite') || str_contains($name, 'penthouse')) {
                                    $cap = min($cap, 4);
                                }
                                return [
                                    'id' => $rt->id,
                                    'name' => $rt->name,
                                    'max_adults' => $rt->max_adults,
                                    'max_children' => $rt->max_children,
                                    'capacity' => $cap,
                                    'active_rate' => $rt->activeRate(),
                                ];
                            })->values();

                            $preselectedRooms = $reservation->reservationRooms
                                ->groupBy('room.room_type_id')
                                ->map(fn($group, $typeId) => [
                                    'id' => (int) $typeId,
                                    'count' => $group->count(),
                                ])
                                ->values();
                        @endphp
                        <form method="post" action="{{ route('bookings.update', $reservation) }}" class="space-y-4" id="booking-edit-form"
                            data-room-types='@json($roomTypeOptions)'
                            data-existing-names='@json($reservation->occupants->pluck("full_name")->values())'
                            data-preselected='@json($preselectedRooms)'
                            data-primary-name="{{ $reservation->customer->name ?? auth()->user()->name }}"
                        >
                            @csrf
                            @method('PATCH')
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="label-text font-semibold">Room types</span>
                                    <button type="button" class="btn btn-primary btn-xs gap-1" id="add-room-type" @disabled($reservation->status === 'CheckedOut')>+ Add room type</button>
                                </div>
                                <div id="room-type-rows" class="space-y-3"></div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="flex flex-col gap-1">
                                    <span class="label-text font-semibold">Check-in</span>
                                    <input type="date" name="check_in_date" value="{{ optional($reservation->check_in_date)->toDateString() }}" class="input input-bordered" @disabled($reservation->status === 'CheckedIn' || $reservation->status === 'CheckedOut')>
                                </label>
                                <label class="flex flex-col gap-1">
                                    <span class="label-text font-semibold">Check-out</span>
                                    <input type="date" name="check_out_date" value="{{ optional($reservation->check_out_date)->toDateString() }}" class="input input-bordered" @disabled($reservation->status === 'CheckedOut')>
                                </label>
                            </div>
                            <div class="hidden">
                                <label>
                                    <input type="number" name="adults" value="{{ $reservation->adults }}" min="1" max="20" aria-hidden="true" tabindex="-1" @disabled($reservation->status === 'CheckedOut')}>
                                </label>
                                <label>
                                    <input type="number" name="children" value="{{ $reservation->children }}" min="0" max="20" aria-hidden="true" tabindex="-1" id="children-input" @disabled($reservation->status === 'CheckedOut')}>
                                </label>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="label-text font-semibold">Additional occupant names</p>
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="btn btn-primary btn-xs gap-1" id="add-adult-btn">+ Adult</button>
                                        <button type="button" class="btn btn-primary btn-xs gap-1" id="add-child-btn">+ Child</button>
                                    </div>
                                </div>
                                <p class="text-xs text-base-content/70" id="guest-helper"></p>
                                <div id="occupant-fields" class="grid gap-3 sm:grid-cols-2"></div>
                                <span class="text-xs text-base-content/70 mt-1">One name per guest (excluding you). Fields update as guest counts change.</span>
                            </div>
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" class="btn btn-ghost" id="cancel-edit-btn">Cancel</button>
                                <button type="submit" class="btn btn-primary" @disabled($reservation->status === 'CheckedOut')>Save changes</button>
                            </div>
                            @if ($reservation->status === 'CheckedOut')
                                <p class="text-sm text-base-content/70">Checked-out bookings cannot be edited.</p>
                            @elseif ($reservation->status === 'CheckedIn')
                                <p class="text-sm text-base-content/70">Check-in date can no longer be changed.</p>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('booking-edit-form');
            if (!form) return;

            const roomTypes = JSON.parse(form.dataset.roomTypes || '[]');
            let existingNames = JSON.parse(form.dataset.existingNames || '[]');
            const preselected = JSON.parse(form.dataset.preselected || '[]');
            const defaultSelection = preselected.length
                ? preselected
                : (roomTypes[0]?.id ? [{ id: roomTypes[0].id, count: 1 }] : []);
            const adultsInput = form.querySelector('input[name="adults"]');
            const childrenInput = document.getElementById('children-input');
            const occupantFields = document.getElementById('occupant-fields');
            const guestHelper = document.getElementById('guest-helper');
            const rowsContainer = document.getElementById('room-type-rows');
            const addBtn = document.getElementById('add-room-type');
            const addAdultBtn = document.getElementById('add-adult-btn');
            const addChildBtn = document.getElementById('add-child-btn');
            const cancelBtn = document.getElementById('cancel-edit-btn');
            const editSection = document.getElementById('edit-section');
            const checkInInput = form.querySelector('input[name="check_in_date"]');
            const checkOutInput = form.querySelector('input[name="check_out_date"]');
            const initialState = {
                checkIn: checkInInput?.value || '',
                checkOut: checkOutInput?.value || '',
                adults: adultsInput?.value || '',
                children: childrenInput?.value || '',
                selections: defaultSelection,
            };

            // Remove the primary guest from prefilled additional occupants if present.
            const primaryName = (form.dataset.primaryName || '').trim();
            if (primaryName) {
                existingNames = existingNames.filter((n) => (n || '').trim() !== primaryName);
            } else if (existingNames.length) {
                // Fallback: drop the first entry if we don't know the primary name.
                existingNames = existingNames.slice(1);
            }

            const syncRowNames = () => {
                rowsContainer.querySelectorAll('.room-type-row').forEach((row, idx) => {
                    const select = row.querySelector('.room-type-select');
                    const countInput = row.querySelector('.room-type-count');
                    if (select) select.name = `room_types[${idx}][id]`;
                    if (countInput) countInput.name = `room_types[${idx}][count]`;
                });
            };

            const selectedRoomTypeIds = () => Array.from(rowsContainer.querySelectorAll('.room-type-select'))
                .map((s) => Number(s.value) || null)
                .filter(Boolean);

            const updateRemoveRoomButtons = () => {
                const buttons = rowsContainer.querySelectorAll('.remove-room-type');
                const disable = buttons.length <= 1;
                buttons.forEach((btn) => {
                    btn.disabled = disable;
                });
            };

            const updateAddRoomButton = () => {
                const atLimit = rowsContainer.querySelectorAll('.room-type-row').length >= roomTypes.length;
                if (addBtn) {
                    addBtn.disabled = atLimit;
                }
            };

            const refreshRoomTypeOptions = () => {
                const chosen = selectedRoomTypeIds();
                rowsContainer.querySelectorAll('.room-type-row').forEach((row) => {
                    const select = row.querySelector('.room-type-select');
                    const current = Number(select?.value) || null;
                    if (!select) return;
                    select.innerHTML = roomTypes.map((rt) => {
                        const disabled = chosen.includes(rt.id) && rt.id !== current;
                        return `<option value="${rt.id}" data-max-children="${rt.max_children}" data-max-adults="${rt.max_adults}" data-capacity="${rt.capacity}" ${disabled ? 'disabled' : ''}>${rt.name}</option>`;
                    }).join('');
                    if (current) {
                        select.value = current;
                    }
                });
            };

            const makeRow = (id = null, count = 1) => {
                const row = document.createElement('div');
                row.className = 'room-type-row grid grid-cols-1 sm:grid-cols-2 gap-3 items-end border border-base-300/50 rounded-lg p-3';
                const currentSelections = selectedRoomTypeIds();
                const allowed = roomTypes.filter((rt) => rt.id === id || !currentSelections.includes(rt.id));
                row.innerHTML = `
                    <label class="flex flex-col gap-1">
                        <span class="label-text font-semibold">Room type</span>
                        <select class="room-type-select select select-bordered" required>
                            ${allowed.map((rt) => `<option value="${rt.id}" data-max-children="${rt.max_children}" data-max-adults="${rt.max_adults}" data-capacity="${rt.capacity}">${rt.name}</option>`).join('')}
                        </select>
                    </label>
                    <div class="flex items-center gap-3">
                        <label class="flex flex-col gap-1 flex-1">
                            <span class="label-text font-semibold">Rooms</span>
                            <input type="number" min="1" max="10" value="${count}" class="room-type-count input input-bordered" required>
                        </label>
                        <button type="button" class="btn btn-outline btn-error btn-sm remove-room-type" aria-label="Remove">✕</button>
                    </div>
                `;
                const select = row.querySelector('select');
                if (id) {
                    select.value = id;
                }
                row.querySelector('.remove-room-type').addEventListener('click', () => {
                    if (rowsContainer.children.length > 1) {
                        row.remove();
                        syncRowNames();
                        onInputsChanged();
                        updateRemoveRoomButtons();
                        refreshRoomTypeOptions();
                        updateAddRoomButton();
                    }
                });
                rowsContainer.appendChild(row);
                updateRemoveRoomButtons();
                refreshRoomTypeOptions();
            };

            const rebuildRows = (selections) => {
                rowsContainer.innerHTML = '';
                (selections.length ? selections : defaultSelection).forEach(({ id, count }) => makeRow(id, count));
                syncRowNames();
                bindRowListeners();
                updateRemoveRoomButtons();
                updateAddRoomButton();
                refreshRoomTypeOptions();
            };

            const aggregateCaps = () => {
                const rows = Array.from(rowsContainer.querySelectorAll('.room-type-row')).map((row) => {
                    const select = row.querySelector('.room-type-select');
                    const option = select?.selectedOptions[0];
                    const countInput = row.querySelector('.room-type-count');
                    const count = Math.max(1, Number(countInput?.value) || 1);
                    return {
                        rooms: count,
                        max_children: Number(option?.dataset.maxChildren || 0) * count,
                        capacity: Number(option?.dataset.capacity || 0) * count,
                        max_adults: Math.max(1, Number(option?.dataset.maxAdults || option?.dataset.capacity || 1)) * count,
                    };
                });

                return rows.reduce((acc, row) => {
                    acc.rooms += row.rooms;
                    acc.max_children += row.max_children;
                    acc.capacity += row.capacity;
                    acc.max_adults += row.max_adults;
                    return acc;
                }, { rooms: 0, max_children: 0, capacity: 0, max_adults: 0 });
            };

            const buildRoomLabels = () => {
                const labels = [];
                rowsContainer.querySelectorAll('.room-type-row').forEach((row) => {
                    const select = row.querySelector('.room-type-select');
                    const option = select?.selectedOptions[0];
                    const name = option?.textContent?.trim() || 'Room';
                    const countInput = row.querySelector('.room-type-count');
                    const count = Math.max(1, Number(countInput?.value) || 1);
                    for (let i = 0; i < count; i += 1) {
                        labels.push(`Room ${labels.length + 1} (${name})`);
                    }
                });
                return labels.length ? labels : ['Room TBD'];
            };

            const updateGuestHelper = () => {
                const caps = aggregateCaps();
                const adults = Math.max(0, Number(adultsInput.value) || 0);
                const children = Math.max(0, Number(childrenInput.value) || 0);
                const minAdults = Math.max(caps.rooms, 1);
                if (guestHelper) {
                    guestHelper.textContent = `Max occupants: ${caps.capacity || 0} (Adults up to ${caps.max_adults}, Children up to ${caps.max_children}). Currently added: ${adults + children}. Min adults required: ${minAdults}.`;
                }
            };

            const updateAddButtons = () => {
                const caps = aggregateCaps();
                const adults = Math.max(0, Number(adultsInput.value) || 0);
                const children = Math.max(0, Number(childrenInput.value) || 0);
                const total = adults + children;
                if (addAdultBtn) {
                    addAdultBtn.disabled = adults >= caps.max_adults || total >= caps.capacity;
                }
                if (addChildBtn) {
                    addChildBtn.disabled = children >= caps.max_children || total >= caps.capacity;
                }
            };

            const clampGuests = () => {
                const caps = aggregateCaps();
                const minAdults = Math.max(caps.rooms, 1);
                let adults = Math.max(minAdults, Math.min(Number(adultsInput.value) || minAdults, caps.max_adults));
                let children = Math.max(0, Math.min(Number(childrenInput.value) || 0, caps.max_children));

                const total = adults + children;
                if (total > caps.capacity) {
                    const over = total - caps.capacity;
                    const reduceChildren = Math.min(over, children);
                    children -= reduceChildren;
                    const remaining = over - reduceChildren;
                    if (remaining > 0) {
                        adults = Math.max(minAdults, adults - remaining);
                    }
                }

                const maxByCapacity = Math.max(0, caps.capacity - adults);
                const allowedMaxChildren = Math.min(caps.max_children, maxByCapacity);
                childrenInput.disabled = caps.max_children === 0;
                adultsInput.value = adults;
                childrenInput.value = Math.min(children, allowedMaxChildren);
                childrenInput.max = allowedMaxChildren;
            };


            const renderOccupantFields = () => {
                const adults = Number(adultsInput.value) || 0;
                const children = Number(childrenInput.value) || 0;
                const minAdults = Math.max(aggregateCaps().rooms, 1);
                const existing = Array.from(occupantFields.querySelectorAll('input[name="occupant_names[]"]')).map((input) => input.value);
                const roomLabels = buildRoomLabels();
                const adultSlots = Math.max(adults - 1, 0);
                const childSlots = Math.max(children, 0);
                const totalSlots = adultSlots + childSlots;
                occupantFields.innerHTML = '';
                // Start assigning additional guests at room 2 if there is more than one room;
                // the primary guest is implicitly in the first room.
                const roomOffset = roomLabels.length > 1 ? 1 : 0;
                for (let i = 0; i < totalSlots; i += 1) {
                    const isAdult = i < adultSlots;
                    const wrapper = document.createElement('div');
                    wrapper.className = 'space-y-1 rounded-xl border border-base-300/50 p-3 bg-base-100/40';
                    wrapper.dataset.guestType = isAdult ? 'adult' : 'child';
                    const span = document.createElement('span');
                    span.className = 'label-text font-semibold';
                    span.textContent = `Guest ${i + 2} (${isAdult ? 'Adult' : 'Child'})`;
                    const helper = document.createElement('span');
                    helper.className = 'text-xs text-base-content/60';
                    helper.textContent = `Assigned to ${roomLabels[(roomOffset + i) % roomLabels.length]}`;
                    const inputRow = document.createElement('div');
                    inputRow.className = 'flex items-center gap-2';
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.name = 'occupant_names[]';
                    input.className = 'input input-bordered flex-1';
                    input.value = existing[i] ?? existingNames[i] ?? '';
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'btn btn-outline btn-error btn-sm remove-guest-btn';
                    removeBtn.textContent = '✕';
                    const currentAdults = Math.max(0, Number(adultsInput.value) || 0);
                    const currentChildren = Math.max(0, Number(childrenInput.value) || 0);
                    if ((isAdult && currentAdults <= minAdults) || (!isAdult && currentChildren <= 0)) {
                        removeBtn.disabled = true;
                    }
                    removeBtn.addEventListener('click', () => {
                        const caps = aggregateCaps();
                        const min = Math.max(caps.rooms, 1);
                        const adultsNow = Math.max(0, Number(adultsInput.value) || 0);
                        const childrenNow = Math.max(0, Number(childrenInput.value) || 0);
                        if (isAdult && adultsNow > min) {
                            adultsInput.value = adultsNow - 1;
                        } else if (!isAdult && childrenNow > 0) {
                            childrenInput.value = childrenNow - 1;
                        }
                        onInputsChanged();
                    });
                    inputRow.appendChild(input);
                    inputRow.appendChild(removeBtn);
                    wrapper.appendChild(span);
                    wrapper.appendChild(helper);
                    wrapper.appendChild(inputRow);
                    occupantFields.appendChild(wrapper);
                }
            };

            const onInputsChanged = () => {
                clampGuests();
                renderOccupantFields();
                updateGuestHelper();
                updateAddButtons();
                updateAddRoomButton();
                syncRoomHiddenInputs();
            };

            addBtn?.addEventListener('click', () => {
                makeRow();
                bindRowListeners();
                onInputsChanged();
                updateRemoveRoomButtons();
                refreshRoomTypeOptions();
                updateAddRoomButton();
                syncRowNames();
            });

            addAdultBtn?.addEventListener('click', () => {
                const caps = aggregateCaps();
                const adults = Math.max(0, Number(adultsInput.value) || 0);
                const children = Math.max(0, Number(childrenInput.value) || 0);
                if (adults < caps.max_adults && adults + children < caps.capacity) {
                    adultsInput.value = adults + 1;
                    onInputsChanged();
                }
            });

            addChildBtn?.addEventListener('click', () => {
                const caps = aggregateCaps();
                const adults = Math.max(0, Number(adultsInput.value) || 0);
                const children = Math.max(0, Number(childrenInput.value) || 0);
                if (children < caps.max_children && adults + children < caps.capacity) {
                    childrenInput.value = children + 1;
                    onInputsChanged();
                }
            });

            const bindRowListeners = () => {
                rowsContainer.querySelectorAll('.room-type-select, .room-type-count').forEach((el) => {
                    el.removeEventListener('change', onInputsChanged);
                    el.removeEventListener('input', onInputsChanged);
                    el.addEventListener('change', () => {
                        onInputsChanged();
                        refreshRoomTypeOptions();
                        syncRowNames();
                    });
                    el.addEventListener('input', onInputsChanged);
                });
            };

            const resetForm = () => {
                if (checkInInput) checkInInput.value = initialState.checkIn;
                if (checkOutInput) checkOutInput.value = initialState.checkOut;
                if (adultsInput) adultsInput.value = initialState.adults;
                if (childrenInput) childrenInput.value = initialState.children;
                rebuildRows(initialState.selections);
                onInputsChanged();
                editSection?.classList.add('hidden');
            };

            form.addEventListener('submit', () => {
                syncRowNames();
            });

            rebuildRows(defaultSelection);
            adultsInput.addEventListener('input', onInputsChanged);
            childrenInput.addEventListener('input', onInputsChanged);
            cancelBtn?.addEventListener('click', resetForm);

            onInputsChanged();
        });
    </script>

    <input type="checkbox" id="cancel-modal" class="modal-toggle">
    <div class="modal">
        <div class="modal-box space-y-3">
            <h3 class="font-bold text-lg">Cancel booking?</h3>
            <p class="text-base-content/80">
                This will cancel your reservation. This action cannot be undone.
            </p>
            @isset($cancellationPreview)
                <div class="p-4 rounded-xl bg-base-200 border border-base-300/70 space-y-4">
                    <div class="space-y-1">
                        <div class="text-sm font-semibold text-base-content/70 uppercase tracking-wide">Cancellation fee</div>
                        <div class="badge badge-error badge-outline">{{ $cancellationPreview['label'] }}</div>
                    </div>
                    <div class="grid gap-2">
                        <div>
                            <p class="text-xs text-base-content/70">You will be charged</p>
                            <p class="text-2xl font-semibold text-base-content">£{{ number_format($cancellationPreview['fee'], 2) }}</p>
                        </div>
                        <div class="p-3 rounded-lg bg-base-100 border border-base-300/50 space-y-1">
                            <p class="text-sm text-base-content/80">
                                Your paid deposit: £{{ number_format($cancellationPreview['paid'], 2) }}.
                            </p>
                            <p class="text-sm text-base-content">
                                @if($cancellationPreview['refundable'] > 0)
                                    This covers the fee; you’ll be refunded £{{ number_format($cancellationPreview['refundable'], 2) }} to your card.
                                @elseif($cancellationPreview['due'] > 0)
                                    This does not cover the fee; you’ll owe £{{ number_format($cancellationPreview['due'], 2) }} when you cancel.
                                @else
                                    This covers the fee; no refund is due.
                                @endif
                            </p>
                        </div>
                    </div>
                    <p class="text-xs">
                        <a href="#" class="link link-primary" target="_blank" rel="noreferrer">View cancellation policy</a>
                    </p>
                    {{-- TODO: Replace # link with actual cancellation policy URL --}}
                </div>
            @endisset
            <div class="modal-action">
                <label for="cancel-modal" class="btn btn-ghost">Keep booking</label>
                <form method="post" action="{{ route('bookings.cancel', $reservation) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-error">Cancel booking</button>
                </form>
            </div>
        </div>
        <label class="modal-backdrop" for="cancel-modal">Close</label>
    </div>
</x-layouts.app.base>
