<x-layouts.app.base :title="'Staff Dashboard'">
    <x-slot name="header">
        <x-staff.header
            title="Staff Portal"
        >
            <x-slot name="actions">
                <a href="{{ route('staff.frontdesk') }}" class="btn btn-outline btn-primary btn-sm md:btn-md">
                    Front Desk Hub
                </a>
                <form method="POST" action="{{ route('staff.logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-error btn-sm md:btn-md">
                        Sign out
                    </button>
                </form>
            </x-slot>
        </x-staff.header>
    </x-slot>

    <section class="bg-base-200 py-10 min-h-[calc(100dvh-4.5rem)]">
        <div class="mx-auto grid max-w-6xl gap-6 px-4">
            <div>
                <h1 class="text-2xl font-bold">Welcome, {{ auth('staff')->user()->name }}</h1>
                <p class="text-md text-base-content/70">Hotel: {{ auth('staff')->user()->hotel->name }} ({{ auth('staff')->user()->hotel->code }})</p>
            </div>

            <div class="stats stats-vertical lg:stats-horizontal shadow">
                <div class="stat">
                    <div class="stat-title">Today's Arrivals</div>
                    <div class="stat-value text-primary">{{ $stats['today_arrivals'] ?? 0 }}</div>
                    <div class="stat-desc">Scheduled to check-in</div>
                </div>

                <div class="stat">
                    <div class="stat-title">Today's Departures</div>
                    <div class="stat-value text-success">{{ $stats['today_departures'] ?? 0 }}</div>
                    <div class="stat-desc">Expected to check-out</div>
                </div>

                <div class="stat">
                    <div class="stat-title">In-House Guests</div>
                    <div class="stat-value text-secondary">{{ $stats['in_house'] ?? 0 }}</div>
                    <div class="stat-desc">Currently checked in</div>
                </div>

                <div class="stat">
                    <div class="stat-title">Rooms Out of Service</div>
                    <div class="stat-value text-error">{{ $stats['rooms_oos'] ?? 0 }}</div>
                    <div class="stat-desc">Track maintenance</div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="card-title">Revenue (last 7 days)</h2>
                                <p class="text-sm text-base-content/70">All payments captured for {{ $hotelName ?? 'hotel' }}.</p>
                            </div>
                            <div class="badge badge-primary badge-outline">${{ number_format(collect($revenueSeries)->sum('amount'), 2) }}</div>
                        </div>

                        @php
                            $revenueChart = collect($revenueSeries ?? [])
                                ->map(fn ($d) => [
                                    'label' => $d['label'] ?? '',
                                    'value' => $d['amount'] ?? 0,
                                    'date' => $d['date'] ?? null,
                                ])
                                ->all();
                        @endphp

                        <div class="mt-4">
                            <x-charts.line
                                :series="$revenueChart"
                                format="currency"
                                color-class="text-primary"
                                class="rounded-box bg-base-200/30 p-3"
                            />
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="card-title">Occupancy (last 7 days)</h2>
                                <p class="text-sm text-base-content/70">{{ $roomCount }} rooms currently available for assignment.</p>
                            </div>
                            <div class="badge badge-secondary badge-outline">Live snapshot</div>
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
                    </div>
                </div>
            </div>
        </div>
    </section>

    <x-slot name="scripts">
        <script>
            (function() {
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
                        const { x, y } = toContainerPx(containerRect, svgRect, svg, point.cx, point.cy);

                        tooltip.classList.remove('hidden');

                        const pad = 10;
                        const tooltipRect = tooltip.getBoundingClientRect();
                        const left = clamp(
                            x,
                            (tooltipRect.width / 2) + pad,
                            containerRect.width - (tooltipRect.width / 2) - pad
                        );
                        const top = clamp(
                            y,
                            tooltipRect.height + pad,
                            containerRect.height - pad
                        );

                        tooltip.style.left = `${left}px`;
                        tooltip.style.top = `${top - 10}px`;
                    };

                    const hide = () => {
                        tooltip.classList.add('hidden');
                        if (guide) guide.setAttribute('stroke-opacity', '0');
                        active = null;
                        for (const p of points) {
                            p.dot.setAttribute('r', '4.5');
                            p.ring.setAttribute('fill-opacity', '0.12');
                            p.ring.setAttribute('r', '9');
                        }
                    };

                    const onMove = (event) => {
                        const x = getSvgPointX(svg, event);
                        let nearest = points[0];
                        let best = Math.abs(points[0].cx - x);
                        for (let i = 1; i < points.length; i++) {
                            const d = Math.abs(points[i].cx - x);
                            if (d < best) {
                                best = d;
                                nearest = points[i];
                            }
                        }
                        setActive(nearest);
                        showTooltipAtPoint(nearest);
                    };

                    svg.addEventListener('mousemove', onMove);
                    svg.addEventListener('mouseenter', onMove);
                    svg.addEventListener('mouseleave', hide);

                    for (const p of points) {
                        p.hit.addEventListener('mouseenter', (event) => {
                            setActive(p);
                            showTooltipAtPoint(p);
                            event.stopPropagation();
                        });
                    }
                };

                const boot = () => {
                    document.querySelectorAll('[data-linechart]').forEach(initLineChart);
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', boot);
                } else {
                    boot();
                }
            })();
        </script>
    </x-slot>
</x-layouts.app.base>
