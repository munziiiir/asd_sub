<div class="space-y-6">
    @php
        $statusLabel = strtolower($reservation->status);
        $helper = match ($statusLabel) {
            'checkedin' => 'Checked-in: only the check-out date can be adjusted.',
            'checkedout', 'cancelled', 'noshow' => 'This reservation is locked. Edits are disabled for this status.',
            default => 'You can edit dates, guests, rooms, and names while pending or confirmed.',
        };
        $roomSummary = $selectedRooms->count()
            ? $selectedRooms->pluck('name')->implode(', ')
            : 'Auto-assigned';
        $assignedRooms = $reservation->rooms
            ->map(function ($room) {
                $type = $room->roomType?->name;
                $number = $room->number;
                return $number ? ($type ? "{$number} ({$type})" : $number) : ($type ?: null);
            })
            ->filter()
            ->values();
        $roomSummary = $assignedRooms->isNotEmpty() ? $assignedRooms->implode(', ') : $roomSummary;
    @endphp

    <div class="card bg-base-100 shadow" data-edit-anchor>
        <div class="card-body space-y-6">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <span class="badge badge-outline badge-neutral text-sm">{{ $reservation->status }}</span>
                    <p class="text-sm text-base-content/70">{{ $helper }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($isEditing)
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="cancelEditing">Cancel editing</button>
                    @elseif (! $cannotEdit)
                        <button type="button" class="btn btn-primary btn-sm" wire:click="beginEditing">Edit details</button>
                    @endif
                </div>
            </div>

            <div class="space-y-3">
                <h2 class="text-xl font-semibold">Reservation summary</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-lg border border-base-200 bg-base-200/50 p-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm text-base-content/60">Guest</span>
                                <span class="font-semibold text-base-content text-right">{{ $reservation->customer->name ?? 'Guest profile missing' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm text-base-content/60">Dates</span>
                                <span class="font-semibold text-base-content text-right">{{ optional($reservation->check_in_date)->toDateString() }} → {{ optional($reservation->check_out_date)->toDateString() }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm text-base-content/60">Guests</span>
                                <span class="font-semibold text-base-content text-right">{{ $reservation->adults }} adult{{ $reservation->adults === 1 ? '' : 's' }}@if($reservation->children) · {{ $reservation->children }} child{{ $reservation->children === 1 ? '' : 'ren' }} @endif</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm text-base-content/60">Nightly rate</span>
                                <span class="font-semibold text-base-content text-right">{{ number_format($nightlyRate, 2) }} GBP</span>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg border border-base-200 bg-base-200/50 p-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm text-base-content/60">Rooms</span>
                                <span class="font-semibold text-base-content text-right">{{ $roomSummary }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm text-base-content/60">Occupant names</span>
                                <span class="font-semibold text-base-content text-right">{{ $primaryGuestName }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if ($cannotEdit)
                <div class="alert alert-warning">
                    <span>This reservation cannot be edited because it is {{ strtolower($reservation->status) }}.</span>
                </div>
            @endif

            @if ($isEditing)
                <form wire:submit.prevent="save" class="space-y-6">
                    <div class="divider my-2"></div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="grid w-full gap-2">
                            <span class="label-text font-semibold">Check-in date</span>
                            <input
                                type="date"
                                class="input input-bordered"
                                wire:model.live="checkInDate"
                                min="{{ now()->toDateString() }}"
                                @disabled(! $isEditing || ! $canEditStayDetails || $cannotEdit)
                            >
                            @error('checkInDate')
                                <span class="text-sm text-error">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="grid w-full gap-2">
                            <span class="label-text font-semibold">Check-out date @if(strtolower($reservation->status) === 'checkedin')<span class="text-xs text-warning ml-1">(adjustable while checked in)</span>@endif</span>
                            <input
                                type="date"
                                class="input input-bordered"
                                wire:model.live="checkOutDate"
                                min="{{ now()->toDateString() }}"
                                @disabled(! $isEditing || $cannotEdit)
                            >
                            @error('checkOutDate')
                                <span class="text-sm text-error">{{ $message }}</span>
                            @enderror
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="grid w-full gap-2">
                            <span class="label-text font-semibold">Adults</span>
                            <input
                                type="number"
                                min="1"
                                max="20"
                                class="input input-bordered"
                                wire:model.live.number="adults"
                                @disabled(! $isEditing || ! $canEditStayDetails || $cannotEdit)
                            >
                            @error('adults')
                                <span class="text-sm text-error">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="grid w-full gap-2">
                            <span class="label-text font-semibold">Children</span>
                            <input
                                type="number"
                                min="0"
                                max="20"
                                class="input input-bordered"
                                wire:model.live.number="children"
                                @disabled(! $isEditing || ! $canEditStayDetails || $cannotEdit)
                            >
                            @error('children')
                                <span class="text-sm text-error">{{ $message }}</span>
                            @enderror
                        </label>
                    </div>

                    <div class="divider my-2"></div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-base-content">Room assignment</h3>
                            <span class="badge badge-outline badge-sm">Auto-assigned</span>
                        </div>
                        <div class="grid gap-3 grid-cols-1 md:grid-cols-2 xl:grid-cols-4">
                            @foreach ($roomTypeOptions as $room)
                                @php
                                    $availability = $roomAvailability[$room['id']] ?? ['available_count' => null, 'status' => 'unknown', 'message' => null];
                                    $availableCount = $availability['available_count'] ?? null;
                                    $status = $availability['status'] ?? 'available';
                                    $statusTone = $status === 'unavailable' ? 'text-error' : ($status === 'limited' ? 'text-warning' : 'text-success');
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
                                        <div class="min-w-[120px]">
                                            <label class="flex flex-col gap-1">
                                                <span class="label-text text-sm font-semibold">Rooms</span>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    @if(!is_null($availableCount)) max="{{ min($availableCount, 10) }}" @else max="10" @endif
                                                    class="input input-bordered input-sm w-full"
                                                    wire:model.debounce.300ms.number="roomSelections.{{ $room['id'] }}"
                                                    @disabled(! $isEditing || ! $canEditStayDetails || $cannotEdit || ($availableCount === 0))
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
                            <span class="ml-2">Refreshing availability…</span>
                        </div>
                    </div>

                    @php
                        $selected = collect($selectedRooms ?? []);
                        $selectedCapacity = $selected->sum(fn ($room) => ($room['max_adults'] ?? 0) + ($room['max_children'] ?? 0));
                        $selectedAdults = $selected->sum('max_adults');
                        $selectedChildren = $selected->sum('max_children');
                        $selectedRate = $selected->sum('nightly_rate');
                    @endphp
                    @if ($selected->count() > 0)
                        <div class="divider my-2"></div>
                        <div class="rounded-lg border border-base-200 bg-base-100 p-4 text-sm">
                            <p class="font-semibold text-base-content/80">Selected rooms summary</p>
                            <p>{{ $selected->count() }} room{{ $selected->count() === 1 ? '' : 's' }} selected.</p>
                            <p>Capacity: {{ $selectedAdults }} adults / {{ $selectedChildren }} children ({{ $selectedCapacity }} total occupants)</p>
                            <p>Nightly rate total: {{ number_format($selectedRate, 2) }} GBP</p>
                        </div>
                    @endif

                    <div class="divider my-2"></div>

                    <div class="space-y-4">
                        <h2 class="card-title">Occupants</h2>
                        <p class="text-sm text-base-content/70">Primary guest is included automatically; edit any additional adults or children below.</p>

                        <div class="rounded-lg border border-base-200 bg-base-200/50 p-4">
                            <p class="font-semibold">Primary guest</p>
                            <p>{{ $primaryGuestName }}</p>
                            <p class="text-sm text-base-content/70">{{ $primaryGuestEmail ?: 'No email on file' }}</p>
                        </div>

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
                                            class="input input-bordered w-full"
                                            placeholder="Adult name"
                                            @disabled(! $isEditing || ! $canEditStayDetails || $cannotEdit)
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
                                    <p class="mt-2 text-sm text-base-content/60">No extra children added.</p>
                                @endif
                                @for ($i = 0; $i < $children; $i++)
                                    <div class="mt-2" wire:key="child-{{ $i }}">
                                        <input
                                            type="text"
                                            wire:model.lazy="childNames.{{ $i }}"
                                            class="input input-bordered w-full"
                                            placeholder="Child name"
                                            @disabled(! $isEditing || ! $canEditStayDetails || $cannotEdit)
                                        />
                                        @error('childNames.' . $i)
                                            <span class="text-sm text-error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                @endfor
                            </div>
                        </div>
                    </div>

                    <div class="card-actions justify-end border-t border-base-200 bg-base-100 px-0 pt-4">
                        <div class="flex flex-wrap gap-3">
                            <button
                                type="submit"
                                class="btn btn-primary"
                                wire:loading.attr="disabled"
                                wire:target="save"
                                @disabled($cannotEdit)
                            >
                                <span wire:loading.remove wire:target="save">Save changes</span>
                                <span wire:loading.inline wire:target="save" class="flex items-center gap-2">
                                    <span class="loading loading-spinner loading-xs mr-2"></span>
                                    Saving...
                                </span>
                            </button>
                            <button type="button" class="btn btn-ghost" wire:click="cancelEditing">Cancel</button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
