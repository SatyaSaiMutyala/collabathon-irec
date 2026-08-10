@props([
    'rows' => [],   // [['label' => 'Azure Bay', 'meta' => 'Skyline', 'value' => 19], ...]
    'color' => 'var(--color-chart-1)',
    'height' => 160,
])

@php
$rows = array_values($rows);
$max = 0;
foreach ($rows as $r) {
    $max = max($max, (float) $r['value']);
}
$max = $max ?: 1;
@endphp

<div x-data="{ hot: null }" class="flex items-end gap-3" style="height: {{ $height }}px">
    @forelse($rows as $i => $r)
        @php $pct = max((float) $r['value'] / $max * 100, 4); @endphp
        <div class="flex-1 h-full min-w-0 flex flex-col items-center justify-end gap-2"
             @mouseenter="hot = {{ $i }}" @mouseleave="hot = null">
            <span class="text-[11.5px] font-semibold text-ink nums transition-opacity duration-150"
                  :class="hot === {{ $i }} ? 'opacity-100' : 'opacity-0'">{{ number_format((float) $r['value']) }}</span>
            <div class="w-full rounded-t-lg transition-[filter] duration-150"
                 :class="hot === {{ $i }} && 'brightness-110'"
                 style="height: {{ round($pct, 1) }}%; background: {{ $color }}; min-height: 6px"></div>
            <div class="min-w-0 text-center">
                <p class="text-[11px] text-ink-2 truncate max-w-[72px]">{{ $r['label'] }}</p>
                @if(!empty($r['meta']))
                    <p class="text-[10px] text-ink-3 truncate max-w-[72px]">{{ $r['meta'] }}</p>
                @endif
            </div>
        </div>
    @empty
        <p class="text-[12.5px] text-ink-3 w-full text-center pb-6">No data for this period.</p>
    @endforelse
</div>
