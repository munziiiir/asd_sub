<x-layouts.app.base :title="'Reports & Analytics'">
    @php
        $qs = request()->query();
        $exportAllCsv = route('staff.manager.reports.export.csv') . (count($qs) ? ('?' . http_build_query(array_merge($qs, ['section' => 'all']))) : '');
        $exportAllPdf = route('staff.manager.reports.export.pdf') . (count($qs) ? ('?' . http_build_query(array_merge($qs, ['section' => 'all']))) : '');
    @endphp
    <x-slot name="header">
        <x-staff.header
            title="Front Desk Hub"
            titleColor="secondary"
            description="Manager-only reporting for {{ $hotelName ?? 'your hotel' }}."
        >
            <x-slot name="actions">
                <a href="{{ route('staff.frontdesk') }}" class="btn btn-ghost btn-sm md:btn-md">
                    &larr; Back to hub
                </a>
            </x-slot>
        </x-staff.header>
    </x-slot>

    <div class="bg-base-200 pt-4">
        <div class="mx-auto flex max-w-6xl items-center gap-2 px-4 text-sm text-base-content/70 flex-wrap">
            <a href="{{ route('staff.frontdesk') }}" class="link text-secondary font-semibold">Front Desk Hub</a>
            <span class="text-base-content/50">→</span>
            <span>Manager</span>
            <span class="text-base-content/50">→</span>
            <span>Reports &amp; analytics</span>
        </div>
    </div>

    <section class="bg-base-200 py-6">
        <div class="mx-auto max-w-6xl px-4 space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Reports &amp; analytics</h1>
                    <p class="text-sm text-base-content/70">
                        Reporting period: <span class="font-semibold">{{ $rangeStart ?? '' }}</span> → <span class="font-semibold">{{ $rangeEnd ?? '' }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 sm:justify-end">
                    <a href="{{ $exportAllCsv }}" class="btn btn-outline btn-primary btn-sm md:btn-md">
                        Download CSV
                    </a>
                    <a href="{{ $exportAllPdf }}" class="btn btn-outline btn-secondary btn-sm md:btn-md">
                        Download PDF
                    </a>
                </div>
            </div>

            <form method="GET" action="{{ route('staff.manager.reports.index') }}" class="card bg-base-100 shadow" data-report-filters>
                <div class="card-body">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-12 md:items-end">
                        <label class="flex flex-col gap-1 md:col-span-5">
                            <span class="label-text font-semibold">Revenue &amp; patterns range</span>
                            <select name="range" class="select select-bordered w-full">
                                <option value="month" @selected(($filters['range'] ?? 'month') === 'month')>This month</option>
                                <option value="30d" @selected(($filters['range'] ?? '') === '30d')>Last 30 days</option>
                                <option value="year" @selected(($filters['range'] ?? '') === 'year')>Year to date</option>
                                <option value="custom" @selected(($filters['range'] ?? '') === 'custom')>Custom</option>
                            </select>
                            <span class="text-xs text-base-content/60">Controls revenue and booking-pattern summaries.</span>
                        </label>

                        <label class="flex flex-col gap-1 md:col-span-5">
                            <span class="label-text font-semibold">Occupancy granularity</span>
                            <select name="occ" class="select select-bordered w-full">
                                <option value="month" @selected(($filters['occ'] ?? 'month') === 'month')>Monthly (last 12 months)</option>
                                <option value="day" @selected(($filters['occ'] ?? '') === 'day')>Daily (last 30 days)</option>
                                <option value="year" @selected(($filters['occ'] ?? '') === 'year')>Yearly (last 5 years)</option>
                            </select>
                            <span class="text-xs text-base-content/60">Occupancy excludes cancelled/no-show stays.</span>
                        </label>

                        <div class="md:col-span-2 flex gap-2 md:justify-end">
                            <button type="submit" class="btn btn-primary btn-sm md:btn-md w-full md:w-auto">Apply</button>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-12" data-custom-range style="{{ ($filters['range'] ?? '') === 'custom' ? '' : 'display:none' }}">
                        <label class="flex flex-col gap-1 md:col-span-6">
                            <span class="label-text font-semibold">Start date</span>
                            <input
                                type="date"
                                name="start"
                                value="{{ $filters['start'] ?? '' }}"
                                class="input input-bordered w-full"
                                max="{{ now()->toDateString() }}"
                            >
                        </label>
                        <label class="flex flex-col gap-1 md:col-span-6">
                            <span class="label-text font-semibold">End date</span>
                            <input
                                type="date"
                                name="end"
                                value="{{ $filters['end'] ?? '' }}"
                                class="input input-bordered w-full"
                                max="{{ now()->toDateString() }}"
                            >
                            <span class="text-xs text-base-content/60">Custom ranges are capped to ~12 months for performance.</span>
                        </label>
                    </div>
                </div>
            </form>

            <div class="stats stats-vertical lg:stats-horizontal shadow">
                <div class="stat">
                    <div class="stat-title">Rooms (available)</div>
                    <div class="stat-value text-secondary">{{ $summary['room_count'] ?? 0 }}</div>
                    <div class="stat-desc">Excludes OOS rooms</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Avg occupancy</div>
                    <div class="stat-value text-primary">{{ $summary['avg_occupancy_rate'] ?? 0 }}%</div>
                    <div class="stat-desc">Based on selected granularity</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Room revenue (est.)</div>
                    <div class="stat-value text-success">£{{ number_format((float) ($summary['room_revenue'] ?? 0), 2) }}</div>
                    <div class="stat-desc">From room nights × active rate</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Service revenue</div>
                    <div class="stat-value text-accent">£{{ number_format((float) ($summary['service_revenue'] ?? 0), 2) }}</div>
                    <div class="stat-desc">From folio charges</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Bookings created</div>
                    <div class="stat-value">{{ $summary['bookings'] ?? 0 }}</div>
                    <div class="stat-desc">In selected range</div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6">
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h2 class="card-title">Occupancy rates</h2>
                                <p class="text-sm text-base-content/70">
                                    Average rooms occupied ÷ rooms available.
                                </p>
                            </div>
                            <div class="badge badge-secondary badge-outline">
                                {{ ($filters['occ'] ?? 'month') === 'day' ? 'Daily' : (($filters['occ'] ?? 'month') === 'year' ? 'Yearly' : 'Monthly') }}
                            </div>
                        </div>

                        @php
                            $occupancyChart = collect($occupancySeries ?? [])
                                ->map(fn ($d) => [
                                    'label' => $d['label'] ?? '',
                                    'value' => $d['rate'] ?? 0,
                                    'date' => $d['date'] ?? null,
                                    'occupied' => $d['occupied'] ?? null,
                                ])
                                ->all();
                        @endphp

                        <div class="mt-4">
                            <x-charts.line
                                :series="$occupancyChart"
                                format="percent"
                                max="100"
                                color-class="text-secondary"
                                class="rounded-box bg-base-200/30 p-3"
                            />
                        </div>

                        <div class="mt-4 overflow-x-auto">
                            <table class="table w-full">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>Avg rooms occupied</th>
                                        <th class="text-right">Occupancy</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse (($occupancySeries ?? []) as $row)
                                        <tr>
                                            <td class="font-semibold">{{ $row['label'] ?? '' }}</td>
                                            <td class="text-base-content/70">{{ $row['occupied'] ?? 0 }}</td>
                                            <td class="text-right font-semibold">{{ $row['rate'] ?? 0 }}%</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="py-6 text-center text-base-content/70">No occupancy data yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h2 class="card-title">Revenue reports</h2>
                                <p class="text-sm text-base-content/70">
                                    Room revenue is estimated from room nights × current active rate. Service revenue comes from folio charges.
                                </p>
                            </div>
                        </div>

                        @php
                            $roomTotal = (float) collect($roomTypeRevenue ?? [])->sum('revenue');
                            $serviceTotal = (float) collect($serviceRevenue ?? [])->sum('revenue');
                        @endphp

                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mt-4">
                            <div class="rounded-box bg-base-200/30 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-semibold">By room type</h3>
                                        <p class="text-xs text-base-content/60">Total: £{{ number_format($roomTotal, 2) }}</p>
                                    </div>
                                </div>

                                <div class="mt-3 overflow-x-auto">
                                    <table class="table table-sm w-full">
                                        <thead>
                                            <tr>
                                                <th>Room type</th>
                                                <th class="text-right">Nights</th>
                                                <th class="text-right">Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse (collect($roomTypeRevenue ?? [])->take(10) as $row)
                                                @php
                                                    $pct = $roomTotal > 0 ? round(((float) $row['revenue'] / $roomTotal) * 100) : 0;
                                                @endphp
                                                <tr>
                                                    <td class="font-semibold">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <span>{{ $row['name'] ?? '' }}</span>
                                                            <span class="badge badge-ghost badge-sm">{{ $pct }}%</span>
                                                        </div>
                                                        <progress class="progress progress-secondary w-full mt-2" value="{{ $pct }}" max="100"></progress>
                                                    </td>
                                                    <td class="text-right text-base-content/70">{{ $row['nights'] ?? 0 }}</td>
                                                    <td class="text-right font-semibold">£{{ number_format((float) ($row['revenue'] ?? 0), 2) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="3" class="py-6 text-center text-base-content/70">No room revenue yet.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="rounded-box bg-base-200/30 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-semibold">By service (charges)</h3>
                                        <p class="text-xs text-base-content/60">Total: £{{ number_format($serviceTotal, 2) }}</p>
                                    </div>
                                </div>

                                <div class="mt-3 overflow-x-auto">
                                    <table class="table table-sm w-full">
                                        <thead>
                                            <tr>
                                                <th>Service</th>
                                                <th class="text-right">Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse (collect($serviceRevenue ?? [])->take(10) as $row)
                                                @php
                                                    $pct = $serviceTotal > 0 ? round(((float) $row['revenue'] / $serviceTotal) * 100) : 0;
                                                @endphp
                                                <tr>
                                                    <td class="font-semibold">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <span>{{ $row['name'] ?? '' }}</span>
                                                            <span class="badge badge-ghost badge-sm">{{ $pct }}%</span>
                                                        </div>
                                                        <progress class="progress progress-accent w-full mt-2" value="{{ $pct }}" max="100"></progress>
                                                    </td>
                                                    <td class="text-right font-semibold">£{{ number_format((float) ($row['revenue'] ?? 0), 2) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="2" class="py-6 text-center text-base-content/70">No service revenue yet.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h2 class="card-title">Guest booking patterns</h2>
                                <p class="text-sm text-base-content/70">
                                    Lead time, check-in preferences, and typical stay length (based on bookings created in range).
                                </p>
                            </div>
                        </div>

                        @php
                            $totals = $bookingPatterns['totals'] ?? [];
                            $bookings = (int) ($totals['bookings'] ?? 0);
                        @endphp

                        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                            <div class="rounded-box bg-base-200/30 p-4">
                                <div class="text-sm text-base-content/70">Average lead time</div>
                                <div class="text-2xl font-bold">{{ (int) ($totals['avg_lead_time_days'] ?? 0) }} days</div>
                            </div>
                            <div class="rounded-box bg-base-200/30 p-4">
                                <div class="text-sm text-base-content/70">Average length of stay</div>
                                <div class="text-2xl font-bold">{{ (float) ($totals['avg_length_of_stay'] ?? 0) }} nights</div>
                            </div>
                            <div class="rounded-box bg-base-200/30 p-4">
                                <div class="text-sm text-base-content/70">Bookings analysed</div>
                                <div class="text-2xl font-bold">{{ $bookings }}</div>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-3">
                            <div class="rounded-box bg-base-200/30 p-4">
                                <h3 class="font-semibold">Lead time</h3>
                                <div class="mt-3 space-y-3">
                                    @foreach (($bookingPatterns['lead_time_buckets'] ?? []) as $row)
                                        @php
                                            $count = (int) ($row['count'] ?? 0);
                                            $pct = $bookings > 0 ? (int) round(($count / $bookings) * 100) : 0;
                                        @endphp
                                        <div>
                                            <div class="flex items-center justify-between text-sm">
                                                <span class="font-semibold">{{ $row['label'] ?? '' }}</span>
                                                <span class="text-base-content/70">{{ $count }} ({{ $pct }}%)</span>
                                            </div>
                                            <progress class="progress progress-primary w-full" value="{{ $pct }}" max="100"></progress>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="rounded-box bg-base-200/30 p-4">
                                <h3 class="font-semibold">Preferred check-in day</h3>
                                <div class="mt-3 space-y-3">
                                    @foreach (($bookingPatterns['checkin_dow'] ?? []) as $row)
                                        @php
                                            $count = (int) ($row['count'] ?? 0);
                                            $pct = $bookings > 0 ? (int) round(($count / $bookings) * 100) : 0;
                                        @endphp
                                        <div>
                                            <div class="flex items-center justify-between text-sm">
                                                <span class="font-semibold">{{ $row['label'] ?? '' }}</span>
                                                <span class="text-base-content/70">{{ $count }} ({{ $pct }}%)</span>
                                            </div>
                                            <progress class="progress progress-secondary w-full" value="{{ $pct }}" max="100"></progress>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="rounded-box bg-base-200/30 p-4">
                                <h3 class="font-semibold">Length of stay</h3>
                                <div class="mt-3 space-y-3">
                                    @foreach (($bookingPatterns['stay_length_buckets'] ?? []) as $row)
                                        @php
                                            $count = (int) ($row['count'] ?? 0);
                                            $pct = $bookings > 0 ? (int) round(($count / $bookings) * 100) : 0;
                                        @endphp
                                        <div>
                                            <div class="flex items-center justify-between text-sm">
                                                <span class="font-semibold">{{ $row['label'] ?? '' }}</span>
                                                <span class="text-base-content/70">{{ $count }} ({{ $pct }}%)</span>
                                            </div>
                                            <progress class="progress progress-accent w-full" value="{{ $pct }}" max="100"></progress>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <x-slot name="scripts">
        <script>
            (function() {
                const filterForm = document.querySelector('[data-report-filters]');
                if (filterForm) {
                    const rangeSelect = filterForm.querySelector('select[name="range"]');
                    const customRange = filterForm.querySelector('[data-custom-range]');
                    const toggleCustomRange = () => {
                        const isCustom = rangeSelect && rangeSelect.value === 'custom';
                        if (customRange) customRange.style.display = isCustom ? '' : 'none';
                    };
                    if (rangeSelect) rangeSelect.addEventListener('change', toggleCustomRange);
                    toggleCustomRange();
                }

                const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

                const getSvgPointX = (svg, event) => {
                    const rect = svg.getBoundingClientRect();
                    const vb = svg.viewBox.baseVal;
                    const ratio = (event.clientX - rect.left) / rect.width;
                    return vb.x + (ratio * vb.width);
                };

                const toContainerPx = (containerRect, svgRect, svg, cx, cy) => {
                    const vb = svg.viewBox.baseVal;
                    const x = (cx - vb.x) / vb.width * svgRect.width + (svgRect.left - containerRect.left);
                    const y = (cy - vb.y) / vb.height * svgRect.height + (svgRect.top - containerRect.top);
                    return { x, y };
                };

                const initLineChart = (container) => {
                    const svg = container.querySelector('svg');
                    const tooltip = container.querySelector('[data-tooltip]');
                    if (!svg || !tooltip) return;

                    const titleEl = tooltip.querySelector('[data-tooltip-title]');
                    const subtitleEl = tooltip.querySelector('[data-tooltip-subtitle]');
                    const guide = svg.querySelector('[data-guide]');

                    const points = Array.from(svg.querySelectorAll('[data-point]'))
                        .map((group) => {
                            const hit = group.querySelector('[data-hit]');
                            const dot = group.querySelector('[data-dot]');
                            const ring = group.querySelector('[data-ring]');
                            if (!hit || !dot || !ring) return null;

                            return {
                                group,
                                hit,
                                dot,
                                ring,
                                cx: parseFloat(hit.getAttribute('cx') || '0'),
                                cy: parseFloat(hit.getAttribute('cy') || '0'),
                                label: group.getAttribute('data-label') || '',
                                display: group.getAttribute('data-display') || '',
                                subtitle: group.getAttribute('data-subtitle') || '',
                            };
                        })
                        .filter(Boolean);

                    if (points.length === 0) return;

                    let active = null;

                    const setActive = (point) => {
                        if (!point || active === point) return;
                        active = point;

                        for (const p of points) {
                            const isActive = p === point;
                            p.dot.setAttribute('r', isActive ? '5.5' : '4.5');
                            p.ring.setAttribute('fill-opacity', isActive ? '0.22' : '0.12');
                            p.ring.setAttribute('r', isActive ? '11' : '9');
                        }

                        if (guide) {
                            guide.setAttribute('x1', String(point.cx));
                            guide.setAttribute('x2', String(point.cx));
                            guide.setAttribute('stroke-opacity', '0.18');
                        }

                        if (titleEl) titleEl.textContent = `${point.label} · ${point.display}`;
                        if (subtitleEl) {
                            subtitleEl.textContent = point.subtitle || '';
                            subtitleEl.style.display = point.subtitle ? 'block' : 'none';
                        }
                    };

                    const showTooltipAtPoint = (point) => {
                        const containerRect = container.getBoundingClientRect();
                        const svgRect = svg.getBoundingClientRect();
                        const pos = toContainerPx(containerRect, svgRect, svg, point.cx, point.cy);

                        tooltip.style.left = `${pos.x}px`;
                        tooltip.style.top = `${pos.y}px`;
                        tooltip.classList.remove('hidden');
                    };

                    const hideTooltip = () => {
                        tooltip.classList.add('hidden');
                        if (guide) guide.setAttribute('stroke-opacity', '0');
                        active = null;
                        for (const p of points) {
                            p.dot.setAttribute('r', '4.5');
                            p.ring.setAttribute('fill-opacity', '0.12');
                            p.ring.setAttribute('r', '9');
                        }
                    };

                    const nearestPoint = (x) => {
                        let best = points[0];
                        let bestDist = Math.abs(points[0].cx - x);
                        for (const p of points) {
                            const dist = Math.abs(p.cx - x);
                            if (dist < bestDist) {
                                best = p;
                                bestDist = dist;
                            }
                        }
                        return best;
                    };

                    const onMove = (event) => {
                        const x = getSvgPointX(svg, event);
                        const point = nearestPoint(x);
                        if (!point) return;
                        setActive(point);
                        showTooltipAtPoint(point);
                    };

                    svg.addEventListener('mousemove', onMove);
                    svg.addEventListener('mouseenter', onMove);
                    svg.addEventListener('mouseleave', hideTooltip);

                    // Initialize with first point for keyboard-less discovery
                    setActive(points[points.length - 1]);
                };

                document.querySelectorAll('[data-linechart]').forEach(initLineChart);
            })();
        </script>
    </x-slot>
</x-layouts.app.base>
