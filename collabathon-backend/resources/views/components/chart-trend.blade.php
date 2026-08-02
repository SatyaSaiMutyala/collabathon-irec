@props([
    'points' => [],
    'series' => [],
    'height' => 250,
])

@php
/**
 * Multi-series line chart on a SINGLE shared axis — every series here is a
 * count of brokers, so one scale is correct. Never add a second y-axis.
 *
 * $points  [['label' => '3 Mar', 'views' => 42, 'interests' => 6], …]
 * $series  [['key' => 'views', 'label' => 'Views', 'color' => 'var(--color-chart-1)'], …]
 */
$points = array_values($points);
$n = count($points);

// viewBox geometry — right pad leaves room for the direct end labels.
$vbW = 720.0;
$vbH = (float) $height;
$padL = 40.0; $padR = 74.0; $padT = 16.0; $padB = 28.0;
$plotW = $vbW - $padL - $padR;
$plotH = $vbH - $padT - $padB;

$max = 0;
foreach ($points as $p) {
    foreach ($series as $s) {
        $max = max($max, (float) ($p[$s['key']] ?? 0));
    }
}

// Round the axis to a clean ceiling with at most four gridline steps.
$step = 1;
foreach ([1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000, 5000] as $candidate) {
    if (ceil(max($max, 1) / $candidate) <= 4) { $step = $candidate; break; }
}
$yMax = max($step, (int) (ceil($max / $step) * $step));

$xAt = fn (int $i) => $n > 1 ? $padL + ($i / ($n - 1)) * $plotW : $padL + $plotW / 2;
$yAt = fn (float $v) => $padT + (1 - ($v / $yMax)) * $plotH;

$ticks = [];
for ($v = 0; $v <= $yMax; $v += $step) {
    $ticks[] = $v;
}

// Percentages drive the HTML hover overlay so it lines up with the SVG plot area.
$leftPct  = $padL / $vbW * 100;
$rightPct = $padR / $vbW * 100;
$plotPct  = 100 - $leftPct - $rightPct;
$xPcts = [];
for ($i = 0; $i < $n; $i++) {
    $xPcts[] = $n > 1 ? $leftPct + ($i / ($n - 1)) * $plotPct : $leftPct + $plotPct / 2;
}
$halfBand = $n > 1 ? $plotPct / ($n - 1) / 2 : $plotPct / 2;

$tooltip = array_map(fn ($p) => [
    'label' => $p['label'],
    'values' => array_map(fn ($s) => (float) ($p[$s['key']] ?? 0), $series),
], $points);

$legend = array_map(fn ($s) => ['label' => $s['label'], 'color' => $s['color']], $series);
// Show every other x tick when the series is dense, so labels never collide.
$labelEvery = $n > 8 ? 2 : 1;

/**
 * Direct end-labels only work while the series separate at the right edge. When they
 * converge — which real data does constantly — stacked labels detach from their lines
 * and read as noise. Measure the gap first; if it is too tight, drop the direct labels
 * and let the legend plus the hover tooltip carry identity.
 */
$lastYs = [];
foreach ($series as $s) {
    $lastYs[] = $yAt((float) ($points[$n - 1][$s['key']] ?? 0));
}
sort($lastYs);

$minGap = PHP_INT_MAX;
for ($i = 1; $i < count($lastYs); $i++) {
    $minGap = min($minGap, $lastYs[$i] - $lastYs[$i - 1]);
}

// Each label block is ~24 units tall (name line + value line).
$showEndLabels = count($series) < 2 || $minGap >= 26;
@endphp

{{-- The viewBox scales every label with the width, so below ~540px the axis text
     becomes unreadable. Hold a legible floor and let the panel scroll instead. --}}
<div class="w-full overflow-x-auto scrollbar-slim">
<div class="min-w-[540px]">

