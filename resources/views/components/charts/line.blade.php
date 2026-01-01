@props([
    'series' => [],
    'format' => 'number', // number|currency|percent
    'colorClass' => 'text-primary',
    'min' => 0,
    'max' => null,
    'height' => 200, // viewBox height (not CSS height)
])

@php
    $chartId = 'lc-' . (string) \Illuminate\Support\Str::uuid();

    $normalized = collect($series)
        ->map(function ($row) {
            $label = data_get($row, 'label', '');
            $value = data_get($row, 'value', data_get($row, 'amount', data_get($row, 'rate', 0)));
            return [
                'label' => (string) $label,
                'value' => is_numeric($value) ? (float) $value : 0.0,
                'meta' => is_array($row) ? $row : [],
            ];
        })
        ->values()
        ->all();

    $count = count($normalized);

    $vbWidth = 640;
    $vbHeight = max(140, (int) $height);
    $padX = 34;
    $padY = 30;
    $baselineY = $vbHeight - $padY;
    $usableW = $vbWidth - ($padX * 2);
    $usableH = $vbHeight - ($padY * 2);

    $minValue = is_numeric($min) ? (float) $min : 0.0;
    $maxCandidate = collect($normalized)->max('value') ?? 0.0;
    $maxValue = $max !== null && is_numeric($max) ? (float) $max : (float) $maxCandidate;

    if ($format === 'percent' && $max === null) {
        $maxValue = 100.0;
    }

    if ($maxValue <= $minValue) {
        $maxValue = $minValue + 1;
    }

    $stepX = $count > 1 ? ($usableW / ($count - 1)) : 0.0;

    $points = [];
    foreach ($normalized as $i => $row) {
        $x = $padX + ($i * $stepX);
        $value = (float) $row['value'];
        $ratio = ($value - $minValue) / ($maxValue - $minValue);
        $ratio = max(0.0, min(1.0, $ratio));
        $y = $baselineY - ($ratio * $usableH);

        $display = match ($format) {
            'currency' => '$' . number_format($value, 2),
            'percent' => (string) ((int) round($value)) . '%',
            default => (string) $value,
        };

        $meta = $row['meta'] ?? [];
        $subtitle = '';
        if ($format === 'percent' && isset($meta['occupied'])) {
            $subtitle = (string) $meta['occupied'] . ' rooms occupied';
        }
        if ($format === 'currency' && isset($meta['date'])) {
            $subtitle = (string) $meta['date'];
        }

        $points[] = [
            'x' => round($x, 2),
            'y' => round($y, 2),
            'label' => $row['label'],
            'value' => $value,
            'display' => $display,
            'subtitle' => $subtitle,
        ];
    }

    $linePath = '';
    if ($count >= 2) {
        $linePath = collect($points)
            ->map(fn ($p, $idx) => ($idx === 0 ? 'M' : 'L') . " {$p['x']} {$p['y']}")
            ->implode(' ');
    } elseif ($count === 1) {
        $p = $points[0];
        $linePath = "M {$p['x']} {$p['y']}";
    }

    $areaPath = '';
    if ($count >= 2) {
        $first = $points[0];
        $last = $points[$count - 1];
        $areaPath = $linePath . " L {$last['x']} {$baselineY} L {$first['x']} {$baselineY} Z";
    }

    $gridCount = 4;
    $gridYs = collect(range(0, $gridCount))
        ->map(fn ($i) => $padY + (($usableH / $gridCount) * $i))
        ->all();

    $xLabelEvery = $count > 6 ? 2 : 1;
@endphp

<div
    {{ $attributes->merge(['class' => 'relative']) }}
    data-linechart
    data-format="{{ $format }}"
>
    @if ($count === 0)
        <div class="flex h-44 items-center justify-center rounded-box bg-base-200/60 text-sm text-base-content/70">
            No data to display.
        </div>
    @else
        <svg
            class="w-full {{ $colorClass }} select-none"
            viewBox="0 0 {{ $vbWidth }} {{ $vbHeight }}"
            role="img"
            aria-label="Line chart"
        >
            <defs>
                <linearGradient id="{{ $chartId }}-fill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="currentColor" stop-opacity="0.26" />
                    <stop offset="55%" stop-color="currentColor" stop-opacity="0.10" />
                    <stop offset="100%" stop-color="currentColor" stop-opacity="0.00" />
                </linearGradient>
                <filter id="{{ $chartId }}-shadow" x="-30%" y="-30%" width="160%" height="160%">
                    <feDropShadow dx="0" dy="10" stdDeviation="10" flood-color="currentColor" flood-opacity="0.20" />
                </filter>
            </defs>

            @foreach ($gridYs as $y)
                <line x1="{{ $padX }}" x2="{{ $vbWidth - $padX }}" y1="{{ $y }}" y2="{{ $y }}" stroke="currentColor" stroke-opacity="0.08" />
            @endforeach

            @if ($areaPath)
                <path d="{{ $areaPath }}" fill="url(#{{ $chartId }}-fill)" />
            @endif

            <line
                data-guide
                x1="{{ $points[0]['x'] }}"
                x2="{{ $points[0]['x'] }}"
                y1="{{ $padY }}"
                y2="{{ $baselineY }}"
                stroke="currentColor"
                stroke-opacity="0"
                stroke-dasharray="4 6"
            />

            <path
                d="{{ $linePath }}"
                fill="none"
                stroke="currentColor"
                stroke-width="3.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                filter="url(#{{ $chartId }}-shadow)"
            />

            @foreach ($points as $idx => $p)
                <g
                    data-point
                    data-label="{{ $p['label'] }}"
                    data-display="{{ $p['display'] }}"
                    @if ($p['subtitle']) data-subtitle="{{ $p['subtitle'] }}" @endif
                >
                    <circle data-hit cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="14" fill="transparent" style="cursor: pointer;" />
                    <circle data-ring cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="9" fill="currentColor" fill-opacity="0.12" pointer-events="none" />
                    <circle data-dot cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="4.5" fill="currentColor" pointer-events="none" />

                    @if (($idx % $xLabelEvery) === 0)
                        <text
                            x="{{ $p['x'] }}"
                            y="{{ $vbHeight - 10 }}"
                            text-anchor="middle"
                            fill="currentColor"
                            fill-opacity="0.60"
                            font-size="12"
                        >
                            {{ $p['label'] }}
                        </text>
                    @endif
                </g>
            @endforeach
        </svg>

        <div
            data-tooltip
            class="pointer-events-none absolute left-0 top-0 hidden -translate-x-1/2 -translate-y-full rounded-box border border-base-content/10 bg-base-100/95 px-3 py-2 text-xs shadow-lg backdrop-blur"
        >
            <div class="font-semibold text-base-content" data-tooltip-title></div>
            <div class="text-base-content/70" data-tooltip-subtitle></div>
        </div>
    @endif
</div>

