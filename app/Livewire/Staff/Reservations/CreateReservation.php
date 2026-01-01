<?php

namespace App\Livewire\Staff\Reservations;

use App\Models\CustomerUser;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CreateReservation extends Component
{
    protected $casts = [
        'customerId' => 'integer',
        'hotelId' => 'integer',
        'adults' => 'integer',
        'children' => 'integer',
        'roomSelections' => 'array',
    ];

    /** @var array<int,array<string,mixed>> */
    public array $roomTypeOptions = [];

    /** @var array<int,array<string,mixed>> */
    public array $roomAvailability = [];

    public $customerId = null;

    public string $checkInDate = '';

    public string $checkOutDate = '';

    /** @var array<int,int> room_type_id => count */
    public array $roomSelections = [];

    public $adults = 1;

    public $children = 0;

    /** @var array<int,string> */
    public array $adultNames = [];

    /** @var array<int,string> */
    public array $childNames = [];

    public string $primaryGuestName = '';

    public string $primaryGuestEmail = '';

    public ?int $hotelId = null;

    public string $hotelName = '';

    private int $maxRoomsPerType = 10;

    public function mount(): void
    {
        $staff = auth('staff')->user();

        abort_unless($staff, 403);

        $this->hotelId = $staff->hotel_id;
        $this->hotelName = $staff->hotel->name ?? 'Hotel';
        $this->refreshRoomAvailability();
    }

    public function updatedCustomerId(): void
    {
        $this->syncPrimaryGuest();
    }

    public function updatedCheckInDate(): void
    {
        $this->refreshRoomAvailability();
    }

    public function updatedCheckOutDate(): void
    {
        $this->refreshRoomAvailability();
    }

    public function updatedAdults(): void
    {
        $this->adults = $this->normalizeAdults($this->adults);
        $this->syncOccupancyCaps();
    }

    public function updatedChildren(): void
    {
        $this->children = $this->normalizeChildren($this->children);
        $this->syncOccupancyCaps();
    }

    public function updatedRoomSelections(): void
    {
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

    public function getSelectedRoomsProperty()
    {
        return $this->selectedRoomEntries();
    }

    public function render()
    {
        return view('livewire.staff.reservations.create-reservation', [
            'roomTypeOptions' => $this->roomTypeOptions,
            'roomAvailability' => $this->roomAvailability,
            'selectedRooms' => $this->selectedRooms,
        ]);
    }

    public function submit()
    {
        // sleep(5); // simulate processing delay
        $this->validate($this->rules(), [], $this->attributes());

        $this->normalizeRoomSelections();

        if (empty($this->roomSelections)) {
            $this->addError('roomSelections', 'Please choose at least one available room type.');
            return;
        }

        $selectedRooms = $this->selectedRooms;
        $caps = $this->selectedRoomsCaps();
        $roomCount = $this->roomsSelectedCount();

        if ($selectedRooms->isEmpty()) {
            $this->addError('roomSelections', 'Please choose at least one available room type.');
            return;
        }

        if (! $this->customerId) {
            $this->addError('customerId', 'Please select a guest.');
            return;
        }

        try {
            $checkIn = Carbon::parse($this->checkInDate)->startOfDay();
            $checkOut = Carbon::parse($this->checkOutDate)->startOfDay();
        } catch (\Throwable $e) {
            $this->addError('checkOutDate', 'Invalid dates.');
            return;
        }

        $roomIds = $this->findAvailableRoomIds($checkIn, $checkOut);

        if (empty($roomIds)) {
            $this->addError('roomSelections', 'Selected room types are not available for these dates.');
            return;
        }

        if ($this->adults > $caps['max_adults'] || $this->children > $caps['max_children']) {
            $this->addError('roomSelections', 'Selected rooms cannot accommodate the party size.');
            return;
        }

        if ($roomCount > 0 && $this->adults < $roomCount) {
            $this->addError('adults', 'Need at least one adult per room selected.');
            return;
        }

        if ($this->children > 0 && $caps['max_children'] === 0) {
            $this->addError('roomSelections', 'Selected rooms do not allow children.');
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

        $customer = $this->selectedCustomer();

        DB::beginTransaction();

        try {
            $reservation = Reservation::create([
                'hotel_id' => $this->hotelId,
                'customer_id' => $this->customerId,
                'status' => 'Pending',
                'check_in_date' => $this->checkInDate,
                'check_out_date' => $this->checkOutDate,
                'adults' => $this->adults,
                'children' => $this->children,
                'nightly_rate' => $caps['nightly_rate'],
            ]);

            foreach ($roomIds as $roomId) {
                $reservation->reservationRooms()->create([
                    'hotel_id' => $this->hotelId,
                    'room_id' => $roomId,
                    'from_date' => $this->checkInDate,
                    'to_date' => $this->checkOutDate,
                ]);
            }

            $reservation->occupants()->createMany($this->buildOccupantsPayload($customer));

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            session()->flash('error', 'Unable to create the reservation right now.');
            return;
        }

        session()->flash('status', "Reservation {$reservation->code} created.");

        \App\Support\AuditLogger::log('booking.create.staff', [
            'reservation_id' => $reservation->id,
            'hotel_id' => $this->hotelId,
            'rooms_selected' => $roomCount,
            'dates' => [$this->checkInDate, $this->checkOutDate],
        ], true, $reservation);

        return redirect()->route('staff.reservations.show', $reservation);
    }

    protected function rules(): array
    {
        return [
            'customerId' => ['required', Rule::exists('customer_users', 'id')],
            'checkInDate' => ['required', 'date', 'after_or_equal:today'],
            'checkOutDate' => ['required', 'date', 'after:checkInDate'],
            'roomSelections' => ['required', 'array'],
            'roomSelections.*' => ['integer', 'min:0', 'max:' . $this->maxRoomsPerType],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['required', 'integer', 'min:0', 'max:20'],
            'adultNames' => ['array'],
            'adultNames.*' => ['nullable', 'string', 'max:255'],
            'childNames' => ['array'],
            'childNames.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function attributes(): array
    {
        return [
            'customerId' => 'guest',
            'checkInDate' => 'check-in date',
            'checkOutDate' => 'check-out date',
            'roomSelections' => 'rooms',
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

    /**
     * @return array<int>
     */
    protected function findAvailableRoomIds(Carbon $checkIn, Carbon $checkOut): array
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
                ->whereDoesntHave('reservationRooms', function ($query) use ($checkIn, $checkOut) {
                    $query->where('from_date', '<', $checkOut->toDateString())
                        ->where('to_date', '>', $checkIn->toDateString())
                        ->whereHas('reservation', fn ($r) => $r->whereNotIn('status', ['Cancelled', 'NoShow']));
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

    protected function syncPrimaryGuest(): void
    {
        $customer = $this->selectedCustomer();

        if ($customer) {
            $this->primaryGuestName = $customer['name'] ?? '';
            $this->primaryGuestEmail = $customer['email'] ?? '';
        } else {
            $this->primaryGuestName = '';
            $this->primaryGuestEmail = '';
        }
    }

    protected function syncOccupancyCaps(): void
    {
        $caps = $this->selectedRoomsCaps();

        $maxAdults = max(1, (int) ($caps['max_adults'] ?? 1));
        $maxChildren = max(0, (int) ($caps['max_children'] ?? 0));

        $minAdults = max(1, $this->roomsSelectedCount());

        $this->adults = min($maxAdults, max($minAdults, (int) $this->adults));
        $this->children = min(max(0, (int) $this->children), $maxChildren);

        $this->ensureAdultInputsCount();
        $this->ensureChildInputsCount();
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

    protected function buildOccupantsPayload(?array $customer): array
    {
        $occupants = [[
            'full_name' => $customer['name'] ?? $this->primaryGuestName ?? 'Primary guest',
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

    protected function selectedCustomer(): ?array
    {
        if (! $this->customerId) {
            return null;
        }

        $customer = CustomerUser::query()
            ->with('user:id,name,email')
            ->find($this->customerId);

        if (! $customer) {
            return null;
        }

        return [
            'id' => $customer->id,
            'name' => $customer->name ?? $customer->user?->name ?? '',
            'email' => $customer->email ?? $customer->user?->email ?? '',
        ];
    }
}
