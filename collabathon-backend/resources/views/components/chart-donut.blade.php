@props([
    'segments' => [],   // [['label' => 'Viewed only', 'value' => 950, 'color' => 'var(--color-chart-1)'], ...] — mutually exclusive, so they sum to the total.
    'size' => 168,
    'stroke' => 26,
    'centerLabel' => 'Total',
])

@php
/**
 * Segmented ring, drawn as stacked circle strokes (dasharray/offset) rather than
 * arc paths — no trig needed, and a small gap between segments falls out of
 * shrinking each dash slightly rather than computing arc endpoints by hand.
 */
$segments = array_values($segments);
$total = array_sum(array_column($segments, 'value'));
$r = ($size - $stroke) / 2;
$circumference = 2 * M_PI * $r;
$gap = 3; // px of visual gap after each segment

$cursor = 0;
$arcs = [];
foreach ($segments as $s) {
    $value = (float) $s['value'];
    $pct = $total > 0 ? $value / $total : 0;
    $len = $pct * $circumference;
    $arcs[] = [
        'label' => $s['label'],
        'value' => $value,
        'pct' => $pct * 100,
        'color' => $s['color'],
        'dash' => max($len - $gap, 0),
        'gap' => $circumference - max($len - $gap, 0),
        'offset' => -$cursor,
    ];
    $cursor += $len;
}
@endphp

<div x-data="{ hover: null }" class="flex flex-col items-center">
    <div class="relative shrink-0" style="width: {{ $size }}px; height: {{ $size }}px">
        <svg viewBox="0 0 {{ $size }} {{ $size }}" width="{{ $size }}" height="{{ $size }}" class="-rotate-90">
            <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $r }}" fill="none"
                    stroke="var(--color-canvas)" stroke-width="{{ $stroke }}" />
            @foreach($arcs as $i => $a)
                <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $r }}" fill="none"
                        stroke="{{ $a['color'] }}" stroke-width="{{ $stroke }}" stroke-linecap="round"
                        stroke-dasharray="{{ round($a['dash'], 2) }} {{ round($a['gap'], 2) }}"
                        stroke-dashoffset="{{ round($a['offset'], 2) }}"
                        class="transition-[filter] duration-150 cursor-default"
                        :class="hover === {{ $i }} && 'brightness-110'"
                        @mouseenter="hover = {{ $i }}" @mouseleave="hover = null" />
            @endforeach
        </svg>

        {{-- Center content: totals by default, the hovered segment's own figures when hot. --}}
        <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-2 pointer-events-none">
            <template x-if="hover === null">
                <div>
                    <p class="text-[20px] font-semibold text-ink leading-none tracking-[-0.02em] nums">{{ number_format($total) }}</p>
                    <p class="text-[11px] text-ink-3 mt-1">{{ $centerLabel }}</p>
                </div>
            </template>
            @foreach($arcs as $i => $a)
                <template x-if="hover === {{ $i }}">
                    <div>
                        <p class="text-[19px] font-semibold text-ink leading-none tracking-[-0.02em] nums">{{ number_format($a['value']) }}</p>
                        <p class="text-[11px] text-ink-3 mt-1 truncate max-w-[90px]">{{ $a['label'] }}</p>
                    </div>
                </template>
            @endforeach
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 mt-4">
        @foreach($arcs as $i => $a)
            <button type="button" class="flex items-center gap-1.5 cursor-default"
                    @mouseenter="hover = {{ $i }}" @mouseleave="hover = null">
                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background: {{ $a['color'] }}"></span>
                <span class="text-[12px] text-ink-2">{{ $a['label'] }}</span>
                <span class="text-[12px] font-semibold text-ink nums">{{ number_format($a['pct'], 0) }}%</span>
            </button>
        @endforeach
    </div>
</div>
