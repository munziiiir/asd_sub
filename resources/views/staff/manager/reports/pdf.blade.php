<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reports</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        h2 { font-size: 14px; margin: 18px 0 8px; }
        p { margin: 0 0 8px; color: #374151; }
        .muted { color: #6b7280; }
        .meta { margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; vertical-align: top; }
        th { background: #f9fafb; text-align: left; }
        .right { text-align: right; }
    </style>
</head>
<body>
    @php
        $section = $exportSection ?? 'all';
        $only = fn (string $s) => $section === 'all' || $section === $s;
    @endphp

    <h1>Reports &amp; Analytics</h1>
    <div class="meta">
        <p><strong>Hotel:</strong> {{ $hotelName ?? '' }}</p>
        <p><strong>Reporting period:</strong> {{ $rangeStart ?? '' }} → {{ $rangeEnd ?? '' }}</p>
        <p class="muted">Generated: {{ now()->toDateTimeString() }}</p>
    </div>

    @if ($only('summary'))
        <h2>Summary</h2>
        <table>
            <tbody>
                @foreach (($summary ?? []) as $key => $value)
                    <tr>
                        <th style="width: 45%;">{{ (string) $key }}</th>
                        <td class="right">{{ (string) $value }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($only('occupancy'))
        <h2>Occupancy</h2>
        <table>
            <thead>
                <tr>
                    <th>Period</th>
                    <th class="right">Avg rooms occupied</th>
                    <th class="right">Occupancy (%)</th>
                </tr>
            </thead>
            <tbody>
                @foreach (($occupancySeries ?? []) as $row)
                    <tr>
                        <td>{{ $row['label'] ?? '' }}</td>
                        <td class="right">{{ $row['occupied'] ?? 0 }}</td>
                        <td class="right">{{ $row['rate'] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($only('room_revenue'))
        <h2>Revenue by Room Type (estimated)</h2>
        <p class="muted">Room revenue is estimated from room nights × current active rate.</p>
        <table>
            <thead>
                <tr>
                    <th>Room type</th>
                    <th class="right">Nights</th>
                    <th class="right">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @foreach (($roomTypeRevenue ?? []) as $row)
                    <tr>
                        <td>{{ $row['name'] ?? '' }}</td>
                        <td class="right">{{ $row['nights'] ?? 0 }}</td>
                        <td class="right">£{{ number_format((float) ($row['revenue'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($only('service_revenue'))
        <h2>Revenue by Service (folio charges)</h2>
        <table>
            <thead>
                <tr>
                    <th>Service</th>
                    <th class="right">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @foreach (($serviceRevenue ?? []) as $row)
                    <tr>
                        <td>{{ $row['name'] ?? '' }}</td>
                        <td class="right">£{{ number_format((float) ($row['revenue'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($only('patterns'))
        <h2>Guest Booking Patterns</h2>
        @php $totals = $bookingPatterns['totals'] ?? []; @endphp

        <table>
            <tbody>
                <tr>
                    <th style="width: 45%;">Bookings analysed</th>
                    <td class="right">{{ $totals['bookings'] ?? 0 }}</td>
                </tr>
                <tr>
                    <th>Average lead time (days)</th>
                    <td class="right">{{ $totals['avg_lead_time_days'] ?? 0 }}</td>
                </tr>
                <tr>
                    <th>Average length of stay (nights)</th>
                    <td class="right">{{ $totals['avg_length_of_stay'] ?? 0 }}</td>
                </tr>
            </tbody>
        </table>

        <h2>Lead Time Buckets</h2>
        <table>
            <thead>
                <tr>
                    <th>Bucket</th>
                    <th class="right">Count</th>
                </tr>
            </thead>
            <tbody>
                @foreach (($bookingPatterns['lead_time_buckets'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['label'] ?? '' }}</td>
                        <td class="right">{{ $row['count'] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Preferred Check-in Day</h2>
        <table>
            <thead>
                <tr>
                    <th>Day</th>
                    <th class="right">Count</th>
                </tr>
            </thead>
            <tbody>
                @foreach (($bookingPatterns['checkin_dow'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['label'] ?? '' }}</td>
                        <td class="right">{{ $row['count'] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Length of Stay Buckets</h2>
        <table>
            <thead>
                <tr>
                    <th>Bucket</th>
                    <th class="right">Count</th>
                </tr>
            </thead>
            <tbody>
                @foreach (($bookingPatterns['stay_length_buckets'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['label'] ?? '' }}</td>
                        <td class="right">{{ $row['count'] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>

