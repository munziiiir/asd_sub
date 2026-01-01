<?php

namespace App\Support;

use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomStatusLog;
use App\Models\StaffUser;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomStatusService
{
    public function markCleaning(Room $room, StaffUser $actor, StaffUser $assigned, ?string $note = null): RoomStatusLog
    {
        $this->assertSameHotel($room, $actor);
        $this->assertAssignableStaff($assigned, $actor);

        $reservation = $this->activeReservationForRoom($room);

        if ($room->status === 'Cleaning') {
            throw ValidationException::withMessages([
                'status' => 'Room is already marked for cleaning.',
            ]);
        }

        if ($room->status === 'Occupied' || ($reservation && $reservation->status === 'CheckedIn')) {
            throw ValidationException::withMessages([
                'status' => 'Cannot set cleaning while the room is occupied.',
            ]);
        }

        $hotelToday = $this->hotelToday($room);
        if ($reservation && $reservation->check_in_date && $reservation->check_in_date->toDateString() === $hotelToday) {
            throw ValidationException::withMessages([
                'status' => 'Room has a check-in today. Move or adjust the reservation first.',
            ]);
        }

        $revertAt = $this->endOfHotelDayUtc($room);
        $revertTo = $this->determineRevertStatus($room);

        return DB::transaction(function () use ($room, $actor, $assigned, $reservation, $note, $revertAt, $revertTo) {
            $previous = $room->status ?: 'Available';

            $room->update(['status' => 'Cleaning']);

            return $this->logStatusChange($room, [
                'previous_status' => $previous,
                'new_status' => 'Cleaning',
                'revert_to_status' => $revertTo,
                'revert_at' => $revertAt,
                'changed_by_staff_id' => $actor->id,
                'assigned_staff_id' => $assigned->id,
                'reservation_id' => $reservation?->id,
                'note' => $note,
                'context' => 'manual-cleaning',
                'meta' => [
                    'hotel_timezone' => $this->hotelTimezone($room),
                    'reservation_check_in' => $reservation?->check_in_date?->toDateString(),
                    'reservation_status' => $reservation?->status,
                ],
            ]);
        });
    }

    public function markOutOfService(Room $room, StaffUser $actor, ?string $note = null): RoomStatusLog
    {
        $this->assertSameHotel($room, $actor);

        if (! in_array($room->status, ['Available', 'Reserved'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Room must be Available or Reserved to mark it Out of Service.',
            ]);
        }

        $reservation = $this->activeReservationForRoom($room);

        return DB::transaction(function () use ($room, $actor, $reservation, $note) {
            $meta = [];
            $previous = $room->status ?: 'Available';

            if ($reservation) {
                $replacement = $this->shiftReservationToNewRoom($reservation, $room);
                $meta['reservation_shifted_to_room_id'] = $replacement->id;
                $meta['reservation_shifted_to_room_number'] = $replacement->number;
            }

            $room->update(['status' => 'Out of Service']);

            return $this->logStatusChange($room, [
                'previous_status' => $previous,
                'new_status' => 'Out of Service',
                'revert_to_status' => $previous,
                'changed_by_staff_id' => $actor->id,
                'assigned_staff_id' => null,
                'reservation_id' => $reservation?->id,
                'note' => $note,
                'context' => 'manual-oos',
                'meta' => $meta,
            ]);
        });
    }

    public function resetTemporaryStatuses(?CarbonInterface $asOf = null): int
    {
        $now = ($asOf ?? now())->utc();

        $logs = RoomStatusLog::query()
            ->with('room')
            ->whereNotNull('revert_at')
            ->whereNull('reverted_at')
            ->where('revert_at', '<=', $now)
            ->orderBy('id')
            ->get();

        $resetCount = 0;

        foreach ($logs as $log) {
            $room = $log->room;

            if (! $room) {
                $this->markLogReverted($log, $now, [
                    'reason' => 'room_missing',
                ]);
                continue;
            }

            if ($room->status !== $log->new_status) {
                $this->markLogReverted($log, $now, [
                    'reason' => 'status_changed',
                    'current_status' => $room->status,
                ]);
                continue;
            }

            $targetStatus = $log->revert_to_status ?: $log->previous_status ?: 'Available';

            if ($targetStatus === 'Available') {
                $futureReservation = $this->activeReservationForRoom($room);
                if ($futureReservation) {
                    $targetStatus = 'Reserved';
                }
            }

            $room->update(['status' => $targetStatus]);

            $this->logStatusChange($room, [
                'previous_status' => $log->new_status,
                'new_status' => $targetStatus,
                'context' => 'auto-revert',
                'note' => 'Auto-reverted after temporary status expired.',
                'meta' => [
                    'source_log_id' => $log->id,
                ],
            ]);

            $this->markLogReverted($log, $now);
            $resetCount++;
        }

        return $resetCount;
    }

    public function activeReservationForRoom(Room $room): ?Reservation
    {
        $today = $this->hotelToday($room);

        return Reservation::query()
            ->with('customer')
            ->where('hotel_id', $room->hotel_id)
            ->whereHas('reservationRooms', function ($query) use ($room, $today) {
                $query->where('room_id', $room->id)
                    ->where('to_date', '>', $today);
            })
            ->whereNotIn('status', ['Cancelled', 'NoShow', 'CheckedOut'])
            ->orderBy('check_in_date')
            ->first();
    }

    public function syncToNextReservationOrFree(Room $room, string $fallbackStatus = 'Available'): string
    {
        if (in_array($room->status, ['Out of Service', 'OOS', 'Cleaning'], true)) {
            return $room->status;
        }

        $reservation = $this->activeReservationForRoom($room);

        if ($reservation) {
            $room->update(['status' => 'Reserved']);

            return 'Reserved';
        }

        $room->update(['status' => $fallbackStatus]);

        return $fallbackStatus;
    }

    protected function shiftReservationToNewRoom(Reservation $reservation, Room $fromRoom): Room
    {
        $replacement = $this->findReplacementRoom($reservation, $fromRoom);

        if (! $replacement) {
            throw ValidationException::withMessages([
                'status' => 'No other rooms of this type are free for this stay. Move the reservation first, then set this room Out of Service.',
            ]);
        }

        $updated = ReservationRoom::query()
            ->where('reservation_id', $reservation->id)
            ->where('room_id', $fromRoom->id)
            ->update(['room_id' => $replacement->id]);

        if ($updated < 1) {
            throw ValidationException::withMessages([
                'status' => 'Could not move the reservation off this room. Please update the reservation directly.',
            ]);
        }

        if (in_array($reservation->status, ['Pending', 'Confirmed'], true)) {
            $replacement->update(['status' => 'Reserved']);
        }

        return $replacement;
    }

    protected function findReplacementRoom(Reservation $reservation, Room $fromRoom): ?Room
    {
        $from = $reservation->check_in_date?->toDateString();
        $to = $reservation->check_out_date?->toDateString();

        if (! $from || ! $to) {
            return null;
        }

        return Room::query()
            ->where('hotel_id', $fromRoom->hotel_id)
            ->where('room_type_id', $fromRoom->room_type_id)
            ->where('id', '!=', $fromRoom->id)
            ->whereNotIn('status', ['Out of Service', 'OOS', 'Cleaning', 'Occupied'])
            ->whereDoesntHave('reservationRooms', function ($query) use ($from, $to, $reservation) {
                $query->where('to_date', '>', $from)
                    ->where('from_date', '<', $to)
                    ->where('reservation_id', '!=', $reservation->id)
                    ->whereHas('reservation', fn ($r) => $r->where('status', '!=', 'Cancelled'));
            })
            ->orderBy('number')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    protected function hotelTimezone(Room $room): string
    {
        return $room->hotel?->timezone?->timezone ?? config('app.timezone');
    }

    public function hotelToday(Room $room): string
    {
        return now($this->hotelTimezone($room))->toDateString();
    }

    protected function endOfHotelDayUtc(Room $room): Carbon
    {
        return now($this->hotelTimezone($room))->endOfDay()->utc();
    }

    protected function determineRevertStatus(Room $room): string
    {
        $status = $room->status ?: 'Available';

        if (in_array($status, ['Out of Service', 'OOS'], true)) {
            $previous = RoomStatusLog::query()
                ->where('room_id', $room->id)
                ->whereIn('new_status', ['Out of Service', 'OOS'])
                ->orderByDesc('id')
                ->value('previous_status');

            if ($previous) {
                $status = $previous;
            } else {
                $status = 'Available';
            }
        }

        if ($status === 'Cleaning') {
            $status = 'Available';
        }

        if ($status === 'Available') {
            $reservation = $this->activeReservationForRoom($room);
            if ($reservation) {
                return 'Reserved';
            }
        }

        return $status;
    }

    protected function logStatusChange(Room $room, array $attributes): RoomStatusLog
    {
        return RoomStatusLog::create(array_merge([
            'hotel_id' => $room->hotel_id,
            'room_id' => $room->id,
        ], $attributes));
    }

    protected function markLogReverted(RoomStatusLog $log, CarbonInterface $asOf, array $meta = []): void
    {
        $mergedMeta = $log->meta ?? [];

        foreach ($meta as $key => $value) {
            $mergedMeta[$key] = $value;
        }

        $log->forceFill([
            'reverted_at' => $asOf,
            'meta' => $mergedMeta,
        ])->save();
    }

    protected function assertSameHotel(Room $room, StaffUser $staff): void
    {
        if ($room->hotel_id !== $staff->hotel_id) {
            throw ValidationException::withMessages([
                'status' => 'You cannot change rooms outside your hotel.',
            ]);
        }
    }

    protected function assertAssignableStaff(StaffUser $assignee, StaffUser $actor): void
    {
        if ($assignee->hotel_id !== $actor->hotel_id) {
            throw ValidationException::withMessages([
                'assigned_staff_id' => 'Cleaning staff must belong to this hotel.',
            ]);
        }

        if ($assignee->employment_status && $assignee->employment_status !== 'active') {
            throw ValidationException::withMessages([
                'assigned_staff_id' => 'Selected staff member is not active.',
            ]);
        }

        if ($assignee->role === 'manager' && $assignee->id !== $actor->id) {
            throw ValidationException::withMessages([
                'assigned_staff_id' => 'Assign yourself or a non-manager staff member.',
            ]);
        }
    }
}
