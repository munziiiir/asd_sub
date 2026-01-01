<section class="bg-base-200 min-h-[calc(100vh-4rem)] px-6 py-12">
    <div class="max-w-5xl mx-auto space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <p class="text-sm uppercase tracking-widest text-primary font-semibold">Book now</p>
                <h1 class="text-3xl md:text-4xl font-bold text-base-content">Plan your stay in a few taps</h1>
                <p class="text-base-content/70 mt-2">We collect one detail at a time so nothing feels overwhelming.</p>
            </div>
        </div>

        @if ($errorMessage)
            <div class="alert alert-error max-w-6xl mx-auto">
                <span>{{ $errorMessage }}</span>
            </div>
        @endif
        @if ($statusMessage)
            <div class="alert alert-success max-w-6xl mx-auto">
                <span>{{ $statusMessage }}</span>
            </div>
        @endif

        <div class="relative max-w-6xl mx-auto">
            <div class="grid gap-6 lg:grid-cols-[1.1fr,0.9fr]">
                <div class="card bg-base-100 shadow-lg border border-base-300/60 w-full @if($confirmed) hidden lg:col-span-full absolute left-1/2 -translate-x-1/2 w-full max-w-6xl pointer-events-none @endif">
                    <div class="card-body gap-6">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div>
                            @php $stepTitles = ['dates' => 'Pick dates', 'country' => 'Choose a country', 'hotel' => 'Pick your hotel', 'room' => 'Select room type', 'guests' => 'Confirm guests', 'names' => 'Add guest names']; @endphp
                            <h2 class="text-xl font-semibold text-base-content">
                                {{ $stepTitles[$stepIds[$step] ?? 'country'] ?? 'Choose a country' }}
                            </h2>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-4 ml-auto">
                                @if ($confirmed)
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="editDraft">Edit Draft</button>
                                        <button type="button" class="btn btn-primary btn-sm" wire:click="finalizeBooking">Continue to payment</button>
                                    </div>
                                @else
                                    <div class="flex flex-col items-end gap-2">
                                        <span class="text-xs uppercase tracking-widest text-primary font-semibold">Step {{ $step + 1 }} of {{ $stepCount }}</span>
                                        <div class="w-20 h-2 rounded-full bg-base-200 overflow-hidden">
                                            <div class="h-full bg-primary transition-all duration-300" style="width: {{ $progressPercent }}%;"></div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="{{ $stepIds[$step] === 'dates' ? '' : 'hidden' }}">
                            <p class="text-base-content font-semibold">What dates suit you best?</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="flex flex-col gap-1">
                                    <span class="label-text font-semibold">Check-in</span>
                                    <input type="date" wire:model.debounce.300ms="checkIn" class="input input-bordered input-lg">
                                </label>
                                <label class="flex flex-col gap-1">
                                    <span class="label-text font-semibold">Check-out</span>
                                    <input type="date" wire:model.debounce.300ms="checkOut" class="input input-bordered input-lg">
                                </label>
                            </div>
                            <p class="text-sm text-base-content/70">Check-out must be after check-in.</p>
                        </div>

                        <div class="{{ $stepIds[$step] === 'country' ? '' : 'hidden' }}">
                            <p class="text-base-content font-semibold">Which country are you staying in?</p>
                            <select wire:model="selectedCountry" class="select select-lg select-bordered w-full" @disabled(!($checkIn && $checkOut))>
                                <option value="">Choose a country</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country }}">{{ $country }}</option>
                                @endforeach
                            </select>
                            <p class="text-sm text-base-content/70">We only list countries with available hotels.</p>
                        </div>

                        <div class="{{ $stepIds[$step] === 'hotel' ? '' : 'hidden' }}">
                            <p class="text-base-content font-semibold">Choose your hotel in <span class="text-primary">{{ $selectedCountry ?: 'the selected country' }}</span>.</p>
                            <select wire:model="selectedHotelId" class="select select-lg select-bordered w-full" @disabled(empty($selectedCountry))>
                                <option value="">Choose a hotel</option>
                                @foreach ($this->availableHotels as $hotel)
                                    <option value="{{ $hotel['id'] }}">{{ $hotel['name'] }} ({{ $hotel['code'] }})</option>
                                @endforeach
                            </select>
                            <p class="text-sm text-base-content/70">Hotels update automatically once you pick a country.</p>
                        </div>

                        <div class="{{ $stepIds[$step] === 'room' ? '' : 'hidden' }}">
                            <p class="text-base-content font-semibold">Select your room types and counts.</p>
                            <p class="text-sm text-base-content/70 mb-2">Set the number of rooms per type; availability and capacity adjust automatically.</p>
                            <div class="space-y-3">
                                @foreach ($this->availableRoomTypes as $room)
                                    @php
                                        $capacity = $this->roomCapacity($room);
                                        $allowsChildren = $room['max_children'] > 0;
                                        $availability = $roomAvailability[$room['id']] ?? ['available' => true, 'status' => 'available', 'message' => null, 'available_count' => null];
                                        $availableCount = $availability['available_count'] ?? null;
                                        $disabled = $availableCount === 0;
                                        $count = $roomSelections[$room['id']] ?? 0;
                                    @endphp
                                    <div class="rounded-lg border border-base-200 bg-base-100 p-3 flex flex-col gap-2">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-semibold">{{ $room['name'] }}</p>
                                                <p class="text-sm text-base-content/70">
                                                    Sleeps {{ $capacity }}{{ $allowsChildren ? ' · Children welcome' : ' · Adults only' }}
                                                </p>
                                                @if(($availability['message'] ?? null))
                                                    <p class="text-xs text-warning mt-1">{{ $availability['message'] }}</p>
                                                @endif
                                            </div>
                                            <div class="min-w-[140px]">
                                                <label class="flex flex-col gap-1">
                                                    <span class="label-text text-sm font-semibold">Rooms</span>
                                                    <input
                                                        type="number"
                                                        wire:model.debounce.300ms.number="roomSelections.{{ $room['id'] }}"
                                                        min="0"
                                                        @if($availableCount) max="{{ min($availableCount, 10) }}" @else max="10" @endif
                                                        class="input input-bordered input-sm w-full"
                                                        @disabled($disabled)
                                                    >
                                                    <span class="label-text-alt text-base-content/60">
                                                        {{ $availableCount !== null ? ($availableCount . ' available') : 'Set dates to see availability' }}
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="{{ $stepIds[$step] === 'guests' ? '' : 'hidden' }} space-y-4">
                            <div>
                                <p class="text-base-content font-semibold">Assign guests to each room.</p>
                                @php
                                    $capacity = $this->totalCapacity();
                                    $allowsChildren = $this->maxChildren() > 0;
                                    $slots = $this->roomSlots();
                                @endphp
                                <p class="text-sm text-base-content/70">
                                    @if ($capacity)
                                        Sleeps up to {{ $capacity }} across {{ $this->roomsSelected() }} room{{ $this->roomsSelected() > 1 ? 's' : '' }}. {{ $allowsChildren ? 'Children welcome within capacity.' : 'Children not permitted for these rooms.' }}
                                    @else
                                        Adults and children (if applicable).
                                    @endif
                                </p>
                            </div>
                            <div class="space-y-3">
                                @forelse ($slots as $slot)
                                    @php
                                        $key = $slot['type_id'].'-'.$slot['index'];
                                        $occupant = $roomOccupants[$key] ?? ['adults' => 1, 'children' => 0];
                                    @endphp
                                    <div class="rounded-lg border border-base-200 bg-base-100 p-3" wire:key="room-slot-{{ $key }}">
                                        <div class="flex items-center justify-between gap-3 flex-wrap">
                                            <div>
                                                <p class="font-semibold text-base-content">{{ $slot['type_name'] }} — Room {{ $slot['index'] }}</p>
                                                <p class="text-xs text-base-content/70">Max {{ $slot['capacity'] }} total · {{ $slot['max_adults'] }} adults / {{ $slot['max_children'] }} children</p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
                                            <label class="flex flex-col gap-1">
                                                <span class="label-text font-semibold">Adults</span>
                                                <input
                                                    type="number"
                                                    class="input input-bordered"
                                                    min="1"
                                                    max="{{ $slot['max_adults'] }}"
                                                    wire:model.live.number="roomOccupants.{{ $key }}.adults"
                                                    wire:key="room-slot-{{ $key }}-adults"
                                                >
                                            </label>
                                            <label class="flex flex-col gap-1 @if($slot['max_children'] === 0) opacity-60 @endif">
                                                <span class="label-text font-semibold">Children</span>
                                                <input
                                                    type="number"
                                                    class="input input-bordered"
                                                    min="0"
                                                    max="{{ $slot['max_children'] }}"
                                                    wire:model.live.number="roomOccupants.{{ $key }}.children"
                                                    wire:key="room-slot-{{ $key }}-children"
                                                    @disabled($slot['max_children'] === 0)
                                                >
                                            </label>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-base-content/70">Select at least one room to assign guests.</p>
                                @endforelse
                            </div>
                        </div>

                        @php $namesStepActive = in_array('names', $stepIds, true); @endphp
                        <div class="{{ $stepIds[$step] === 'names' ? '' : 'hidden' }} space-y-4">
                            <div>
                                <p class="text-base-content font-semibold">Add names for everyone joining you.</p>
                                <p class="text-sm text-base-content/70">
                                    @if ($this->namesNeeded() > 0)
                                        We need {{ $this->namesNeeded() }} additional name{{ $this->namesNeeded() > 1 ? 's' : '' }} (excluding you).
                                    @else
                                        No extra names needed when it is just you.
                                    @endif
                                </p>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="flex flex-col gap-1 opacity-60">
                                    <span class="label-text font-semibold">Guest 1 (you)</span>
                                    <input type="text" class="input input-bordered input-lg" value="{{ auth()->user()?->name ?? 'Primary guest' }}" disabled>
                                </label>
                                @for ($i = 0; $i < $this->namesNeeded(); $i++)
                                    @php
                                        $isAdult = $i < max($adults - 1, 0);
                                    @endphp
                                    <label class="flex flex-col gap-1">
                                        <span class="label-text font-semibold">Guest {{ $i + 2 }} ({{ $isAdult ? 'adult' : 'child' }})</span>
                                        <input type="text" wire:model.live="guestNames.{{ $i }}" class="input input-bordered input-lg" placeholder="Full name">
                                    </label>
                                @endfor
                            </div>
                            <p class="text-sm text-base-content/70">These names help us prep check-in details.</p>
                        </div>

                        <div class="{{ $stepIds[$step] === 'dates' ? '' : 'hidden' }} space-y-4"></div>
                    </div>

                    <div class="flex items-center justify-between gap-3 flex-wrap pt-2">
                        <button type="button" class="btn btn-ghost" wire:click="back" @disabled($step === 0)>Back</button>
                        <div class="flex items-center gap-3">
                            @php $isNamesStep = ($stepIds[$step] ?? '') === 'names'; @endphp
                            @if ($step !== $stepCount - 1)
                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    wire:click="next"
                                    @disabled($step === $stepCount - 1)
                                    data-next-btn
                                >
                                        <span>Next</span>
                                        <span wire:loading.inline wire:target="next" class="flex items-center gap-2">
                                        <span class="loading loading-spinner loading-xs mr-1"></span>
                                        Working…
                                    </span>
                                </button>
                            @endif
                            <button type="button" class="btn btn-success @if($step !== $stepCount - 1) hidden @endif" wire:click="confirm">Save</button>
                        </div>
                    </div>
                </div>
            </div>

                <div class="space-y-4 @if($confirmed) lg:col-span-full flex justify-center @endif">
                <div class="card bg-gradient-to-br from-primary/10 via-base-100 to-secondary/10 border border-base-300/60 shadow-md w-full @if($confirmed) max-w-6xl mx-auto @endif">
                    <div class="card-body gap-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-base-content">Booking snapshot</h3>
                            @if ($reservationSummary)
                                <span class="badge badge-info badge-outline">Draft reservation {{ $reservationSummary['code'] }}</span>
                            @else
                                <span class="badge badge-primary badge-outline">Live preview</span>
                            @endif
                        </div>
                        <dl class="space-y-3 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-base-content/70">Country</dt>
                                <dd class="font-semibold text-base-content">{{ $selectedCountry ?: 'Not chosen' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-base-content/70">Hotel</dt>
                                <dd class="font-semibold text-base-content">{{ $this->selectedHotel['name'] ?? 'Not chosen' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-base-content/70">Rooms</dt>
                                @php
                                    $selectedTypes = $this->selectedRoomTypeCounts();
                                @endphp
                                <dd class="font-semibold text-base-content">
                                    @if ($selectedTypes->isNotEmpty())
                                        <div class="space-y-1">
                                            @foreach ($selectedTypes as $type)
                                                <span class="block">{{ $type['name'] ?? 'Room' }} × {{ $type['count'] ?? 0 }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        Not chosen
                                    @endif
                                </dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-base-content/70">Guests</dt>
                                @php
                                    $guestParts = [];
                                    if ($adults > 0) $guestParts[] = $adults . ' adult' . ($adults > 1 ? 's' : '');
                                    if ($children > 0) $guestParts[] = $children . ' child' . ($children > 1 ? 'ren' : '');
                                @endphp
                                <dd class="font-semibold text-base-content">{{ $guestParts ? implode(' + ', $guestParts) : 'Not set' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-base-content/70">Additional names</dt>
                                @php
                                    $filledNames = array_filter($guestNames, fn ($n) => trim((string)$n) !== '');
                                @endphp
                                <dd class="font-semibold text-base-content break-words">
                                    @if ($this->namesNeeded() === 0)
                                        None needed (solo stay)
                                    @elseif (count($filledNames) === 0)
                                        Pending {{ $this->namesNeeded() }} name{{ $this->namesNeeded() > 1 ? 's' : '' }}
                                    @elseif (count($filledNames) < $this->namesNeeded())
                                        <div class="space-y-1">
                                            <span class="block md:hidden whitespace-pre-line">{{ implode("\n", $filledNames) }}</span>
                                            <span class="hidden md:inline">{{ implode(', ', $filledNames) }}</span>
                                            <span class="text-sm font-normal text-base-content/70 block">({{ $this->namesNeeded() - count($filledNames) }} missing)</span>
                                        </div>
                                    @else
                                        <div class="space-y-1">
                                            <span class="block md:hidden whitespace-pre-line">{{ implode("\n", $filledNames) }}</span>
                                            <span class="hidden md:inline">{{ implode(', ', $filledNames) }}</span>
                                        </div>
                                    @endif
                                </dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-base-content/70">Dates</dt>
                                <dd class="font-semibold text-base-content">
                                    @if ($checkIn && $checkOut)
                                        {{ $checkIn }} → {{ $checkOut }}
                                    @elseif ($checkIn)
                                        Arriving {{ $checkIn }}
                                    @else
                                        Not set
                                    @endif
                                </dd>
                            </div>
                        </dl>
                        @if ($confirmed)
                            <div class="divider my-2"></div>
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="editDraft">Edit Draft</button>
                                <button type="button" class="btn btn-primary btn-sm" wire:click="finalizeBooking">Continue to payment</button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
