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

<div class="space-y-4">
    @foreach($rows as $i => $r)
        @php $pct = (float) $r['value'] / $max * 100; @endphp
        <div x-data="{ hot: false }" @mouseenter="hot = true" @mouseleave="hot = false" class="group flex items-center gap-3">
            {{-- Rank — position already carries the order, this just makes a "top 5"
                 read as a ranking at a glance instead of a plain list. --}}
            <span class="w-5 h-5 shrink-0 flex items-center justify-center bg-canvas text-[10.5px] font-semibold text-ink-3 nums">
                {{ $i + 1 }}
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex items-baseline justify-between gap-3 mb-1.5">
                    <div class="min-w-0">
                        <p class="text-[12.5px] text-ink truncate">{{ $r['label'] }}</p>
                        @if(!empty($r['meta']))
                            <p class="text-[11px] text-ink-3 truncate">{{ $r['meta'] }}</p>
                        @endif
                    </div>
                    <span class="text-[13.5px] font-semibold text-ink nums shrink-0">{{ number_format((float) $r['value']) }}</span>
                </div>

                {{-- Bar: <=24px thick, square ends — corners are 0 across the whole UI.
                     The tip marker (a slightly taller accent at the fill's end) is what
                     anchors the value above to *this* bar instead of just labelling a row. --}}
                <div class="relative h-4 bg-canvas overflow-hidden">
                    <div class="h-full transition-[filter,width] duration-150"
                         :class="hot && 'brightness-110'"
                         style="width: {{ round(max($pct, 1.5), 2) }}%; background: {{ $color }}"></div>
                    <div class="absolute top-0 bottom-0 w-[2px] bg-panel"
                         style="left: calc({{ round(max($pct, 1.5), 2) }}% - 2px)"></div>
                </div>
            </div>
        </div>
    @endforeach

    @if(empty($rows))
        <p class="text-[12.5px] text-ink-3 py-6 text-center">No data for this period.</p>
    @endif
</div>
