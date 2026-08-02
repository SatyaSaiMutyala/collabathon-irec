@props(['rows' => [], 'valueLabel' => 'value', 'color' => 'var(--color-chart-1)'])

@php
/**
 * Ranked magnitude across categories — ONE measure, so one hue for every bar.
 * Colour here would encode nothing (rank is already the y-position).
 * $rows [['label' => 'Azure Bay', 'meta' => 'Skyline', 'value' => 19], …]
 */
$rows = array_values($rows);
$max = 0;
foreach ($rows as $r) {
    $max = max($max, (float) $r['value']);
}
$max = $max ?: 1;
@endphp

<div class="space-y-3">
    @foreach($rows as $r)
        @php $pct = (float) $r['value'] / $max * 100; @endphp
        <div x-data="{ hot: false }" @mouseenter="hot = true" @mouseleave="hot = false"
             class="group grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 gap-y-1 items-center">
            <div class="min-w-0">
                <p class="text-[12.5px] text-ink truncate">{{ $r['label'] }}</p>
                @if(!empty($r['meta']))
                    <p class="text-[11px] text-ink-3 truncate">{{ $r['meta'] }}</p>
                @endif
            </div>
            <span class="text-[13px] font-semibold text-ink nums">{{ number_format((float) $r['value']) }}</span>

            {{-- Bar: <=24px thick, square ends — corners are 0 across the whole UI --}}
            <div class="col-span-2 h-[10px] bg-canvas overflow-hidden">
                <div class="h-full transition-[filter,width] duration-150"
                     :class="hot && 'brightness-110'"
                     style="width: {{ round(max($pct, 1.5), 2) }}%; background: {{ $color }}"></div>
            </div>
        </div>
    @endforeach

    @if(empty($rows))
        <p class="text-[12.5px] text-ink-3 py-6 text-center">No data for this period.</p>
    @endif
</div>
