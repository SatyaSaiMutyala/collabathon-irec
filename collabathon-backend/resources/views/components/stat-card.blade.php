@props([
    'icon' => null,
    'label' => '',
    'value' => 0,
    'delta' => null,
    'goodWhenUp' => true,
    'spark' => [],
    'period' => 'vs last month',
    // Opt-in only — every card on every non-dashboard page omits this and keeps the
    // original plain icon exactly as before. Only the dashboard's top KPI row passes
    // one, so that row alone reads as colourful instead of every stat tile in the app.
    'color' => null,
])

@php
// Auto-compact so the tile never wraps: 1,284 / 12.9K / 4.2M
$n = is_numeric($value) ? (float) $value : null;
$display = $n === null
    ? $value
    : ($n >= 1_000_000 ? rtrim(rtrim(number_format($n / 1_000_000, 1), '0'), '.') . 'M'
      : ($n >= 10_000  ? rtrim(rtrim(number_format($n / 1_000, 1), '0'), '.') . 'K'
      : number_format($n)));

$up = $delta !== null && $delta >= 0;
// Colour by outcome, not by sign — a rising approval backlog is not good news.
$favourable = $up === (bool) $goodWhenUp;
// The delta text carries direction (arrow + colour). The sparkline stays in the
// de-emphasis hue — status colours are reserved and must not double as trend ink.
$deltaClass = $delta === null ? '' : ($favourable ? 'text-success' : 'text-danger');

// Badge + sparkline tint per tile. Status hues are used where the metric genuinely is
// that status (pending -> warning, accepted -> success) rather than as decoration; the
// three plain counts (developers/brokers/listings) are spread across blue/orange/red
// specifically so no two are from the same warm-earth family — `chart-2` (a burnt
// sienna) sat right next to `primary` (a burnt orange) and the two badges were, in
// practice, indistinguishable at a glance. `danger` is not a "something's wrong" read
// here — there is no negative context on this tile, just a colour — and it is the one
// hue in the palette that is neither blue, green, amber nor orange.
//
// Badges use a `/32` opacity of the solid hue rather than the design system's `-soft`
// tokens (e.g. `bg-primary-soft`, ~3-6% tint) — those read as barely-there pastel next
// to a plain white card and were the "very light colours" complaint. `/22` (tried
// first) was still too close to the `-soft` tokens to read as a real colour; `/32` is
// the same solid colour at a third of its strength, clearly present without darkening
// the icon glyph's own contrast against it.
$palette = [
    'primary' => ['badge' => 'bg-primary/32 text-primary-dark', 'spark' => 'var(--color-primary)'],
    'info'    => ['badge' => 'bg-info/32 text-info',            'spark' => 'var(--color-info)'],
    'success' => ['badge' => 'bg-success/32 text-success',      'spark' => 'var(--color-success)'],
    'warning' => ['badge' => 'bg-warning/32 text-warning',      'spark' => 'var(--color-warning)'],
    'danger'  => ['badge' => 'bg-danger/32 text-danger',        'spark' => 'var(--color-danger)'],
    'chart-2' => ['badge' => 'bg-[var(--color-chart-2)]/32 text-[var(--color-chart-2)]', 'spark' => 'var(--color-chart-2)'],
    'teal'    => ['badge' => 'bg-[var(--color-accent-teal)]/32 text-[var(--color-accent-teal)]', 'spark' => 'var(--color-accent-teal)'],
    'violet'  => ['badge' => 'bg-[var(--color-accent-violet)]/32 text-[var(--color-accent-violet)]', 'spark' => 'var(--color-accent-violet)'],
    // Deliberately muted rather than colourless — for a tile that is a rollup rather
    // than its own category (e.g. "Total actions" summing every other tile). A plain
    // `bg-canvas` badge was literally the same hex as the page background (invisible),
    // and its sparkline reused `chart-1`, the exact blue "Developers" already owns —
    // two cards with identical-looking trend lines. Charcoal ink reads as neutral
    // without either problem.
    'neutral' => ['badge' => 'bg-ink-3/28 text-ink-2', 'spark' => 'var(--color-ink-2)'],
];
$tone = $palette[$color] ?? null;
$sparkColor = $tone['spark'] ?? 'var(--color-chart-1)';
@endphp

<div {{ $attributes->merge(['class' => 'bg-panel border border-line rounded-2xl px-4 py-3.5 shadow-card flex flex-col gap-2.5 min-w-0']) }}>

    <div class="flex items-center gap-2 min-w-0">
        @if($icon)
            @if($tone)
                <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 {{ $tone['badge'] }}">
                    <x-icon :name="$icon" class="w-3.5 h-3.5" />
                </span>
            @else
                <x-icon :name="$icon" class="w-4 h-4 text-ink-3 shrink-0" />
            @endif
        @endif
        {{-- Wraps rather than truncates: a clipped label ("Confirmed matc…") is worse
             than two lines, and grid rows equalise the height anyway. --}}
        <p class="text-[12.5px] text-ink-2 leading-snug min-w-0">{{ $label }}</p>
    </div>

    {{-- Value and sparkline share a row; the delta gets its own full-width row below.
         Packing all three onto one line makes the period text collide with the
         sparkline once the card drops under ~230px. --}}
    <div class="flex items-end justify-between gap-2 min-w-0">
        {{-- Display figure: proportional digits, never tabular-nums --}}
        <p class="text-[26px] leading-none font-semibold text-ink tracking-[-0.02em]">{{ $display }}</p>

        @if(!empty($spark))
            <x-sparkline :values="$spark" :color="$sparkColor" :width="104" :height="36"
                         class="hidden sm:block mb-0.5" />
        @endif
    </div>

    @if($delta !== null)
        <p class="flex items-center gap-1 text-[11.5px] whitespace-nowrap min-w-0 {{ $deltaClass }}">
            <x-icon :name="$up ? 'arrow-up' : 'arrow-down'" class="w-3 h-3 shrink-0" />
            <span class="font-medium nums">{{ number_format(abs($delta), 1) }}%</span>
            <span class="text-ink-3 font-normal truncate">{{ $period }}</span>
        </p>
    @endif
</div>
