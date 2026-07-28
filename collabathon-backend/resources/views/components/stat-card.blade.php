@props([
    'icon' => null,
    'label' => '',
    'value' => 0,
    'delta' => null,
    'goodWhenUp' => true,
    'spark' => [],
    'period' => 'vs last month',
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
@endphp

<div {{ $attributes->merge(['class' => 'bg-panel border border-line rounded-xl px-4 py-3.5 shadow-card flex flex-col gap-2.5 min-w-0']) }}>

    <div class="flex items-center gap-2 min-w-0">
        @if($icon)
            <x-icon :name="$icon" class="w-4 h-4 text-ink-3 shrink-0" />
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
            <x-sparkline :values="$spark" color="var(--color-chart-1)" :width="76" :height="26"
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
