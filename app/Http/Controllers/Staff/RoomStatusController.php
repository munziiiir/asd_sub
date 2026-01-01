<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomStatusLog;
use App\Models\StaffUser;
use App\Support\RoomStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomStatusController extends Controller
{
    public function index(Request $request): View
    {
        $staff = $request->user('staff');
        abort_unless($staff, 403);

        $rooms = Room::with('roomType')
            ->where('hotel_id', $staff->hotel_id)
            ->orderBy('number')
            ->paginate(20);

        return view('staff.rooms.index', [
            'rooms' => $rooms,
        ]);
    }

    public function show(Request $request, Room $room, RoomStatusService $service): View
    {
        $staff = $request->user('staff');
        abort_unless($staff && $room->hotel_id === $staff->hotel_id, 403);

        $room->load(['roomType', 'hotel.timezone']);
        $hotelTimezone = $room->hotel?->timezone?->timezone ?? config('app.timezone');
        $viewerTimezone = $this->sanitizeTimezone(urldecode((string) $request->cookie('viewer_timezone'))) ?? $hotelTimezone;

        $currentReservation = $service->activeReservationForRoom($room);
        $lastOccupied = $this->lastOccupiedReservation($room);
        $pendingCleaning = $this->pendingCleaningLog($room);
        $statusLogs = $this->recentStatusLogs($room);

        $assignees = StaffUser::query()
            ->where('hotel_id', $staff->hotel_id)
            ->where(function ($query) use ($staff) {
                $query->where('role', '!=', 'manager')
                    ->orWhere('id', $staff->id);
            })
            ->where(function ($query) {
                $query->whereNull('employment_status')
                    ->orWhere('employment_status', 'active');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'employment_status']);

        $hotelToday = $service->hotelToday($room);
        $viewerToday = now($viewerTimezone)->toDateString();
        $cleaningBlockedReason = null;

        if ($room->status === 'Occupied' || ($currentReservation && $currentReservation->status === 'CheckedIn')) {
            $cleaningBlockedReason = 'Room is occupied. Complete check-out before marking cleaning.';
        } elseif ($currentReservation && $currentReservation->check_in_date && $currentReservation->check_in_date->toDateString() === $hotelToday) {
            $cleaningBlockedReason = 'Room has an arrival today. Move the reservation before cleaning.';
        }

        $canMarkCleaning = $cleaningBlockedReason === null;
        $canMarkOutOfService = in_array($room->status, ['Available', 'Reserved'], true);

        return view('staff.rooms.show', [
            'room' => $room,
            'currentReservation' => $currentReservation,
            'lastOccupied' => $lastOccupied,
            'pendingCleaning' => $pendingCleaning,
            'assignees' => $assignees,
            'statusLogs' => $statusLogs,
            'canMarkCleaning' => $canMarkCleaning,
            'canMarkOutOfService' => $canMarkOutOfService,
            'cleaningBlockedReason' => $cleaningBlockedReason,
            'hotelToday' => $hotelToday,
            'viewerToday' => $viewerToday,
            'viewerTimezone' => $viewerTimezone,
            'hotelTimezone' => $hotelTimezone,
        ]);
    }

    public function update(Request $request, Room $room, RoomStatusService $service): RedirectResponse
    {
        $staff = $request->user('staff');
        abort_unless($staff && $room->hotel_id === $staff->hotel_id, 403);

        $data = $request->validate([
            'status_action' => ['required', Rule::in(['cleaning', 'out_of_service'])],
            'assigned_staff_id' => ['required_if:status_action,cleaning', 'integer', 'exists:staff_users,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            if ($data['status_action'] === 'cleaning') {
                $assignee = StaffUser::where('hotel_id', $staff->hotel_id)->findOrFail($data['assigned_staff_id']);
                $service->markCleaning($room, $staff, $assignee, $data['note'] ?? null);

                $message = "Room {$room->number} set to Cleaning until end of day.";
            } else {
                $log = $service->markOutOfService($room, $staff, $data['note'] ?? null);
                $message = "Room {$room->number} marked Out of Service.";

                $shiftedRoomNumber = $log->meta['reservation_shifted_to_room_number'] ?? null;
                if ($shiftedRoomNumber) {
                    $message .= " Reservation shifted to room {$shiftedRoomNumber}.";
                }
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'status' => 'Unable to update room status right now. Please try again.',
            ])->withInput();
        }

        return redirect()->route('staff.rooms.show', $room)->with('status', $message);
    }

    protected function pendingCleaningLog(Room $room): ?RoomStatusLog
    {
        return RoomStatusLog::query()
            ->where('room_id', $room->id)
            ->where('new_status', 'Cleaning')
            ->whereNull('reverted_at')
            ->orderByDesc('id')
            ->first();
    }

    protected function lastOccupiedReservation(Room $room): ?Reservation
    {
        $latestCheckIn = $room->checkIns()
            ->with('reservation.customer')
            ->orderByDesc('checked_in_at')
            ->first();

        return $latestCheckIn?->reservation;
    }

    /**
     * @return \Illuminate\Support\Collection<int,RoomStatusLog>
     */
    protected function recentStatusLogs(Room $room)
    {
        return RoomStatusLog::query()
            ->with(['changedBy', 'assignedStaff', 'reservation'])
            ->where('room_id', $room->id)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();
    }

    protected function sanitizeTimezone(?string $tz): ?string
    {
        if (! $tz) {
            return null;
        }

        $trimmed = trim($tz);
        if ($trimmed === '' || $trimmed === 'null' || $trimmed === 'undefined') {
            return null;
        }

        return $trimmed;
    }
}