<div x-data="{
        hover: null,
        xs: @js($xPcts),
        rows: @js($tooltip),
        legend: @js($legend),
        shift(i) { return i === 0 ? '0%' : (i === this.xs.length - 1 ? '-100%' : '-50%'); }
     }"
     class="relative w-full"
     @mouseleave="hover = null">

    <svg viewBox="0 0 {{ $vbW }} {{ $vbH }}" class="w-full h-auto block" role="img"
         aria-label="Weekly listing views and interests across the platform">

        {{-- Gridlines: hairline, solid, one step off the surface --}}
        @foreach($ticks as $t)
            <line x1="{{ $padL }}" y1="{{ round($yAt($t), 2) }}" x2="{{ $padL + $plotW }}" y2="{{ round($yAt($t), 2) }}"
                  stroke="var(--color-grid)" stroke-width="1" shape-rendering="crispEdges" />
            <text x="{{ $padL - 10 }}" y="{{ round($yAt($t) + 3.5, 2) }}" text-anchor="end"
                  font-size="10.5" fill="var(--color-ink-3)" style="font-variant-numeric: tabular-nums">{{ number_format($t) }}</text>
        @endforeach

        {{-- x labels --}}
        @foreach($points as $i => $p)
            @if($i % $labelEvery === 0 || $i === $n - 1)
                <text x="{{ round($xAt($i), 2) }}" y="{{ $vbH - 8 }}" text-anchor="middle"
                      font-size="10.5" fill="var(--color-ink-3)">{{ $p['label'] }}</text>
            @endif
        @endforeach

        {{-- Series: 2px lines, round caps --}}
        @foreach($series as $s)
            @php
                $d = [];
                foreach ($points as $i => $p) {
                    $d[] = round($xAt($i), 2) . ',' . round($yAt((float) ($p[$s['key']] ?? 0)), 2);
                }
                $lastVal = (float) ($points[$n - 1][$s['key']] ?? 0);
            @endphp
            <polyline points="{{ implode(' ', $d) }}" fill="none" stroke="{{ $s['color'] }}"
                      stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />

            {{-- End marker: r>=4 with a 2px surface ring --}}
            <circle cx="{{ round($xAt($n - 1), 2) }}" cy="{{ round($yAt($lastVal), 2) }}" r="4"
                    fill="{{ $s['color'] }}" stroke="var(--color-panel)" stroke-width="2" />

            {{-- Direct end label, only when the series are far enough apart to read.
                 Text wears ink tokens; the dot beside it carries identity. --}}
            @if($showEndLabels)
                <text x="{{ round($xAt($n - 1) + 12, 2) }}" y="{{ round($yAt($lastVal) - 5, 2) }}"
                      font-size="10.5" fill="var(--color-ink-3)">{{ $s['label'] }}</text>
                <text x="{{ round($xAt($n - 1) + 12, 2) }}" y="{{ round($yAt($lastVal) + 7, 2) }}"
                      font-size="12.5" font-weight="600" fill="var(--color-ink)"
                      style="font-variant-numeric: tabular-nums">{{ number_format($lastVal) }}</text>
            @endif
        @endforeach

        {{-- Crosshair + focused markers.
             x-show (not x-if/<template>) so these stay plain SVG nodes — a
             <template> inside <svg> is namespace-fragile across parsers. --}}
        <line x-show="hover !== null" x-cloak
              :x1="{{ $padL }} + ((hover ?? 0) / {{ max($n - 1, 1) }}) * {{ $plotW }}" y1="{{ $padT }}"
              :x2="{{ $padL }} + ((hover ?? 0) / {{ max($n - 1, 1) }}) * {{ $plotW }}" y2="{{ $padT + $plotH }}"
              stroke="var(--color-ink-3)" stroke-width="1" />

        @foreach($series as $si => $s)
            <circle x-show="hover !== null" x-cloak
                    :cx="{{ $padL }} + ((hover ?? 0) / {{ max($n - 1, 1) }}) * {{ $plotW }}"
                    :cy="{{ $padT }} + (1 - ((hover === null ? 0 : rows[hover].values[{{ $si }}]) / {{ $yMax }})) * {{ $plotH }}"
                    r="4.5" fill="{{ $s['color'] }}" stroke="var(--color-panel)" stroke-width="2" />
        @endforeach
    </svg>

    {{-- Hover hit areas, wider than the marks --}}
    <div class="absolute inset-0">
        @foreach($points as $i => $p)
            <button type="button"
                    class="absolute top-0 bottom-0 cursor-default"
                    style="left: {{ round(max(0, $xPcts[$i] - $halfBand), 3) }}%; width: {{ round($halfBand * 2, 3) }}%"
                    @mouseenter="hover = {{ $i }}" @focus="hover = {{ $i }}"
                    aria-label="{{ $p['label'] }}"></button>
        @endforeach
    </div>

    {{-- Tooltip --}}
    {{-- Bindings still evaluate while hidden, so every read of `hover` is guarded. --}}
    <div x-show="hover !== null" x-cloak
         class="absolute top-1 z-20 pointer-events-none"
         :style="`left: ${xs[hover ?? 0]}%; transform: translateX(${shift(hover ?? 0)})`">
        <div class="bg-panel border border-line rounded-lg shadow-pop px-3 py-2 min-w-[128px]">
            <p class="text-[11px] text-ink-3 mb-1.5" x-text="hover === null ? '' : rows[hover].label"></p>
            <template x-for="(s, i) in legend" :key="s.label">
                <div class="flex items-center justify-between gap-4 py-0.5">
                    <span class="flex items-center gap-1.5 min-w-0">
                        <span class="w-2 h-2 shrink-0" :style="`background:${s.color}`"></span>
                        <span class="text-[12px] text-ink-2 truncate" x-text="s.label"></span>
                    </span>
                    <span class="text-[12.5px] font-semibold text-ink nums"
                          x-text="hover === null ? '' : rows[hover].values[i].toLocaleString()"></span>
                </div>
            </template>
        </div>
    </div>
</div>

</div>{{-- /min-w --}}
</div>{{-- /scroller --}}

{{-- Legend is always present for two or more series. When the end-labels were
     suppressed for collision, the latest value moves here so nothing is lost. --}}
@if(count($series) > 1)
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 mt-3 pl-1">
        @foreach($series as $s)
            <span class="flex items-baseline gap-1.5">
                <span class="w-2.5 h-2.5 self-center" style="background: {{ $s['color'] }}"></span>
                <span class="text-[12px] text-ink-2">{{ $s['label'] }}</span>
                @unless($showEndLabels)
                    <span class="text-[12px] font-semibold text-ink nums">
                        {{ number_format((float) ($points[$n - 1][$s['key']] ?? 0)) }}
                    </span>
                @endunless
            </span>
        @endforeach
    </div>
@endif
