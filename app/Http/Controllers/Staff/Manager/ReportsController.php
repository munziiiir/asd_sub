<?php

namespace App\Http\Controllers\Staff\Manager;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\StaffUser;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        $manager = $this->manager($request);
        $data = $this->buildReportData($manager, $request);

        return view('staff.manager.reports.index', $data);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $manager = $this->manager($request);
        $data = $this->buildReportData($manager, $request);

        $section = $this->normalizeSection((string) $request->string('section'));

        $filename = sprintf(
            'reports-%s-%s-%s.csv',
            Str::slug((string) ($data['hotelName'] ?? 'hotel')),
            (string) ($data['rangeStart'] ?? now()->toDateString()),
            $section
        );

        return response()->streamDownload(function () use ($data, $section) {
            $out = fopen('php://output', 'w');
            if (! $out) {
                return;
            }

            fputcsv($out, ['Hotel', (string) ($data['hotelName'] ?? '')]);
            fputcsv($out, ['Reporting period', (string) ($data['rangeStart'] ?? ''), (string) ($data['rangeEnd'] ?? '')]);
            fputcsv($out, []);

            if ($section === 'all' || $section === 'summary') {
                fputcsv($out, ['Summary']);
                foreach ((array) ($data['summary'] ?? []) as $key => $value) {
                    fputcsv($out, [(string) $key, (string) $value]);
                }
                fputcsv($out, []);
            }

            if ($section === 'all' || $section === 'occupancy') {
                fputcsv($out, ['Occupancy']);
                fputcsv($out, ['period', 'avg_rooms_occupied', 'occupancy_rate_percent']);
                foreach ((array) ($data['occupancySeries'] ?? []) as $row) {
                    fputcsv($out, [
                        (string) ($row['label'] ?? ''),
                        (string) ($row['occupied'] ?? 0),
                        (string) ($row['rate'] ?? 0),
                    ]);
                }
                fputcsv($out, []);
            }

            if ($section === 'all' || $section === 'room_revenue') {
                fputcsv($out, ['Revenue by room type (estimated)']);
                fputcsv($out, ['room_type', 'nights', 'revenue']);
                foreach ((array) ($data['roomTypeRevenue'] ?? []) as $row) {
                    fputcsv($out, [
                        (string) ($row['name'] ?? ''),
                        (string) ($row['nights'] ?? 0),
                        (string) ($row['revenue'] ?? 0),
                    ]);
                }
                fputcsv($out, []);
            }

            if ($section === 'all' || $section === 'service_revenue') {
                fputcsv($out, ['Revenue by service (folio charges)']);
                fputcsv($out, ['service', 'revenue']);
                foreach ((array) ($data['serviceRevenue'] ?? []) as $row) {
                    fputcsv($out, [
                        (string) ($row['name'] ?? ''),
                        (string) ($row['revenue'] ?? 0),
                    ]);
                }
                fputcsv($out, []);
            }

            if ($section === 'all' || $section === 'patterns') {
                $patterns = (array) ($data['bookingPatterns'] ?? []);
                fputcsv($out, ['Guest booking patterns']);

                $totals = (array) ($patterns['totals'] ?? []);
                fputcsv($out, ['bookings', (string) ($totals['bookings'] ?? 0)]);
                fputcsv($out, ['avg_lead_time_days', (string) ($totals['avg_lead_time_days'] ?? 0)]);
                fputcsv($out, ['avg_length_of_stay', (string) ($totals['avg_length_of_stay'] ?? 0)]);
                fputcsv($out, []);

                fputcsv($out, ['Lead time buckets']);
                fputcsv($out, ['bucket', 'count']);
                foreach ((array) ($patterns['lead_time_buckets'] ?? []) as $row) {
                    fputcsv($out, [(string) ($row['label'] ?? ''), (string) ($row['count'] ?? 0)]);
                }
                fputcsv($out, []);

                fputcsv($out, ['Preferred check-in day']);
                fputcsv($out, ['day', 'count']);
                foreach ((array) ($patterns['checkin_dow'] ?? []) as $row) {
                    fputcsv($out, [(string) ($row['label'] ?? ''), (string) ($row['count'] ?? 0)]);
                }
                fputcsv($out, []);

                fputcsv($out, ['Length of stay buckets']);
                fputcsv($out, ['bucket', 'count']);
                foreach ((array) ($patterns['stay_length_buckets'] ?? []) as $row) {
                    fputcsv($out, [(string) ($row['label'] ?? ''), (string) ($row['count'] ?? 0)]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $manager = $this->manager($request);
        $data = $this->buildReportData($manager, $request);
        $section = $this->normalizeSection((string) $request->string('section'));

        $filename = sprintf(
            'reports-%s-%s-%s.pdf',
            Str::slug((string) ($data['hotelName'] ?? 'hotel')),
            (string) ($data['rangeStart'] ?? now()->toDateString()),
            $section
        );

        abort_unless(class_exists(\Dompdf\Dompdf::class), 500, 'PDF export is not installed. Ask a developer to add dompdf/dompdf.');

        $html = view('staff.manager.reports.pdf', array_merge($data, [
            'exportSection' => $section,
        ]))->render();

        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setIsRemoteEnabled(false);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function manager(Request $request): StaffUser
    {
        $manager = $request->user('staff');

        abort_unless($manager && $manager->role === 'manager', 403);

        return $manager;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rangeBounds(string $range, Carbon $now): array
    {
        return match ($range) {
            '30d' => [$now->copy()->subDays(29), $now->copy()],
            'year' => [$now->copy()->startOfYear(), $now->copy()],
            default => [$now->copy()->startOfMonth(), $now->copy()],
        };
    }

    private function normalizeRange(string $raw): string
    {
        $range = trim($raw);

        return in_array($range, ['30d', 'month', 'year', 'custom'], true) ? $range : 'month';
    }

    private function normalizeOccupancyGranularity(string $raw): string
    {
        $granularity = trim($raw);

        return in_array($granularity, ['day', 'month', 'year'], true) ? $granularity : 'month';
    }

    private function normalizeSection(string $raw): string
    {
        $section = trim($raw);

        return in_array($section, ['all', 'summary', 'occupancy', 'room_revenue', 'service_revenue', 'patterns'], true)
            ? $section
            : 'all';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReportData(StaffUser $manager, Request $request): array
    {
        $hotel = $manager->hotel;
        $hotelId = (int) $manager->hotel_id;
        $timezone = $hotel?->timezone?->timezone ?? config('app.timezone');

        $now = now($timezone)->startOfDay();
        $range = $this->normalizeRange((string) $request->string('range'));
        $occupancyGranularity = $this->normalizeOccupancyGranularity((string) $request->string('occ'));

        [$rangeStart, $rangeEnd] = $this->rangeBounds($range, $now);
        $customStart = $this->parseDate((string) $request->string('start'), $timezone);
        $customEnd = $this->parseDate((string) $request->string('end'), $timezone);

        if ($range === 'custom' && $customStart && $customEnd) {
            if ($customEnd->lessThan($customStart)) {
                [$customStart, $customEnd] = [$customEnd, $customStart];
            }

            // Guardrail: keep custom range reasonable to avoid heavy reports on large datasets.
            if ($customStart->diffInDays($customEnd) > 366) {
                $customEnd = $customStart->copy()->addDays(366);
            }

            $rangeStart = $customStart->copy();
            $rangeEnd = $customEnd->copy();
        }

        $roomCount = Room::where('hotel_id', $hotelId)
            ->whereNotIn('status', ['Out of Service', 'OOS'])
            ->count();

        $occupancySeries = $this->occupancySeries($hotelId, $roomCount, $occupancyGranularity, $now);
        $roomTypeRevenue = $this->roomTypeRevenue($hotelId, $rangeStart, $rangeEnd);
        $serviceRevenue = $this->serviceRevenue($hotelId, $rangeStart, $rangeEnd);
        $bookingPatterns = $this->bookingPatterns($hotelId, $rangeStart, $rangeEnd, $timezone);

        $roomRevenueTotal = (float) collect($roomTypeRevenue)->sum('revenue');
        $serviceRevenueTotal = (float) collect($serviceRevenue)->sum('revenue');

        $summary = [
            'room_count' => $roomCount,
            'avg_occupancy_rate' => (int) round(collect($occupancySeries)->avg('rate') ?? 0),
            'room_revenue' => round($roomRevenueTotal, 2),
            'service_revenue' => round($serviceRevenueTotal, 2),
            'bookings' => (int) ($bookingPatterns['totals']['bookings'] ?? 0),
        ];

        return [
            'hotelName' => $hotel?->name ?? 'your hotel',
            'filters' => [
                'range' => $range,
                'occ' => $occupancyGranularity,
                'start' => $customStart?->toDateString(),
                'end' => $customEnd?->toDateString(),
            ],
            'rangeStart' => $rangeStart->toDateString(),
            'rangeEnd' => $rangeEnd->toDateString(),
            'summary' => $summary,
            'occupancySeries' => $occupancySeries,
            'roomTypeRevenue' => $roomTypeRevenue,
            'serviceRevenue' => $serviceRevenue,
            'bookingPatterns' => $bookingPatterns,
        ];
    }

    private function parseDate(string $raw, string $timezone): ?Carbon
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value, $timezone)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function occupancySeries(int $hotelId, int $roomCount, string $granularity, Carbon $now): array
    {
        return match ($granularity) {
            'day' => $this->occupancySeriesDaily($hotelId, $roomCount, $now),
            'year' => $this->occupancySeriesYearly($hotelId, $roomCount, $now),
            default => $this->occupancySeriesMonthly($hotelId, $roomCount, $now),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function occupancySeriesDaily(int $hotelId, int $roomCount, Carbon $now): array
    {
        $start = $now->copy()->subDays(29);
        $period = CarbonPeriod::create($start, $now);

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
                'label' => $date->format('M j'),
                'occupied' => $activeRooms,
                'rate' => $rate,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function occupancySeriesMonthly(int $hotelId, int $roomCount, Carbon $now): array
    {
        $startMonth = $now->copy()->subMonths(11)->startOfMonth();
        $months = collect(range(0, 11))->map(fn (int $i) => $startMonth->copy()->addMonths($i));

        return $months->map(function (Carbon $month) use ($hotelId, $roomCount) {
            $periodStart = $month->copy()->startOfMonth();
            $periodEnd = $month->copy()->endOfMonth();
            $days = (int) $periodStart->daysInMonth;

            $occupiedRoomNights = $this->occupiedRoomNights($hotelId, $periodStart, $periodEnd);
            $avgOccupiedRooms = $days > 0 ? (int) round($occupiedRoomNights / $days) : 0;

            $rate = ($roomCount > 0 && $days > 0)
                ? (int) round(($occupiedRoomNights / ($roomCount * $days)) * 100)
                : 0;

            return [
                'date' => $periodStart->format('Y-m'),
                'label' => $periodStart->format('M'),
                'occupied' => $avgOccupiedRooms,
                'rate' => $rate,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function occupancySeriesYearly(int $hotelId, int $roomCount, Carbon $now): array
    {
        $startYear = $now->copy()->subYears(4)->startOfYear();
        $years = collect(range(0, 4))->map(fn (int $i) => $startYear->copy()->addYears($i));

        return $years->map(function (Carbon $year) use ($hotelId, $roomCount) {
            $periodStart = $year->copy()->startOfYear();
            $periodEnd = $year->copy()->endOfYear();
            $days = (int) $periodStart->daysInYear;

            $occupiedRoomNights = $this->occupiedRoomNights($hotelId, $periodStart, $periodEnd);
            $avgOccupiedRooms = $days > 0 ? (int) round($occupiedRoomNights / $days) : 0;

            $rate = ($roomCount > 0 && $days > 0)
                ? (int) round(($occupiedRoomNights / ($roomCount * $days)) * 100)
                : 0;

            return [
                'date' => $periodStart->format('Y'),
                'label' => $periodStart->format('Y'),
                'occupied' => $avgOccupiedRooms,
                'rate' => $rate,
            ];
        })->values()->all();
    }

    private function occupiedRoomNights(int $hotelId, Carbon $startDate, Carbon $endDate): int
    {
        $start = $startDate->copy()->startOfDay();
        $endExclusive = $endDate->copy()->addDay()->startOfDay();

        $reservationRooms = ReservationRoom::query()
            ->where('hotel_id', $hotelId)
            ->where('from_date', '<', $endExclusive->toDateString())
            ->where('to_date', '>', $start->toDateString())
            ->whereHas('reservation', fn ($query) => $query->whereNotIn('status', ['Cancelled', 'NoShow']))
            ->get(['from_date', 'to_date']);

        $roomNights = 0;
        foreach ($reservationRooms as $reservationRoom) {
            $roomNights += $this->overlapNights(
                Carbon::parse($reservationRoom->from_date)->startOfDay(),
                Carbon::parse($reservationRoom->to_date)->startOfDay(),
                $start,
                $endExclusive,
            );
        }

        return $roomNights;
    }

    /**
     * @return array<int, array{name: string, nights: int, revenue: float}>
     */
    private function roomTypeRevenue(int $hotelId, Carbon $startDate, Carbon $endDate): array
    {
        $start = $startDate->copy()->startOfDay();
        $endExclusive = $endDate->copy()->addDay()->startOfDay();

        $reservationRooms = ReservationRoom::query()
            ->where('hotel_id', $hotelId)
            ->where('from_date', '<', $endExclusive->toDateString())
            ->where('to_date', '>', $start->toDateString())
            ->whereHas('reservation', fn ($query) => $query->whereNotIn('status', ['Cancelled', 'NoShow']))
            ->with(['room.roomType'])
            ->get();

        $buckets = [];
        foreach ($reservationRooms as $reservationRoom) {
            $roomType = $reservationRoom->room?->roomType;
            if (! $roomType) {
                continue;
            }

            $roomTypeName = (string) $roomType->name;
            $nights = $this->overlapNights(
                Carbon::parse($reservationRoom->from_date)->startOfDay(),
                Carbon::parse($reservationRoom->to_date)->startOfDay(),
                $start,
                $endExclusive,
            );

            if ($nights <= 0) {
                continue;
            }

            $rate = (float) $roomType->activeRate();
            $revenue = $rate * $nights;

            if (! isset($buckets[$roomTypeName])) {
                $buckets[$roomTypeName] = ['name' => $roomTypeName, 'nights' => 0, 'revenue' => 0.0];
            }

            $buckets[$roomTypeName]['nights'] += $nights;
            $buckets[$roomTypeName]['revenue'] += $revenue;
        }

        return collect($buckets)
            ->values()
            ->sortByDesc('revenue')
            ->map(fn ($row) => [
                'name' => (string) ($row['name'] ?? ''),
                'nights' => (int) ($row['nights'] ?? 0),
                'revenue' => round((float) ($row['revenue'] ?? 0), 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name: string, revenue: float}>
     */
    private function serviceRevenue(int $hotelId, Carbon $startDate, Carbon $endDate): array
    {
        $start = $startDate->toDateString();
        $end = $endDate->toDateString();

        $rows = Charge::query()
            ->selectRaw('description, SUM(total_amount) as revenue')
            ->whereBetween('post_date', [$start, $end])
            ->whereHas('folio.reservation', fn ($q) => $q
                ->where('hotel_id', $hotelId)
                ->whereNotIn('status', ['Cancelled', 'NoShow']))
            ->groupBy('description')
            ->orderByDesc('revenue')
            ->get();

        return $rows
            ->map(fn ($row) => [
                'name' => (string) ($row->description ?? ''),
                'revenue' => round((float) ($row->revenue ?? 0), 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *   totals: array{bookings: int, avg_lead_time_days: int, avg_length_of_stay: float},
     *   lead_time_buckets: array<int, array{label: string, count: int}>,
     *   checkin_dow: array<int, array{label: string, count: int}>,
     *   stay_length_buckets: array<int, array{label: string, count: int}>
     * }
     */
    private function bookingPatterns(int $hotelId, Carbon $startDate, Carbon $endDate, string $timezone): array
    {
        $start = $startDate->copy()->startOfDay()->utc();
        $end = $endDate->copy()->endOfDay()->utc();

        $reservations = Reservation::query()
            ->where('hotel_id', $hotelId)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', ['Cancelled', 'NoShow'])
            ->get(['id', 'created_at', 'check_in_date', 'check_out_date']);

        $leadTimes = [];
        $lengths = [];

        $leadBuckets = [
            'Same day / next day' => 0,
            '2–7 days' => 0,
            '8–30 days' => 0,
            '31+ days' => 0,
        ];

        $stayBuckets = [
            '1 night' => 0,
            '2–3 nights' => 0,
            '4–7 nights' => 0,
            '8+ nights' => 0,
        ];

        $dowBuckets = collect(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])
            ->mapWithKeys(fn (string $d) => [$d => 0])
            ->all();

        foreach ($reservations as $reservation) {
            $created = $reservation->created_at?->copy()->setTimezone($timezone);
            $checkIn = $reservation->check_in_date ? Carbon::parse($reservation->check_in_date, $timezone)->startOfDay() : null;
            $checkOut = $reservation->check_out_date ? Carbon::parse($reservation->check_out_date, $timezone)->startOfDay() : null;

            if ($created && $checkIn) {
                $leadDays = max(0, $created->startOfDay()->diffInDays($checkIn, false));
                $leadTimes[] = $leadDays;

                if ($leadDays <= 1) {
                    $leadBuckets['Same day / next day']++;
                } elseif ($leadDays <= 7) {
                    $leadBuckets['2–7 days']++;
                } elseif ($leadDays <= 30) {
                    $leadBuckets['8–30 days']++;
                } else {
                    $leadBuckets['31+ days']++;
                }
            }

            if ($checkIn) {
                $dow = $checkIn->format('D');
                if (isset($dowBuckets[$dow])) {
                    $dowBuckets[$dow]++;
                }
            }

            if ($checkIn && $checkOut) {
                $nights = max(1, $checkIn->diffInDays($checkOut));
                $lengths[] = $nights;

                if ($nights === 1) {
                    $stayBuckets['1 night']++;
                } elseif ($nights <= 3) {
                    $stayBuckets['2–3 nights']++;
                } elseif ($nights <= 7) {
                    $stayBuckets['4–7 nights']++;
                } else {
                    $stayBuckets['8+ nights']++;
                }
            }
        }

        $avgLead = count($leadTimes) > 0 ? (int) round(array_sum($leadTimes) / count($leadTimes)) : 0;
        $avgStay = count($lengths) > 0 ? round(array_sum($lengths) / count($lengths), 1) : 0.0;

        return [
            'totals' => [
                'bookings' => $reservations->count(),
                'avg_lead_time_days' => $avgLead,
                'avg_length_of_stay' => $avgStay,
            ],
            'lead_time_buckets' => collect($leadBuckets)
                ->map(fn (int $count, string $label) => ['label' => $label, 'count' => $count])
                ->values()
                ->all(),
            'checkin_dow' => collect($dowBuckets)
                ->map(fn (int $count, string $label) => ['label' => $label, 'count' => $count])
                ->values()
                ->all(),
            'stay_length_buckets' => collect($stayBuckets)
                ->map(fn (int $count, string $label) => ['label' => $label, 'count' => $count])
                ->values()
                ->all(),
        ];
    }

    private function overlapNights(Carbon $fromDate, Carbon $toDate, Carbon $rangeStart, Carbon $rangeEndExclusive): int
    {
        $start = $fromDate->greaterThan($rangeStart) ? $fromDate : $rangeStart;
        $end = $toDate->lessThan($rangeEndExclusive) ? $toDate : $rangeEndExclusive;

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        return $start->diffInDays($end);
    }
}
