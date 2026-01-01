<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $staff = $request->user('staff');
        abort_unless($staff, 403);

        $hotel = $staff->hotel;
        $hotelId = $hotel?->id;
        $timezone = $hotel?->timezone?->timezone ?? config('app.timezone');

        $today = now($timezone)->startOfDay();
        $startDate = $today->copy()->subDays(6);

        $stats = $this->buildHeadlineStats($hotelId, $today);
        $revenueSeries = $this->revenueSeries($hotelId, $startDate, $today, $timezone);
        $occupancySeries = $this->occupancySeries($hotelId, $startDate, $today);

        $revenueMax = (float) collect($revenueSeries)->max('amount');
        $roomCount = Room::where('hotel_id', $hotelId)
            ->whereNotIn('status', ['Out of Service', 'OOS'])
            ->count();

        return view('staff.dashboard', [
            'stats' => $stats,
            'revenueSeries' => $revenueSeries,
            'revenueMax' => $revenueMax,
            'occupancySeries' => $occupancySeries,
            'roomCount' => $roomCount,
            'hotelName' => $hotel?->name ?? 'Hotel',
        ]);
    }

    /**
     * @return array<string, int>
     */
    protected function buildHeadlineStats(?int $hotelId, Carbon $today): array
    {
        if (! $hotelId) {
            return [
                'today_arrivals' => 0,
                'today_departures' => 0,
                'in_house' => 0,
                'rooms_oos' => 0,
            ];
        }

        $todayDate = $today->toDateString();

        return [
            'today_arrivals' => Reservation::where('hotel_id', $hotelId)
                ->whereDate('check_in_date', $todayDate)
                ->whereIn('status', ['Pending', 'Confirmed'])
                ->count(),
            'today_departures' => Reservation::where('hotel_id', $hotelId)
                ->whereDate('check_out_date', $todayDate)
                ->where('status', 'CheckedIn')
                ->count(),
            'in_house' => Reservation::where('hotel_id', $hotelId)
                ->where('status', 'CheckedIn')
                ->count(),
            'rooms_oos' => Room::where('hotel_id', $hotelId)
                ->where('status', 'Out of Service')
                ->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function revenueSeries(?int $hotelId, Carbon $startDate, Carbon $endDate, string $timezone): array
    {
        if (! $hotelId) {
            return [];
        }

        $period = CarbonPeriod::create($startDate, $endDate);
        $series = collect($period)->mapWithKeys(
            fn (Carbon $date) => [$date->toDateString() => 0.0]
        );

        $payments = Payment::query()
            ->whereHas('folio.reservation', fn ($query) => $query->where('hotel_id', $hotelId))
            ->whereBetween('paid_at', [$startDate->copy()->utc(), $endDate->copy()->endOfDay()->utc()])
            ->get();

        foreach ($payments as $payment) {
            if (! $payment->paid_at) {
                continue;
            }

            $dateKey = $payment->paid_at->setTimezone($timezone)->toDateString();
            if ($series->has($dateKey)) {
                $series[$dateKey] += (float) $payment->amount;
            }
        }

        return $series->map(
            fn (float $amount, string $date) => [
                'date' => $date,
                'label' => Carbon::parse($date, $timezone)->format('D'),
                'amount' => round($amount, 2),
            ]
        )->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function occupancySeries(?int $hotelId, Carbon $startDate, Carbon $endDate): array
    {
        if (! $hotelId) {
            return [];
        }

        $roomCount = Room::where('hotel_id', $hotelId)
            ->whereNotIn('status', ['Out of Service', 'OOS'])
            ->count();

        $period = CarbonPeriod::create($startDate, $endDate);

        return collect($period)->map(function (Carbon $date) use ($hotelId, $roomCount) {
            $activeRooms = ReservationRoom::query()
                ->where('hotel_id', $hotelId)
                ->where('from_date', '<=', $date->toDateString())
                ->where('to_date', '>', $date->toDateString())
                ->whereHas('reservation', fn ($query) => $query->whereNotIn('status', ['Cancelled', 'NoShow']))
                ->distinct('room_id')
                ->count('room_id');

            $rate = $roomCount > 0
                ? (int) round(($activeRooms / $roomCount) * 100)
                : 0;

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('D'),
                'occupied' => $activeRooms,
                'rate' => $rate,
            ];
        })->values()->all();
    }
}
