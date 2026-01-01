<?php

namespace App\Http\Controllers;

use App\Models\CustomerUser;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Support\ReservationFolioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserBookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Reservation::query()
            ->with(['hotel', 'reservationRooms.room'])
            ->whereHas('customer', fn ($q) => $q->where('user_id', Auth::id()));

        $sort = $request->get('sort', 'updated_desc');
        $sortOptions = [
            'updated_desc' => ['updated_at', 'desc'],
            'updated_asc' => ['updated_at', 'asc'],
            'status' => ['status', 'asc'],
            'checkin' => ['check_in_date', 'asc'],
        ];

        if (isset($sortOptions[$sort])) {
            [$col, $dir] = $sortOptions[$sort];
            $query->orderBy($col, $dir);
        } else {
            $query->orderByDesc('updated_at');
            $sort = 'updated_desc';
        }

        $reservations = $query->paginate(10)->withQueryString();

        return view('bookings.index', [
            'reservations' => $reservations,
            'sort' => $sort,
        ]);
    }

    public function show(Reservation $reservation)
    {
        $this->authorizeReservation($reservation);

        $reservation->load(['hotel', 'reservationRooms.room', 'occupants']);

        $cancellationPreview = app(\App\Support\ReservationFolioService::class)
            ->previewCancellationOutcome($reservation);

        return view('bookings.show', [
            'reservation' => $reservation,
            'cancellationPreview' => $cancellationPreview,
        ]);
    }

    public function pay(Reservation $reservation)
    {
        $this->authorizeReservation($reservation);

        $folioService = app(ReservationFolioService::class);
        $folioService->syncRoomCharges($reservation, 'view payment');
        $folio = $folioService->ensureOpenFolio($reservation);
        $folioService->normalizeOverpayment($folio, 'view payment refund');
        $folioService->enforceDepositStatus($reservation);
        $reservation->refresh();

        if (! in_array($reservation->status, ['Pending', 'NoShow'], true)) {
            return redirect()
                ->route('bookings.show', $reservation)
                ->with('status', 'Booking already finalized.');
        }

        $reservation->load(['hotel.country', 'reservationRooms.room.roomType', 'occupants', 'customer']);

        return view('bookings.pay', [
            'reservation' => $reservation,
        ]);
    }

    public function update(Request $request, Reservation $reservation)
    {
        $this->authorizeReservation($reservation);

        if ($reservation->status === 'CheckedOut') {
            return back()->withErrors(['status' => 'Checked-out bookings cannot be edited.']);
        }

        $validated = $request->validate([
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'room_types' => ['required', 'array', 'min:1'],
            'room_types.*.id' => ['required', 'integer', 'exists:room_types,id', 'distinct'],
            'room_types.*.count' => ['required', 'integer', 'min:1', 'max:10'],
            'occupant_names' => ['array'],
            'occupant_names.*' => ['nullable', 'string', 'max:150'],
        ]);

        $maxRoomTypes = (int) ($reservation->hotel?->roomTypes()->count() ?? 0);
        if ($maxRoomTypes > 0 && count($validated['room_types']) > $maxRoomTypes) {
            return back()->withErrors(['status' => 'Too many room types selected.']);
        }

        if ($reservation->status === 'CheckedIn') {
            unset($validated['check_in_date']);
        }

        $depositSummary = null;

        $hotel = $reservation->hotel()->with('roomTypes')->first();
        if (! $hotel) {
            return back()->withErrors(['status' => 'Selected hotel is invalid.']);
        }

        $roomTypeMap = optional($hotel)->roomTypes->keyBy('id');
        $selections = collect($validated['room_types'] ?? [])
            ->map(function ($row) use ($roomTypeMap) {
                $type = $roomTypeMap->get((int) ($row['id'] ?? 0));
                if (! $type) {
                    return null;
                }
                $capacityPerRoom = max(1, max($type->base_occupancy ?? 0, ($type->max_adults ?? 0) + ($type->max_children ?? 0)));
                $name = strtolower($type->name ?? '');
                if (str_contains($name, 'family suite') || str_contains($name, 'penthouse')) {
                    $capacityPerRoom = min($capacityPerRoom, 4);
                }

                return [
                    'type' => $type,
                    'count' => max(1, (int) ($row['count'] ?? 1)),
                    'capacity_per_room' => $capacityPerRoom,
                    'max_adults' => (int) $type->max_adults,
                    'max_children' => (int) $type->max_children,
                    'active_rate' => (float) $type->activeRate(),
                ];
            })
            ->filter();

        if ($selections->isEmpty()) {
            return back()->withErrors(['status' => 'Select at least one room type.']);
        }

        $totalRooms = (int) $selections->sum(fn ($s) => $s['count']);
        if ($validated['adults'] < $totalRooms) {
            $label = $totalRooms === 1 ? 'room' : 'rooms';

            return back()->withErrors(['status' => "At least {$totalRooms} adult" . ($totalRooms > 1 ? 's' : '') . " required for {$totalRooms} {$label}."]);
        }

        $capacity = (int) $selections->sum(fn ($s) => $s['capacity_per_room'] * $s['count']);
        $maxAdults = (int) $selections->sum(fn ($s) => max(1, $s['max_adults'] ?: $s['capacity_per_room']) * $s['count']);
        $maxChildren = (int) $selections->sum(fn ($s) => max(0, $s['max_children']) * $s['count']);

        $childrenAllowed = $maxChildren > 0;
        if (! $childrenAllowed) {
            $validated['children'] = 0;
        }

        $totalGuests = $validated['adults'] + ($validated['children'] ?? 0);
        if ($totalGuests > $capacity) {
            return back()->withErrors(['status' => "Selected rooms sleep up to {$capacity}. Reduce guest count."]);
        }
        if ($validated['adults'] > $maxAdults) {
            return back()->withErrors(['status' => "Selected rooms allow up to {$maxAdults} adult" . ($maxAdults === 1 ? '' : 's') . '.']);
        }
        if (($validated['children'] ?? 0) > $maxChildren) {
            return back()->withErrors(['status' => "Selected rooms allow up to {$maxChildren} child" . ($maxChildren === 1 ? '' : 'ren') . '.']);
        }

        $names = collect($validated['occupant_names'] ?? [])
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->values()
            ->all();

        $requiredNames = max($totalGuests - 1, 0);
        if ($requiredNames > 0 && count($names) < $requiredNames) {
            return back()->withErrors(['status' => "Please provide {$requiredNames} additional guest name" . ($requiredNames > 1 ? 's' : '') . '.']);
        }

        $roomIds = [];
        foreach ($selections as $selection) {
            $ids = $this->findAvailableRoomIds(
                $reservation,
                $selection['type']->id,
                $selection['count'],
                $validated['check_in_date'] ?? $reservation->check_in_date,
                $validated['check_out_date'] ?? $reservation->check_out_date
            );

            if (count($ids) !== $selection['count']) {
                $found = count($ids);
                $message = $found > 0
                    ? "Only {$found} room" . ($found === 1 ? '' : 's') . " available for {$selection['type']->name} on these dates."
                    : "No available rooms for {$selection['type']->name} on these dates.";

                return back()->withErrors(['status' => $message]);
            }

            $roomIds = array_merge($roomIds, $ids);
        }

        $depositSummary = null;

        DB::transaction(function () use ($reservation, $validated, $roomIds, $selections, $names, &$depositSummary) {
            $reservation->fill([
                'check_in_date' => $validated['check_in_date'] ?? $reservation->check_in_date,
                'check_out_date' => $validated['check_out_date'] ?? $reservation->check_out_date,
                'adults' => $validated['adults'],
                'children' => $validated['children'] ?? 0,
                'nightly_rate' => $selections->sum(fn ($s) => $s['active_rate'] * $s['count']),
            ])->save();

            $reservation->reservationRooms()->delete();
            foreach ($roomIds as $roomId) {
                $reservation->reservationRooms()->create([
                    'hotel_id' => $reservation->hotel_id,
                    'room_id' => $roomId,
                    'from_date' => $reservation->check_in_date,
                    'to_date' => $reservation->check_out_date,
                ]);
            }

            $reservation->occupants()->delete();

            $totalAdults = (int) $validated['adults'];
            $totalChildren = (int) ($validated['children'] ?? 0);
            $additionalAdults = max($totalAdults - 1, 0);

            $adultNames = array_slice($names, 0, $additionalAdults);
            $childNames = array_slice($names, $additionalAdults, $totalChildren);

            $primaryGuestName = $reservation->customer?->name ?: 'Primary guest';

            $occupants = [
                [
                    'full_name' => $primaryGuestName,
                    'type' => 'Adult',
                ],
            ];

            foreach ($adultNames as $name) {
                $occupants[] = [
                    'full_name' => $name,
                    'type' => 'Adult',
                ];
            }

            foreach ($childNames as $name) {
                $occupants[] = [
                    'full_name' => $name,
                    'type' => 'Child',
                ];
            }

            $reservation->occupants()->createMany($occupants);

            $folioService = app(ReservationFolioService::class);
            $folioService->syncRoomCharges($reservation, 'guest edit');
            $folio = $folioService->ensureOpenFolio($reservation);
            $folioService->normalizeOverpayment($folio, 'guest edit refund');
            $depositSummary = $folioService->enforceDepositStatus($reservation);
        });

        $reservation->refresh();

        if (($depositSummary['new_status'] ?? null) === 'Pending' && ($depositSummary['due'] ?? 0) > 0) {
            return redirect()
                ->route('bookings.pay', $reservation)
                ->with('status', 'Booking updated — an additional deposit is required to confirm.');
        }

        return back()->with('status', 'Booking updated.');
    }

    public function cancel(Reservation $reservation)
    {
        $this->authorizeReservation($reservation);

        if (in_array($reservation->status, ['CheckedOut', 'Cancelled'], true)) {
            return back()->withErrors(['status' => 'This booking cannot be cancelled.']);
        }

        if ($reservation->status === 'CheckedIn') {
            return back()->withErrors(['status' => 'Checked-in bookings cannot be cancelled online.']);
        }

        $policy = app(ReservationFolioService::class)->applyCancellationPolicy($reservation, 'guest');

        if ($policy['policy'] === 'no_show') {
            return back()->withErrors(['status' => 'This booking is now marked as No-Show. Please settle the remaining balance.']);
        }

        return back()->with('status', 'Booking cancelled. Refund processed where applicable.');
    }

    private function authorizeReservation(Reservation $reservation): void
    {
        $customer = CustomerUser::firstWhere('user_id', Auth::id());
        abort_unless($customer && $reservation->customer_id === $customer->id, 403);
    }

    /**
     * @return array<int>
     */
    private function findAvailableRoomIds(Reservation $reservation, int $roomTypeId, int $roomsNeeded, string $from, string $to): array
    {
        $roomsNeeded = max(1, $roomsNeeded);

        $ids = $reservation->hotel->rooms()
            ->where('room_type_id', $roomTypeId)
            ->whereNotIn('status', ['Out of Service', 'OOS'])
            ->whereDoesntHave('reservationRooms', function ($query) use ($from, $to, $reservation) {
                $query->where('to_date', '>', $from)
                    ->where('from_date', '<', $to)
                    ->where('reservation_id', '!=', $reservation->id)
                    ->whereHas('reservation', fn ($r) => $r->where('status', '!=', 'Cancelled'));
            })
            ->limit($roomsNeeded)
            ->pluck('rooms.id')
            ->all();

        return count($ids) === $roomsNeeded ? $ids : [];
    }
}
