<div>
    <form wire:submit.prevent="submit">
        <div class="card bg-base-100 shadow">
            <div class="card-body space-y-6">

                {{-- Primary guest --}}
                <div class="space-y-2">
                    <h3 class="text-sm font-semibold text-base-content/80">Primary guest</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="flex flex-col gap-2 w-full">
                            <span class="label-text font-semibold">Guest</span>
                            <livewire:inputs.searchable-select
                                wire:key="guest-search"
                                wire:model.live="customerId"
                                model="App\\Models\\CustomerUser"
                                :search-fields="['name','email']"
                                :display-fields="['name','email']"
                                placeholder="Search by guest name or email"
                                :max-results="8"
                                max-width="320px"
                                input-classes="input input-bordered w-full"
                            />
                            @error('customerId')
                                <span class="text-sm text-error">{{ $message }}</span>
                            @enderror
                        </label>
                        <div class="rounded-lg border border-base-200 bg-base-200/40 p-3 text-sm">
                            <p class="font-semibold">Selected</p>
                            <p>{{ $primaryGuestName ?: '—' }}</p>
                            <p class="text-base-content/70">{{ $primaryGuestEmail ?: 'No email on file' }}</p>
                        </div>
                    </div>
                </div>

                {{-- Stay dates --}}
                <div class="space-y-2">
                    <h3 class="text-sm font-semibold text-base-content/80">Stay dates</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="flex flex-col gap-2 w-full">
                            <span class="label-text font-semibold">Check-in date</span>
                            <input
                                type="date"
                                wire:model.live="checkInDate"
                                class="input input-bordered"
                                min="{{ now()->toDateString() }}"
                            />
                            @error('checkInDate')
                                <span class="text-sm text-error">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="flex flex-col gap-2 w-full">
                            <span class="label-text font-semibold">Check-out date</span>
                            <input
                                type="date"
                                wire:model.live="checkOutDate"
                                class="input input-bordered"
                                min="{{ now()->toDateString() }}"
                            />
                            @error('checkOutDate')
                                <span class="text-sm text-error">{{ $message }}</span>
                            @enderror
                        </label>
                    </div>
                </div>

                {{-- Rooms --}}
                <div class="space-y-2">
                    <h3 class="text-sm font-semibold text-base-content/80">Rooms</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="label-text font-semibold text-base-content">Available room types</p>
                            <span class="badge badge-outline badge-sm">Auto-assigned</span>
                        </div>
                        <p class="text-sm text-base-content/70">Set how many rooms you need per type. We’ll pick the best available rooms for the dates.</p>
                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach ($roomTypeOptions as $room)
                                @php
                                    $availability = $roomAvailability[$room['id']] ?? ['available_count' => null, 'status' => 'unknown', 'message' => null];
                                    $availableCount = $availability['available_count'] ?? null;
                                    $status = $availability['status'] ?? 'available';
                                    $statusTone = $status === 'unavailable' ? 'text-error' : ($status === 'limited' ? 'text-warning' : 'text-success');
                                    $disableRooms = blank($checkInDate) || blank($checkOutDate) || ($availableCount === 0);
                                @endphp
                                <div class="rounded-lg border border-base-200 bg-base-100 p-3 flex flex-col gap-2">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-semibold">{{ $room['name'] }}</p>
                                            <p class="text-sm text-base-content/70">
                                                Sleeps {{ ($room['max_adults'] ?? 0) + ($room['max_children'] ?? 0) }}
                                                · {{ ($room['max_children'] ?? 0) > 0 ? 'Children welcome' : 'Adults only' }}
                                            </p>
                                            @if ($availability['message'] ?? null)
                                                <p class="text-xs text-warning mt-1">{{ $availability['message'] }}</p>
                                            @endif
                                        </div>
                                        <div class="min-w-[140px]">
                                            <label class="flex flex-col gap-1">
                                                <span class="label-text text-sm font-semibold">Rooms</span>
                            <input
                                type="number"
                                min="0"
                                @if(!is_null($availableCount)) max="{{ min($availableCount, 10) }}" @else max="10" @endif
                                class="input input-bordered input-sm w-full"
                                wire:model.debounce.300ms.number="roomSelections.{{ $room['id'] }}"
                                @disabled($disableRooms)
                            />
                                                <span class="label-text-alt text-base-content/60">
                                                    @if ($availableCount === 0)
                                                        Not available
                                                    @elseif (! is_null($availableCount))
                                                        {{ $availableCount }} available
                                                    @else
                                                        Set dates to see availability
                                                    @endif
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="badge badge-ghost badge-xs"></span>
                                        <span class="{{ $statusTone }}">
                                            {{ $status === 'unavailable' ? 'Unavailable' : ($status === 'limited' ? 'Limited' : 'Available') }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('roomSelections')
                            <span class="text-sm text-error">{{ $message }}</span>
                        @enderror
                        <div class="mt-2 text-sm text-base-content/70" wire:loading.flex wire:target="checkInDate,checkOutDate,roomSelections">
                            <span class="loading loading-xs loading-spinner"></span>
                            <span class="ml-2">Checking availability…</span>
                        </div>
                        @if ($checkInDate && $checkOutDate && empty($roomTypeOptions) && !$errors->has('checkOutDate'))
                            <p class="mt-2 text-sm text-warning">No room types available for these dates, try adjusting.</p>
                        @endif
                    </div>
                </div>

                    {{-- room summary --}}
                    @php
                        $selected = collect($selectedRooms ?? []);
                        $selectedCapacity = $selected->sum(fn ($room) => ($room['max_adults'] ?? 0) + ($room['max_children'] ?? 0));
                        $selectedAdults = $selected->sum('max_adults');
                        $selectedChildren = $selected->sum('max_children');
                        $selectedRate = $selected->sum('nightly_rate');
                    @endphp
                    <div class="rounded-lg border border-base-300 bg-base-100 p-4 text-sm">
                        <p class="font-semibold text-base-content/80">Selected rooms summary</p>
                        <p>{{ $selected->count() }} room{{ $selected->count() === 1 ? '' : 's' }} selected.</p>
                        <p>Capacity: {{ $selectedAdults }} adults / {{ $selectedChildren }} children ({{ $selectedCapacity }} total occupants)</p>
                        <p>Nightly rate total: {{ number_format($selectedRate, 2) }} GBP</p>
                    </div>

                {{-- Guests --}}
                <div class="space-y-2">
                    <h3 class="text-sm font-semibold text-base-content/80">Guests</h3>
                    @php
                        $selected = collect($selectedRooms ?? []);
                        $maxAdults = (int) $selected->sum('max_adults');
                        $maxChildren = (int) $selected->sum('max_children');
                    @endphp
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="flex flex-col gap-2 w-full">
                            <span class="label-text font-semibold">Adults</span>
                            <input
                                type="number"
                                min="1"
                                max="20"
                                wire:model.live.number="adults"
                                class="input input-bordered"
                                @disabled(empty($roomTypeOptions))
                            />
                            <span class="label-text-alt text-base-content/60">
                                {{ $selected->isNotEmpty() ? 'Max ' . $maxAdults . ' adults across selected rooms' : 'Select dates and rooms first' }}
                            </span>
                            @error('adults')
                                <span class="text-sm text-error">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="flex flex-col gap-2 w-full">
                            <span class="label-text font-semibold">Children</span>
                            <input
                                type="number"
                                min="0"
                                max="20"
                                wire:model.live.number="children"
                                class="input input-bordered"
                                @disabled(empty($roomTypeOptions))
                            />
                            <span class="label-text-alt text-base-content/60">
                                {{ $selected->isNotEmpty() ? 'Max ' . $maxChildren . ' children across selected rooms' : 'Select dates and rooms first' }}
                            </span>
                            @error('children')
                                <span class="text-sm text-error">{{ $message }}</span>
                            @enderror
                        </label>
                    </div>
                </div>

                {{-- Occupant names --}}
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-base-content/80">Occupant names</h3>
                    <p class="text-sm text-base-content/70">Primary guest is included automatically; add any additional adults or children below.</p>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <p class="font-semibold">Additional adults</p>
                            @if ($adults <= 1)
                                <p class="mt-2 text-sm text-base-content/60">No extra adults added.</p>
                            @endif
                            @for ($i = 0; $i < max(0, $adults - 1); $i++)
                                <div class="mt-2" wire:key="adult-{{ $i }}">
                                    <input
                                        type="text"
                                        wire:model.lazy="adultNames.{{ $i }}"
                                        class="input input-bordered flex flex-col gap-2 w-full"
                                        placeholder="Adult name"
                                        @disabled(empty($roomTypeOptions))
                                    />
                                    @error('adultNames.' . $i)
                                        <span class="text-sm text-error">{{ $message }}</span>
                                    @enderror
                                </div>
                            @endfor
                        </div>

                        <div>
                            <p class="font-semibold">Children</p>
                            @if ($children === 0)
                                <p class="mt-2 text-sm text-base-content/60">No children added.</p>
                            @endif
                            @for ($i = 0; $i < max(0, $children); $i++)
                                <div class="mt-2" wire:key="child-{{ $i }}">
                                    <input
                                        type="text"
                                        wire:model.lazy="childNames.{{ $i }}"
                                        class="input input-bordered flex flex-col gap-2 w-full"
                                        placeholder="Child name"
                                        @disabled(empty($roomTypeOptions))
                                    />
                                    @error('childNames.' . $i)
                                        <span class="text-sm text-error">{{ $message }}</span>
                                    @enderror
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        class="btn btn-primary"
                        wire:loading.attr="disabled"
                        wire:target="submit"
                    >
                        <span wire:loading.remove wire:target="submit">Create</span>
                        <span wire:loading.inline wire:target="submit" class="flex items-center gap-2">
                            <span class="loading loading-spinner loading-xs mr-2"></span>
                            Creating...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
