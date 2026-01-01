<?php

namespace App\Livewire\Staff\Reservations;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\ReservationFolioService;
use App\Support\RoomStatusService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class EditReservation extends Component
{
    protected $casts = [
        'reservation.id' => 'integer',
        'hotelId' => 'integer',
        'adults' => 'integer',
        'children' => 'integer',
        'roomSelections' => 'array',
    ];

    public Reservation $reservation;

    /** @var array<int,string> */
    public array $statuses = [];

    /** @var array<int,string> */
    public array $statusOptions = [];

    /** @var array<string,mixed> */
    public array $backQuery = [];

    /** @var array<int,array<string,mixed>> */
    public array $roomTypeOptions = [];

    /** @var array<int,array<string,mixed>> */
    public array $roomAvailability = [];

    /** @var array<int,int> */
    public array $roomSelections = [];

    public string $status = '';

    public string $checkInDate = '';

    public string $checkOutDate = '';

    public $adults = 1;

    public $children = 0;

    /** @var array<int,string> */
    public array $adultNames = [];

    /** @var array<int,string> */
    public array $childNames = [];

    public string $primaryGuestName = '';

    public string $primaryGuestEmail = '';

    public ?int $hotelId = null;

    public bool $isEditing = false;

    private int $maxRoomsPerType = 10;

    public function mount(Reservation $reservation, array $statuses = [], array $backQuery = []): void
    {
        $reservation->loadMissing(['customer.user', 'rooms.roomType', 'reservationRooms', 'occupants']);

        $this->reservation = $reservation;
        $this->statuses = $statuses;
        $this->statusOptions = [$reservation->status];
        $this->backQuery = $backQuery;
        $this->hotelId = $reservation->hotel_id;

        $this->populateFromReservation();
    }

    public function render()
    {
        return view('livewire.staff.reservations.edit-reservation', [
            'roomTypeOptions' => $this->roomTypeOptions,
            'roomAvailability' => $this->roomAvailability,
            'selectedRooms' => $this->selectedRooms,
            'nightlyRate' => $this->displayedNightlyRate(),
            'cannotEdit' => $this->isEditLocked(),
            'canEditStayDetails' => $this->canEditStayDetails(),
            'isCheckedIn' => strtolower($this->reservation->status) === 'checkedin',
        ]);
    }

    public function updatedCheckInDate(): void
    {
        if (! $this->isEditing || ! $this->canEditStayDetails()) {
            return;
        }

        $this->refreshRoomAvailability();
    }

    public function updatedCheckOutDate(): void
    {
        if (! $this->isEditing) {
            return;
        }

        $this->refreshRoomAvailability();
    }

    public function updatedAdults(): void
    {
        if (! $this->isEditing || ! $this->canEditStayDetails()) {
            return;
        }

        $this->adults = $this->normalizeAdults($this->adults);
        $this->syncOccupancyCaps();
    }

    public function updatedChildren(): void
    {
        if (! $this->isEditing || ! $this->canEditStayDetails()) {
            return;
        }

        $this->children = $this->normalizeChildren($this->children);
        $this->syncOccupancyCaps();
    }

    public function updatedRoomSelections(): void
    {
        if (! $this->isEditing || ! $this->canEditStayDetails()) {
            return;
        }

        $this->normalizeRoomSelections();
        $this->syncOccupancyCaps();
        $this->refreshRoomAvailability();
    }

    public function updatingAdults($value)
    {
        return $this->normalizeAdults($value);
    }

    public function updatingChildren($value)
    {
        return $this->normalizeChildren($value);
    }

    public function updatingRoomSelections($value)
    {
        if (is_array($value)) {
            return collect($value)
                ->mapWithKeys(fn ($count, $roomTypeId) => [(int) $roomTypeId => min($this->maxRoomsPerType, max(0, (int) $count))])
                ->filter()
                ->toArray();
        }
    }

    public function beginEditing(): void
    {
        if ($this->isEditLocked()) {
            return;
        }

        $this->resetValidation();
        $this->resetErrorBag();
        $this->isEditing = true;
        $this->refreshRoomAvailability();
    }

    public function cancelEditing(): void
    {
        $this->resetValidation();
        $this->resetErrorBag();
        $this->isEditing = false;
        $this->populateFromReservation(true);
    }

    public function save()
    {
        if ($this->isEditLocked() || ! $this->isEditing) {
            $this->addError('status', 'This reservation cannot be edited.');
            return;
        }

        $this->validate($this->rules(), [], $this->attributes());

        $this->normalizeRoomSelections();

        if (empty($this->roomSelections)) {
            $this->addError('roomSelections', 'Please choose at least one available room.');
            return;
        }

        $selectedRooms = $this->selectedRooms;
        $roomCount = $this->roomsSelectedCount();

        if ($selectedRooms->isEmpty()) {
            $this->addError('roomSelections', 'Please choose at least one available room.');
            return;
        }

        $caps = $this->selectedRoomsCaps();

        if ($this->adults > $caps['max_adults'] || $this->children > $caps['max_children']) {
            $this->addError('roomSelections', 'Selected rooms cannot accommodate the party size.');
            return;
        }

        if ($this->children > 0 && $caps['max_children'] === 0) {
            $this->addError('roomSelections', 'Selected rooms do not allow children.');
            return;
        }

        if ($roomCount > 0 && $this->adults < $roomCount) {
            $this->addError('adults', 'Need at least one adult per room selected.');
            return;
        }

        if (($this->adults + $this->children) > $caps['capacity']) {
            $this->addError('roomSelections', 'Selected rooms cannot accommodate this many occupants.');
            return;
        }

        if ($this->adults - 1 > 0) {
            for ($i = 0; $i < $this->adults - 1; $i++) {
                if (blank($this->adultNames[$i] ?? null)) {
                    $this->addError("adultNames.$i", 'Adult names are required.');
                    return;
                }
            }
        }

        if ($this->children > 0) {
            for ($i = 0; $i < $this->children; $i++) {
                if (blank($this->childNames[$i] ?? null)) {
                    $this->addError("childNames.$i", 'Child names are required.');
                    return;
                }
            }
        }

        DB::beginTransaction();

        try {
            try {
                $checkIn = Carbon::parse($this->checkInDate)->startOfDay();
                $checkOut = Carbon::parse($this->checkOutDate)->startOfDay();
            } catch (\Throwable $e) {
                $this->addError('checkOutDate', 'Invalid dates.');
                return;
            }

            $roomIds = $this->findAvailableRoomIds($checkIn, $checkOut, $this->reservation->id);

            if (empty($roomIds)) {
                $this->addError('roomSelections', 'Selected room types are not available for these dates.');
                DB::rollBack();
                return;
            }

            $payload = [
                'check_in_date' => $this->checkInDate,
                'check_out_date' => $this->checkOutDate,
                'adults' => $this->adults,
                'children' => $this->children,
                'nightly_rate' => $caps['nightly_rate'] ?: $this->reservation->nightlyRateTotal(),
            ];

            foreach ($this->immutableFields() as $field) {
                unset($payload[$field]);
            }

            $this->reservation->update($payload);

            $syncPayload = collect($roomIds)->mapWithKeys(function ($roomId) {
                return [
                    $roomId => [
                        'hotel_id' => $this->hotelId,
                        'from_date' => $this->checkInDate,
                        'to_date' => $this->checkOutDate,
                    ],
                ];
            })->all();

            $this->reservation->rooms()->sync($syncPayload);
            $this->reservation->load('rooms');

            $this->reservation->occupants()->delete();
            $this->reservation->occupants()->createMany($this->buildOccupantsPayload());

            $folioService = app(ReservationFolioService::class);
            $roomStatusService = app(RoomStatusService::class);
            if (strtolower($this->reservation->status) === 'cancelled') {
                $folioService->applyCancellationPolicy($this->reservation, 'staff');
            } elseif (strtolower($this->reservation->status) === 'noshow') {
                $folioService->syncRoomCharges($this->reservation, 'staff no-show');
                $folioService->ensureOpenFolio($this->reservation);
                foreach ($this->reservation->rooms as $room) {
                    $roomStatusService->syncToNextReservationOrFree($room, 'Available');
                }
            } else {
                $folioService->syncRoomCharges($this->reservation, 'staff edit');
                $folio = $folioService->ensureOpenFolio($this->reservation);
                $folioService->normalizeOverpayment($folio, 'staff edit refund');
                $folioService->enforceDepositStatus($this->reservation);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            session()->flash('error', 'Unable to update the reservation right now.');
            return;
        }

        session()->flash('status', "Reservation {$this->reservation->code} updated.");

        \App\Support\AuditLogger::log('booking.update.staff', [
            'reservation_id' => $this->reservation->id,
            'hotel_id' => $this->hotelId,
            'rooms_selected' => $roomCount,
            'dates' => [$this->checkInDate, $this->checkOutDate],
        ], true, $this->reservation);

        return redirect()->route(
            'staff.reservations.show',
            array_merge([$this->reservation->refresh()], $this->backQuery)
        );
    }

    protected function rules(): array
    {
        $rules = [
            'checkOutDate' => [
                'required',
                'date',
                $this->canEditStayDetails()
                    ? 'after:checkInDate'
                    : 'after:' . optional($this->reservation->check_in_date)->toDateString(),
            ],
            'roomSelections' => ['required', 'array'],
            'roomSelections.*' => ['integer', 'min:0', 'max:' . $this->maxRoomsPerType],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['required', 'integer', 'min:0', 'max:20'],
            'adultNames' => ['array'],
            'adultNames.*' => ['nullable', 'string', 'max:255'],
            'childNames' => ['array'],
            'childNames.*' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->canEditStayDetails()) {
            $rules['checkInDate'] = ['required', 'date', 'after_or_equal:today', 'before_or_equal:checkOutDate'];
        } else {
            $rules['checkInDate'] = ['required', 'date'];
        }

        return $rules;
    }

    protected function attributes(): array
    {
        return [
            'roomSelections' => 'rooms',
            'checkInDate' => 'check-in date',
            'checkOutDate' => 'check-out date',
        ];
    }

    protected function refreshRoomAvailability(): void
    {
        $this->normalizeRoomSelections();
        $this->roomAvailability = [];

        if (! $this->hotelId) {
            return;
        }

        $roomTypes = RoomType::query()
            ->where('hotel_id', $this->hotelId)
            ->get();

        $this->roomTypeOptions = $roomTypes
            ->map(function (RoomType $type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'max_adults' => (int) $type->max_adults,
                    'max_children' => (int) $type->max_children,
                    'nightly_rate' => (float) ($type->activeRate() ?? 0),
                ];
            })
            ->values()
            ->all();

        if (blank($this->checkInDate) || blank($this->checkOutDate)) {
            return;
        }

        try {
            $checkIn = Carbon::parse($this->checkInDate)->startOfDay();
            $checkOut = Carbon::parse($this->checkOutDate)->startOfDay();
        } catch (\Throwable $e) {
            return;
        }

        if ($checkIn->gte($checkOut)) {
            $this->addError('checkOutDate', 'Check-out must be after check-in.');
            return;
        }

        $roomTotals = DB::table('rooms')
            ->selectRaw('room_type_id, COUNT(*) as total')
            ->where('hotel_id', $this->hotelId)
            ->whereNotIn('status', ['Out of Service', 'OOS'])
            ->groupBy('room_type_id')
            ->pluck('total', 'room_type_id');

        $overlaps = DB::table('reservation_room')
            ->join('reservations', 'reservations.id', '=', 'reservation_room.reservation_id')
            ->join('rooms', 'rooms.id', '=', 'reservation_room.room_id')
            ->where('reservation_room.hotel_id', $this->hotelId)
            ->where('reservation_room.to_date', '>', $checkIn->toDateString())
            ->where('reservation_room.from_date', '<', $checkOut->toDateString())
            ->where('reservations.id', '!=', $this->reservation->id)
            ->whereNotIn('reservations.status', ['Cancelled', 'NoShow'])
            ->whereNotIn('rooms.status', ['Out of Service', 'OOS'])
            ->selectRaw('rooms.room_type_id, COUNT(DISTINCT rooms.id) as booked_rooms, MIN(reservation_room.to_date) as next_checkout')
            ->groupBy('rooms.room_type_id')
            ->get()
            ->keyBy('room_type_id');

        foreach ($this->roomTypeOptions as $type) {
            $roomTypeId = $type['id'];
            $total = (int) ($roomTotals[$roomTypeId] ?? 0);
            $booked = (int) ($overlaps[$roomTypeId]->booked_rooms ?? 0);
            $nextCheckout = $overlaps[$roomTypeId]->next_checkout ?? null;
            $requested = (int) ($this->roomSelections[$roomTypeId] ?? 0);

            if ($total === 0) {
                $this->roomAvailability[$roomTypeId] = [
                    'available' => false,
                    'available_count' => 0,
                    'status' => 'unavailable',
                    'message' => 'No rooms of this type at this hotel.',
                ];
                continue;
            }

            $availableCount = max(0, $total - $booked);

            if ($availableCount <= 0) {
                $message = $nextCheckout
                    ? 'Fully booked for these dates. Next availability starts ' . Carbon::parse($nextCheckout)->toDateString() . '.'
                    : 'Fully booked for these dates. Try adjusting the dates.';

                $this->roomAvailability[$roomTypeId] = [
                    'available' => false,
                    'available_count' => 0,
                    'status' => 'unavailable',
                    'message' => $message,
                ];
                $this->roomSelections[$roomTypeId] = 0;
                continue;
            }

            $message = null;
            $status = $booked > 0 ? 'limited' : 'available';
            $meetsRoomCount = $requested <= $availableCount;

            if (! $meetsRoomCount && $requested > 0) {
                $status = 'limited';
                $message = "Only {$availableCount} room" . ($availableCount === 1 ? '' : 's') . " left for these dates.";
                $this->roomSelections[$roomTypeId] = min($availableCount, $this->maxRoomsPerType);
            }

            if ($booked > 0 && $nextCheckout) {
                $message = $message
                    ?? 'Limited availability. Try starting after ' . Carbon::parse($nextCheckout)->toDateString() . ' for more options.';
            }

            $this->roomAvailability[$roomTypeId] = [
                'available' => $meetsRoomCount,
                'available_count' => $availableCount,
                'status' => $status,
                'message' => $message,
            ];
        }

        $this->syncOccupancyCaps();
    }

    protected function populateFromReservation(bool $reload = false): void
    {
        if ($reload) {
            $this->reservation->refresh();
        }

        $this->reservation->loadMissing(['customer.user', 'rooms.roomType', 'occupants']);

        $this->status = $this->reservation->status;
        $this->checkInDate = optional($this->reservation->check_in_date)->toDateString() ?? '';
        $this->checkOutDate = optional($this->reservation->check_out_date)->toDateString() ?? '';
        $this->adults = max(1, (int) $this->reservation->adults);
        $this->children = max(0, (int) $this->reservation->children);
        $this->roomSelections = $this->reservation->rooms
            ->groupBy('room_type_id')
            ->map(fn ($group) => $group->count())
            ->mapWithKeys(fn ($count, $typeId) => [(int) $typeId => (int) $count])
            ->toArray();

        $customer = $this->reservation->customer;

        $this->primaryGuestName = $customer?->name
            ?? $customer?->user?->name
            ?? 'Primary guest';

        $this->primaryGuestEmail = $customer?->email
            ?? $customer?->user?->email
            ?? '';

        $this->hydrateOccupantInputs();
        $this->normalizeRoomSelections();
        $this->refreshRoomAvailability();
    }

    protected function hydrateOccupantInputs(): void
    {
        // Occupants storage has evolved:
        // - New model: primary guest is stored as the first Adult row, then additional adults, then children.
        // - Legacy model: ONLY additional guests were stored (no primary guest row).
        // This hydrator supports both.

        $adults = $this->reservation->occupants
            ->where('type', 'Adult')
            ->sortBy('id')
            ->values();

        $children = $this->reservation->occupants
            ->where('type', 'Child')
            ->sortBy('id')
            ->values();

        $adultCountNeeded = max(0, (int) $this->adults - 1);

        // Determine whether the first adult row is the primary guest.
        // Treat as primary if:
        //  - it matches the primaryGuestName (case-insensitive), OR
        //  - the number of adult occupant rows equals the total adults (implying primary is included).
        $firstAdultName = (string) ($adults->first()?->full_name ?? '');
        $primaryName = (string) ($this->primaryGuestName ?? '');

        $hasPrimaryRow = false;
        if ($firstAdultName !== '' && $primaryName !== '' && strcasecmp(trim($firstAdultName), trim($primaryName)) === 0) {
            $hasPrimaryRow = true;
        } elseif ($adults->count() === (int) $this->adults && (int) $this->adults > 0) {
            $hasPrimaryRow = true;
        }

        $additionalAdults = $hasPrimaryRow ? $adults->skip(1) : $adults;

        $this->adultNames = $additionalAdults
            ->pluck('full_name')
            ->take($adultCountNeeded)
            ->all();

        $this->childNames = $children
            ->pluck('full_name')
            ->take(max(0, (int) $this->children))
            ->all();

        $this->ensureAdultInputsCount();
        $this->ensureChildInputsCount();
    }

    /**
     * @return array<int>
     */
    protected function findAvailableRoomIds(Carbon $checkIn, Carbon $checkOut, ?int $excludeReservationId = null): array
    {
        if (empty($this->roomSelections)) {
            return [];
        }

        $ids = [];

        foreach ($this->selectedRoomTypeCounts() as $roomType) {
            $needed = max(0, (int) ($roomType['count'] ?? 0));
            if ($needed < 1) {
                continue;
            }

            $typeIds = Room::query()
                ->where('hotel_id', $this->hotelId)
                ->where('room_type_id', $roomType['id'])
                ->whereNotIn('status', ['Out of Service', 'OOS'])
                ->whereDoesntHave('reservationRooms', function ($query) use ($checkIn, $checkOut, $excludeReservationId) {
                    $query->where('from_date', '<', $checkOut->toDateString())
                        ->where('to_date', '>', $checkIn->toDateString())
                        ->whereHas('reservation', function ($reservation) use ($excludeReservationId) {
                            $reservation->whereNotIn('status', ['Cancelled', 'NoShow']);

                            if ($excludeReservationId) {
                                $reservation->where('id', '!=', $excludeReservationId);
                            }
                        });
                })
                ->orderBy('number')
                ->limit($needed)
                ->pluck('id')
                ->all();

            if (count($typeIds) !== $needed) {
                return [];
            }

            $ids = array_merge($ids, $typeIds);
        }

        return $ids;
    }

    protected function ensureAdultInputsCount(): void
    {
        $target = max(0, $this->adults - 1);
        $current = count($this->adultNames);

        if ($current < $target) {
            $this->adultNames = array_merge($this->adultNames, array_fill(0, $target - $current, ''));
        } elseif ($current > $target) {
            $this->adultNames = array_slice($this->adultNames, 0, $target);
        }
    }

    protected function ensureChildInputsCount(): void
    {
        $target = max(0, $this->children);
        $current = count($this->childNames);

        if ($current < $target) {
            $this->childNames = array_merge($this->childNames, array_fill(0, $target - $current, ''));
        } elseif ($current > $target) {
            $this->childNames = array_slice($this->childNames, 0, $target);
        }
    }

    protected function normalizeRoomSelections(): void
    {
        $this->roomSelections = collect($this->roomSelections)
            ->mapWithKeys(fn ($count, $roomTypeId) => [(int) $roomTypeId => min($this->maxRoomsPerType, max(0, (int) $count))])
            ->filter()
            ->toArray();
    }

    private function normalizeAdults($value): int
    {
        $int = (int) ($value ?? 0);

        return max(1, min(20, $int));
    }

    private function normalizeChildren($value): int
    {
        $int = (int) ($value ?? 0);

        return max(0, min(20, $int));
    }

    protected function roomsSelectedCount(): int
    {
        return (int) collect($this->roomSelections)->sum(fn ($count) => (int) $count);
    }

    protected function selectedRoomTypeCounts()
    {
        $options = collect($this->roomTypeOptions)->keyBy('id');

        return collect($this->roomSelections)
            ->filter(fn ($count) => (int) $count > 0)
            ->map(function ($count, $typeId) use ($options) {
                $type = $options->get((int) $typeId);
                if (! $type) {
                    return null;
                }

                return array_merge($type, [
                    'count' => (int) $count,
                ]);
            })
            ->filter()
            ->values();
    }

    protected function selectedRoomEntries()
    {
        $options = collect($this->roomTypeOptions)->keyBy('id');
        $entries = collect();

        foreach ($this->roomSelections as $typeId => $count) {
            $type = $options->get((int) $typeId);
            if (! $type) {
                continue;
            }

            for ($i = 0; $i < (int) $count; $i++) {
                $entries->push([
                    'id' => (int) $typeId,
                    'label' => $type['name'],
                    'max_adults' => (int) $type['max_adults'],
                    'max_children' => (int) $type['max_children'],
                    'nightly_rate' => (float) $type['nightly_rate'],
                ]);
            }
        }

        return $entries;
    }

    protected function selectedRoomsCaps(): array
    {
        $selected = $this->selectedRoomTypeCounts();

        return [
            'max_adults' => (int) $selected->sum(fn ($room) => ($room['max_adults'] ?? 0) * ($room['count'] ?? 0)),
            'max_children' => (int) $selected->sum(fn ($room) => ($room['max_children'] ?? 0) * ($room['count'] ?? 0)),
            'capacity' => (int) $selected->sum(fn ($room) => (($room['max_adults'] ?? 0) + ($room['max_children'] ?? 0)) * ($room['count'] ?? 0)),
            'nightly_rate' => (float) $selected->sum(fn ($room) => ($room['nightly_rate'] ?? 0) * ($room['count'] ?? 0)),
        ];
    }

    protected function syncOccupancyCaps(): void
    {
        $caps = $this->selectedRoomsCaps();

        $maxAdults = max(1, (int) ($caps['max_adults'] ?? 1));
        $maxChildren = max(0, (int) ($caps['max_children'] ?? 0));

        $this->adults = min(max(1, (int) $this->adults), $maxAdults);
        $this->children = min(max(0, (int) $this->children), $maxChildren);

        $minAdults = max(1, $this->roomsSelectedCount());
        $this->adults = min($maxAdults, max($minAdults, (int) $this->adults));

        $this->ensureAdultInputsCount();
        $this->ensureChildInputsCount();
    }

    protected function buildOccupantsPayload(): array
    {
        $occupants = [[
            'full_name' => $this->primaryGuestName ?: 'Primary guest',
            'type' => 'Adult',
        ]];

        foreach ($this->adultNames as $name) {
            if (filled($name)) {
                $occupants[] = [
                    'full_name' => $name,
                    'type' => 'Adult',
                ];
            }
        }

        foreach ($this->childNames as $name) {
            if (filled($name)) {
                $occupants[] = [
                    'full_name' => $name,
                    'type' => 'Child',
                ];
            }
        }

        return $occupants;
    }

    protected function immutableFields(): array
    {
        $fields = [];
        $status = strtolower($this->reservation->status);
        if ($status === 'checkedin') {
            $fields = ['status', 'check_in_date', 'adults', 'children'];
        }

        return $fields;
    }

    protected function canEditStayDetails(): bool
    {
        $status = strtolower($this->reservation->status);

        if (in_array($status, ['checkedin', 'checkedout', 'cancelled', 'noshow'], true)) {
            return false;
        }

        return true;
    }

    protected function isEditLocked(): bool
    {
        $status = strtolower($this->reservation->status);

        return in_array($status, ['checkedout', 'cancelled', 'noshow'], true);
    }

    protected function statusOptionsFor(string $currentStatus): array
    {
        return [$currentStatus];
    }

    public function getSelectedRoomsProperty()
    {
        return $this->selectedRoomEntries();
    }

    protected function displayedNightlyRate(): float
    {
        $selectedRate = (float) $this->selectedRooms->sum('nightly_rate');

        if ($selectedRate > 0) {
            return $selectedRate;
        }

        return $this->reservation->nightlyRateTotal();
    }
}
