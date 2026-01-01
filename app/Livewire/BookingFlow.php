<?php

namespace App\Livewire;

use App\Models\CustomerUser;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationOccupant;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Support\ReservationFolioService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class BookingFlow extends Component
{
    private int $maxRoomsPerType = 10;

    protected $casts = [
        'selectedHotelId' => 'integer',
        'roomSelections' => 'array',
        'roomOccupants' => 'array',
        'adults' => 'integer',
        'children' => 'integer',
        'step' => 'integer',
    ];

    public array $countries = [];

    /** @var array<int,array<string,mixed>> */
    public array $hotels = [];

    public string $selectedCountry = '';
    public ?int $selectedHotelId = null;
    /** @var array<int,int> room_type_id => count */
    public array $roomSelections = [];

    public $adults = 1;
    public $children = 0;
    public array $guestNames = [];

    public ?string $checkIn = null;
    public ?string $checkOut = null;

    public int $step = 0;

    public ?string $statusMessage = null;
    public ?string $errorMessage = null;

    public bool $confirmed = false;
    public array $roomAvailability = [];
    public ?array $reservationSummary = null;
    public ?int $reservationId = null;
    /** @var array<int,array<string,int>> */
    public array $roomOccupants = [];

    public function mount(): void
    {
        $hotels = Hotel::query()
            ->with([
                'roomTypes:id,hotel_id,name,max_adults,max_children,base_occupancy,price_off_peak,price_peak,active_rate',
                'country:id,code,name',
                'timezone:id,country_code,timezone',
            ])
            ->get(['id', 'name', 'code', 'country_code', 'timezone_id']);

        $hotels = $hotels
            ->sortBy(fn (Hotel $hotel) => $hotel->name)
            ->sortBy(fn (Hotel $hotel) => $hotel->country?->name ?? '');

        $this->hotels = $hotels->map(function (Hotel $hotel) {
            return [
                'id' => $hotel->id,
                'name' => $hotel->name,
                'code' => $hotel->code,
                'country' => $hotel->country?->name,
                'country_code' => $hotel->country?->code,
                'timezone' => $hotel->timezone?->timezone,
                'room_types' => $hotel->roomTypes->map(function ($roomType) {
                    return [
                        'id' => $roomType->id,
                        'name' => $roomType->name,
                        'max_adults' => $roomType->max_adults,
                        'max_children' => $roomType->max_children,
                        'base_occupancy' => $roomType->base_occupancy,
                        'price_off_peak' => $roomType->price_off_peak,
                        'price_peak' => $roomType->price_peak,
                        'active_rate' => $roomType->active_rate,
                        'active_price' => $roomType->activeRate(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        $this->countries = collect($this->hotels)->pluck('country')->unique()->values()->all();
        $this->syncRoomOccupants();
        $this->syncGuestNames();
        $this->refreshAvailability();
    }

    #[Computed]
    public function availableHotels(): Collection
    {
        return collect($this->hotels)
            ->when($this->selectedCountry !== '', fn ($q) => $q->where('country', $this->selectedCountry))
            ->values();
    }

    #[Computed]
    public function selectedHotel(): ?array
    {
        return collect($this->availableHotels())->firstWhere('id', $this->selectedHotelId);
    }

    #[Computed]
    public function availableRoomTypes(): Collection
    {
        return collect($this->selectedHotel()['room_types'] ?? [])->values();
    }

    #[Computed]
    public function selectedRoomTypes(): Collection
    {
        return $this->availableRoomTypes()->filter(function ($room) {
            return ($this->roomSelections[$room['id']] ?? 0) > 0;
        })->values();
    }

    #[Computed]
    public function firstSelectedRoomType(): ?array
    {
        return $this->selectedRoomTypes()->first();
    }

    /**
     * Backward-compatible helper used by existing views.
     */
    public function selectedRoomType(): ?array
    {
        return $this->firstSelectedRoomType();
    }

    #[Computed]
    public function stepIds(): array
    {
        // Reordered to capture dates first, then location/hotel/room
        return ['dates', 'country', 'hotel', 'room', 'guests', 'names'];
    }

    #[Computed]
    public function stepCount(): int
    {
        return count($this->stepIds());
    }

    public function totalGuests(): int
    {
        return max(0, $this->adults) + max(0, $this->children);
    }

    public function namesNeeded(): int
    {
        return max($this->totalGuests() - 1, 0);
    }

    public function updatedSelectedCountry(): void
    {
        $this->selectedHotelId = null;
        $this->roomSelections = [];
        $this->resetGuests();
        $this->syncGuestNames();
        $this->confirmed = false;
        $this->reservationSummary = null;
        $this->refreshAvailability();
    }

    public function updatedSelectedHotelId($value = null): void
    {
        $this->selectedHotelId = $value ? (int) $value : null;
        $this->roomSelections = [];
        $this->resetGuests();
        $this->syncGuestNames();
        $this->confirmed = false;
        $this->reservationSummary = null;
        $this->refreshAvailability();
    }

    public function updatedRoomSelections($value = null, $key = null): void
    {
        // Normalize to integers and drop zeros/negatives.
        $this->roomSelections = collect($this->roomSelections)
            ->map(fn ($count) => min($this->maxRoomsPerType, max(0, (int) $count)))
            ->filter()
            ->toArray();

        // Ensure at least one selection if user typed a value then cleared.
        if (empty($this->roomSelections)) {
            $this->roomSelections = [];
        }

        $this->syncRoomOccupants();
        $this->recalculateGuestTotals();
        $this->clampGuestCounts();
        $this->syncGuestNames();
        $this->confirmed = false;
        $this->reservationSummary = null;
        $this->refreshAvailability();
    }

    public function updatedAdults(): void
    {
        $this->adults = $this->normalizeAdults($this->adults);
        $this->clampGuestCounts();
        $this->syncGuestNames();
        $this->confirmed = false;
        $this->reservationSummary = null;
        $this->refreshAvailability();
    }

    public function updatedChildren(): void
    {
        $this->children = $this->normalizeChildren($this->children);
        $this->clampGuestCounts();
        $this->syncGuestNames();
        $this->confirmed = false;
        $this->reservationSummary = null;
        $this->refreshAvailability();
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
                ->map(fn ($count) => min($this->maxRoomsPerType, max(0, (int) $count)))
                ->filter()
                ->toArray();
        }
    }

    public function updatedCheckIn(): void
    {
        $this->confirmed = false;
        $this->refreshAvailability();
    }

    public function updatedCheckOut(): void
    {
        $this->confirmed = false;
        $this->refreshAvailability();
    }

    public function back(): void
    {
        $this->errorMessage = null;
        $this->statusMessage = null;
        $this->step = max(0, $this->step - 1);
    }

    public function next(): void
    {
        $this->errorMessage = null;
        $this->statusMessage = null;
        $this->reservationSummary = null;

        $currentStepId = $this->stepIds()[$this->step] ?? null;
        if (!$currentStepId) {
            return;
        }

        $error = $this->validateStep($currentStepId);
        if ($error) {
            $this->errorMessage = $error;
            return;
        }

        $this->step = min($this->step + 1, $this->stepCount - 1);
    }

    public function confirm()
    {
        $this->errorMessage = null;
        $this->statusMessage = null;
        $this->reservationSummary = null;
        $currentStepId = $this->stepIds()[$this->step] ?? null;
        $error = $this->validateStep($currentStepId ?? 'dates');
        if ($error) {
            $this->errorMessage = $error;
            return;
        }

        $roomIds = $this->findAvailableRoomIds();
        $requiredRooms = $this->roomsSelected();
        if (count($roomIds) !== $requiredRooms) {
            $this->errorMessage = 'Selected rooms are no longer available for these dates. Please adjust counts or choose other room types.';
            return;
        }

        $user = Auth::user();
        if (! $user) {
            $this->errorMessage = 'You must be logged in to book.';
            return;
        }

        // Draft review only (no persistence)
        $this->confirmed = true;
        $hotel = $this->selectedHotel() ?? [];
        $selectedTypes = $this->selectedRoomTypeCounts()->map(function ($type) {
            return [
                'name' => $type['name'] ?? 'Room',
                'count' => (int) ($type['count'] ?? 0),
            ];
        })->values()->all();
        $this->reservationSummary = [
            'code' => null,
            'hotel' => $hotel['name'] ?? null,
            'room_types' => $selectedTypes,
            'room_count' => $requiredRooms,
            'dates' => "{$this->checkIn} → {$this->checkOut}",
            'guests' => trim("{$this->adults} adults / {$this->children} children"),
        ];
    }

    public function editDraft(): void
    {
        $this->confirmed = false;
        $this->statusMessage = null;
    }

    public function finalizeBooking()
    {
        $this->errorMessage = null;
        $this->statusMessage = null;

        $currentStepId = $this->stepIds()[$this->step] ?? null;
        $error = $this->validateStep($currentStepId ?? 'dates');
        if ($error) {
            $this->errorMessage = $error;
            return;
        }

        $user = Auth::user();
        if (! $user) {
            $this->errorMessage = 'You must be logged in to book.';
            return;
        }

        // Server-side guardrails to avoid oversized payloads or invalid references.
        $this->validate($this->persistenceRules());
        $this->guestNames = collect($this->guestNames)
            ->map(fn ($name) => trim((string) $name))
            ->toArray();

        $customer = CustomerUser::firstWhere('user_id', $user->id);
        if ($customer) {
            $outstanding = app(ReservationFolioService::class)->outstandingNoShowForCustomer($customer->id);
            if ($outstanding) {
                $this->errorMessage = 'You have an unpaid no-show balance. Please settle it before making new bookings.';
                return;
            }
        }

        $roomIds = $this->findAvailableRoomIds();
        $requiredRooms = $this->roomsSelected();
        if (count($roomIds) !== $requiredRooms) {
            $this->errorMessage = 'Selected rooms are no longer available for these dates. Please adjust counts or choose other room types.';
            return;
        }

        DB::beginTransaction();
        try {
            $customer = CustomerUser::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            );

            $reservation = Reservation::create([
                'hotel_id' => $this->selectedHotelId,
                'customer_id' => $customer->id,
                'status' => 'Pending',
                'check_in_date' => $this->checkIn,
                'check_out_date' => $this->checkOut,
                'adults' => $this->adults,
                'children' => $this->children,
                'nightly_rate' => $this->totalNightlyRate(),
            ]);

            foreach ($roomIds as $roomId) {
                $reservation->reservationRooms()->create([
                    'hotel_id' => $this->selectedHotelId,
                    'room_id' => $roomId,
                    'from_date' => $this->checkIn,
                    'to_date' => $this->checkOut,
                ]);
            }

            $extraAdults = max($this->adults - 1, 0);
            foreach ($this->guestNames as $index => $name) {
                $trimmed = trim((string) $name);
                if ($trimmed === '') {
                    continue;
                }

                ReservationOccupant::create([
                    'reservation_id' => $reservation->id,
                    'full_name' => $trimmed,
                    'type' => $index < $extraAdults ? 'Adult' : 'Child',
                ]);
            }

            app(ReservationFolioService::class)->syncRoomCharges($reservation, 'initial booking');

            DB::commit();

            \App\Support\AuditLogger::log('booking.create.success', [
                'reservation_id' => $reservation->id,
                'hotel_id' => $this->selectedHotelId,
                'rooms_selected' => $this->roomsSelected(),
                'dates' => [$this->checkIn, $this->checkOut],
            ], true, $reservation);

            return redirect()
                ->route('bookings.pay', $reservation->id)
                ->with('status', 'Reservation saved — finish payment to confirm.');
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            $this->errorMessage = 'We could not save your booking. Please try again or adjust your selection.';
            \App\Support\AuditLogger::log('booking.create.failed', [
                'hotel_id' => $this->selectedHotelId,
                'rooms' => $this->roomsSelected(),
                'dates' => [$this->checkIn, $this->checkOut],
            ], false);
        }
    }

    public function render()
    {
        $stepIds = $this->stepIds();
        $stepCount = $this->stepCount;
        return view('livewire.booking-flow', [
            'stepIds' => $stepIds,
            'stepCount' => $stepCount,
            'progressPercent' => $stepCount > 0 ? (int) round((($this->step + 1) / $stepCount) * 100) : 0,
            'roomTypeSummary' => $this->firstSelectedRoomType(),
            'shouldAutoSkipNames' => $this->currentStepId() === 'names' && $this->namesNeeded() === 0,
        ]);
    }

    /**
     * Extra validation for persistence to guard against oversized or tampered input.
     *
     * @return array<string,mixed>
     */
    protected function persistenceRules(): array
    {
        return [
            'selectedCountry' => ['required', 'string', 'max:100'],
            'selectedHotelId' => ['required', 'integer', 'exists:hotels,id'],
            'checkIn' => ['required', 'date', 'after_or_equal:today'],
            'checkOut' => ['required', 'date', 'after:checkIn'],
            'roomSelections' => ['required', 'array', 'min:1'],
            'roomSelections.*' => ['integer', 'min:0', 'max:' . $this->maxRoomsPerType],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['required', 'integer', 'min:0', 'max:20'],
            'guestNames' => ['array'],
            'guestNames.*' => ['nullable', 'string', 'max:150'],
        ];
    }

    private function resetGuests(): void
    {
        $this->adults = 1;
        $this->children = 0;
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

    private function roomAllowsChildren(array $roomType): bool
    {
        return ($roomType['max_children'] ?? 0) > 0;
    }

    private function roomCapacity(array $roomType): int
    {
        $base = $roomType['base_occupancy'] ?? 0;
        $max = ($roomType['max_adults'] ?? 0) + ($roomType['max_children'] ?? 0);
        $capacity = max($base, $max);

        // Business rule override: Family Suite and Penthouse max 4 total occupants (adults + children)
        $name = strtolower($roomType['name'] ?? '');
        if (str_contains($name, 'family suite') || str_contains($name, 'penthouse')) {
            $capacity = min($capacity, 4);
        }

        return $capacity;
    }

    private function selectedRoomTypeCounts(): Collection
    {
        $counts = $this->roomSelections;

        return $this->availableRoomTypes()
            ->filter(fn ($room) => ($counts[$room['id']] ?? 0) > 0)
            ->map(function ($room) use ($counts) {
                return array_merge($room, ['count' => (int) ($counts[$room['id']] ?? 0)]);
            })
            ->values();
    }

    /**
     * Flattened list of room "slots" for occupant assignment.
     *
     * @return array<int,array<string,mixed>>
     */
    private function roomSlots(): array
    {
        $slots = [];
        foreach ($this->selectedRoomTypeCounts() as $roomType) {
            $count = (int) ($roomType['count'] ?? 0);
            for ($i = 1; $i <= $count; $i++) {
                $slots[] = [
                    'type_id' => $roomType['id'],
                    'type_name' => $roomType['name'] ?? 'Room',
                    'index' => $i,
                    'max_adults' => (int) ($roomType['max_adults'] ?? 1),
                    'max_children' => (int) ($roomType['max_children'] ?? 0),
                    'capacity' => $this->roomCapacity($roomType),
                ];
            }
        }

        return $slots;
    }

    private function roomsSelected(): int
    {
        return (int) $this->selectedRoomTypeCounts()->sum('count');
    }

    private function recalculateGuestTotals(): void
    {
        $adults = 0;
        $children = 0;

        foreach ($this->roomOccupants as $occupant) {
            $adults += (int) ($occupant['adults'] ?? 0);
            $children += (int) ($occupant['children'] ?? 0);
        }

        $this->adults = max(0, $adults);
        $this->children = max(0, $children);
    }

    private function totalCapacity(): int
    {
        return (int) $this->selectedRoomTypeCounts()->sum(function ($room) {
            return $this->roomCapacity($room) * max(1, (int) ($room['count'] ?? 0));
        });
    }

    private function maxAdults(): int
    {
        return (int) $this->selectedRoomTypeCounts()->sum(function ($room) {
            return max(1, (int) ($room['max_adults'] ?? 1)) * max(1, (int) ($room['count'] ?? 0));
        });
    }

    private function maxChildren(): int
    {
        return (int) $this->selectedRoomTypeCounts()->sum(function ($room) {
            return max(0, (int) ($room['max_children'] ?? 0)) * max(1, (int) ($room['count'] ?? 0));
        });
    }

    private function totalNightlyRate(): float
    {
        return (float) $this->selectedRoomTypeCounts()->sum(function ($room) {
            return (float) (($room['active_price'] ?? 0) * max(1, (int) ($room['count'] ?? 0)));
        });
    }

    private function syncRoomOccupants(): void
    {
        $slots = $this->roomSlots();
        $existing = $this->roomOccupants;
        $this->roomOccupants = [];

        foreach ($slots as $idx => $slot) {
            $key = $slot['type_id'] . '-' . $slot['index'];
            $current = $existing[$key] ?? ['adults' => 1, 'children' => 0];

            $clamped = $this->clampOccupantValues($current, $slot);

            $this->roomOccupants[$key] = array_merge(
                ['adults' => $clamped['adults'], 'children' => $clamped['children']],
                ['type_id' => $slot['type_id'], 'type_name' => $slot['type_name'], 'index' => $slot['index']]
            );
        }
    }

    private function clampOccupantValues(array $occupant, array $slot): array
    {
        $maxAdults = max(1, (int) ($slot['max_adults'] ?? 1));
        $maxChildren = max(0, (int) ($slot['max_children'] ?? 0));
        $capacity = max(1, (int) ($slot['capacity'] ?? ($maxAdults + $maxChildren)));

        $adults = min(max(1, (int) ($occupant['adults'] ?? 1)), $maxAdults);
        $children = min(max(0, (int) ($occupant['children'] ?? 0)), $maxChildren);

        if ($adults + $children > $capacity) {
            $overflow = ($adults + $children) - $capacity;
            $children = max(0, $children - $overflow);
        }

        if ($maxChildren === 0) {
            $children = 0;
        }

        return ['adults' => $adults, 'children' => $children];
    }

    private function clampGuestCounts(): void
    {
        $this->recalculateGuestTotals();
    }

    private function syncGuestNames(): void
    {
        $this->recalculateGuestTotals();

        $needed = $this->namesNeeded();
        $existing = $this->guestNames;
        $this->guestNames = [];
        for ($i = 0; $i < $needed; $i++) {
            $this->guestNames[$i] = $existing[$i] ?? '';
        }

        // Adjust children visibility when switching rooms
        if ($this->maxChildren() === 0) {
            $this->children = 0;
        }

        // Ensure step index is valid after names step toggles
        $this->step = min($this->step, $this->stepCount - 1);
    }

    public function updatedRoomOccupants(): void
    {
        $this->syncRoomOccupants();
        $this->recalculateGuestTotals();
        $this->syncGuestNames();
    }

    public function updatingRoomOccupants($value): void
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $occupant) {
                $normalized[$key] = [
                    'adults' => $this->normalizeAdults($occupant['adults'] ?? 1),
                    'children' => $this->normalizeChildren($occupant['children'] ?? 0),
                ];
            }
            $this->roomOccupants = $normalized;
        }
    }

    private function currentStepId(): string
    {
        return $this->stepIds()[$this->step] ?? '';
    }

    private function validateStep(string $stepId): ?string
    {
        switch ($stepId) {
            case 'country':
                if ($this->selectedCountry === '') {
                    return 'Please choose a country to continue.';
                }
                break;
            case 'hotel':
                if (!$this->selectedHotelId) {
                    return 'Please choose a hotel before moving on.';
                }
                break;
            case 'room':
                if ($this->roomsSelected() < 1) {
                    return 'Select at least one room to proceed.';
                }

                foreach ($this->selectedRoomTypeCounts() as $roomType) {
                    $availability = $this->roomAvailability[$roomType['id']] ?? null;
                    $requested = (int) ($roomType['count'] ?? 0);
                    if ($requested < 1) {
                        return 'Select at least one room to proceed.';
                    }
                    if ($availability && !($availability['available'] ?? true)) {
                        $availableCount = (int) ($availability['available_count'] ?? 0);
                        if ($availableCount > 0 && $availableCount < $requested) {
                            return "Only {$availableCount} room" . ($availableCount === 1 ? '' : 's') . " left for {$roomType['name']}.";
                        }

                        return "{$roomType['name']} is unavailable for these dates. Please adjust dates or choose another room.";
                    }
                }
                break;
            case 'guests':
                if ($this->roomsSelected() < 1) {
                    return 'Select at least one room before setting guests.';
                }

                foreach ($this->roomSlots() as $slot) {
                    $key = $slot['type_id'] . '-' . $slot['index'];
                    $occupant = $this->roomOccupants[$key] ?? ['adults' => 0, 'children' => 0];
                    $adults = (int) ($occupant['adults'] ?? 0);
                    $children = (int) ($occupant['children'] ?? 0);
                    $capacity = $slot['capacity'] ?? ($slot['max_adults'] + $slot['max_children']);

                    if ($adults < 1) {
                        return "{$slot['type_name']} room {$slot['index']} must have at least one adult.";
                    }

                    if ($adults > $slot['max_adults']) {
                        return "{$slot['type_name']} room {$slot['index']} allows up to {$slot['max_adults']} adults.";
                    }

                    if ($children > $slot['max_children']) {
                        return "{$slot['type_name']} room {$slot['index']} allows up to {$slot['max_children']} children.";
                    }

                    if (($adults + $children) > $capacity) {
                        return "{$slot['type_name']} room {$slot['index']} sleeps up to {$capacity}. Reduce guests for that room.";
                    }
                }
                break;
            case 'names':
                $needed = $this->namesNeeded();
                if ($needed > 0) {
                    if (count($this->guestNames) < $needed) {
                        return 'Please provide a name for each guest.';
                    }
                    foreach ($this->guestNames as $name) {
                        if (trim($name) === '') {
                            return 'All guest name fields are required.';
                        }
                    }
                }
                break;
            case 'dates':
                if (!$this->checkIn || !$this->checkOut) {
                    return 'Select both check-in and check-out dates.';
                }
                $in = strtotime($this->checkIn);
                $out = strtotime($this->checkOut);
                $today = strtotime('today');
                if ($in < $today) {
                    return 'Check-in cannot be in the past.';
                }
                if ($out <= $in) {
                    return 'Check-out must be after check-in.';
                }
                break;
            default:
                break;
        }

        return null;
    }

    /**
     * @return array<int>
     */
    private function findAvailableRoomIds(): array
    {
        if (! $this->selectedHotelId || ! $this->checkIn || ! $this->checkOut || $this->roomsSelected() < 1) {
            return [];
        }

        $from = $this->checkIn;
        $to = $this->checkOut;

        $ids = [];

        foreach ($this->selectedRoomTypeCounts() as $roomType) {
            $needed = max(1, (int) ($roomType['count'] ?? 0));
            $typeIds = Room::query()
                ->where('hotel_id', $this->selectedHotelId)
                ->where('room_type_id', $roomType['id'])
                ->whereNotIn('status', ['Out of Service', 'OOS'])
                ->whereDoesntHave('reservationRooms', function ($query) use ($from, $to) {
                    $query->where('to_date', '>', $from)
                        ->where('from_date', '<', $to)
                        ->whereHas('reservation', fn ($r) => $r->where('status', '!=', 'Cancelled'));
                })
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

    private function refreshAvailability(): void
    {
        $this->roomAvailability = [];

        if (!$this->selectedHotelId || !$this->checkIn || !$this->checkOut) {
            return;
        }

        $hotelId = $this->selectedHotelId;
        $from = $this->checkIn;
        $to = $this->checkOut;

        $roomTotals = DB::table('rooms')
            ->selectRaw('room_type_id, COUNT(*) as total')
            ->where('hotel_id', $hotelId)
            ->whereNotIn('status', ['Out of Service', 'OOS'])
            ->groupBy('room_type_id')
            ->pluck('total', 'room_type_id');

        $overlaps = DB::table('reservation_room')
            ->join('reservations', 'reservations.id', '=', 'reservation_room.reservation_id')
            ->join('rooms', 'rooms.id', '=', 'reservation_room.room_id')
            ->where('reservation_room.hotel_id', $hotelId)
            ->where('reservation_room.to_date', '>', $from)
            ->where('reservation_room.from_date', '<', $to)
            ->whereNotIn('reservations.status', ['Cancelled'])
            ->whereNotIn('rooms.status', ['Out of Service', 'OOS'])
            ->selectRaw('rooms.room_type_id, COUNT(DISTINCT rooms.id) as booked_rooms, MIN(reservation_room.to_date) as next_checkout')
            ->groupBy('rooms.room_type_id')
            ->get()
            ->keyBy('room_type_id');

        foreach ($this->availableRoomTypes() as $roomType) {
            $roomTypeId = $roomType['id'];
            $total = (int) ($roomTotals[$roomTypeId] ?? 0);
            $booked = (int) ($overlaps[$roomTypeId]->booked_rooms ?? 0);
            $nextCheckout = $overlaps[$roomTypeId]->next_checkout ?? null;
            $requested = max(1, (int) ($this->roomSelections[$roomTypeId] ?? 1));

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
                continue;
            }

            $message = null;
            $status = $booked > 0 ? 'limited' : 'available';
            $meetsRoomCount = $availableCount >= $requested;

            if (! $meetsRoomCount) {
                $status = 'limited';
                $message = "Only {$availableCount} room" . ($availableCount === 1 ? '' : 's') . " left for these dates.";
                // Clamp selection to available count to keep UI in sync.
                if (($this->roomSelections[$roomTypeId] ?? 0) > $availableCount) {
                    $this->roomSelections[$roomTypeId] = min($availableCount, $this->maxRoomsPerType);
                }
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
    }
}
