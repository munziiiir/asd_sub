<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\CheckOut;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckInOutController extends Controller
{
    public function index(Request $request): View
    {
        $staff = $request->user('staff');
        abort_unless($staff, 403);

        $hotelId = $staff->hotel_id;
        $timezone = $staff->hotel?->timezone?->timezone ?? config('app.timezone');
        $today = now($timezone)->toDateString();

        $arrivals = Reservation::with(['customer', 'rooms'])
            ->where('hotel_id', $hotelId)
            ->whereIn('status', ['Pending', 'Confirmed'])
            ->orderBy('check_in_date')
            ->limit(10)
            ->get();

        $departures = Reservation::with(['customer', 'rooms.roomType'])
            ->where('hotel_id', $hotelId)
            ->where('status', 'CheckedIn')
            ->orderBy('check_out_date')
            ->limit(10)
            ->get();

        $recentCheckIns = CheckIn::with(['reservation.customer'])
            ->whereHas('reservation', fn ($query) => $query->where('hotel_id', $hotelId))
            ->orderByDesc('checked_in_at')
            ->limit(5)
            ->get();

        $recentCheckOuts = CheckOut::with(['reservation.customer'])
            ->whereHas('reservation', fn ($query) => $query->where('hotel_id', $hotelId))
            ->orderByDesc('checked_out_at')
            ->limit(5)
            ->get();

        $stats = [
            'today_arrivals' => Reservation::where('hotel_id', $hotelId)
                ->whereDate('check_in_date', $today)
                ->whereIn('status', ['Pending', 'Confirmed'])
                ->count(),
            'today_departures' => Reservation::where('hotel_id', $hotelId)
                ->whereDate('check_out_date', $today)
                ->where('status', 'CheckedIn')
                ->count(),
            'in_house' => Reservation::where('hotel_id', $hotelId)
                ->where('status', 'CheckedIn')
                ->count(),
            'pending_arrivals' => Reservation::where('hotel_id', $hotelId)
                ->whereIn('status', ['Pending', 'Confirmed'])
                ->count(),
        ];

        $departureSummaries = $departures->mapWithKeys(function (Reservation $reservation) {
            return [$reservation->id => $this->staySummary($reservation)];
        });

        return view('staff.check-io.index', [
            'arrivals' => $arrivals,
            'departures' => $departures,
            'recentCheckIns' => $recentCheckIns,
            'recentCheckOuts' => $recentCheckOuts,
            'stats' => $stats,
            'departureSummaries' => $departureSummaries,
        ]);
    }

    public function createCheckIn(Request $request): View
    {
        $staff = $request->user('staff');
        abort_unless($staff, 403);

        return view('staff.check-io.check-in', [
            'hotelName' => $staff->hotel->name ?? 'Hotel',
        ]);
    }

    public function createCheckOut(Request $request): View
    {
        $staff = $request->user('staff');
        abort_unless($staff, 403);

        return view('staff.check-io.check-out', [
            'hotelName' => $staff->hotel->name ?? 'Hotel',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function staySummary(Reservation $reservation): array
    {
        $nights = max(1, $reservation->check_in_date->diffInDays($reservation->check_out_date));
        $nightlyRate = (float) $reservation->nightlyRateTotal();

        return [
            'code' => $reservation->code,
            'guest' => $reservation->customer?->name ?? 'Guest profile missing',
            'nights' => $nights,
            'nightly_rate' => $nightlyRate,
            'room_total' => round($nights * $nightlyRate, 2),
        ];
    }
}
