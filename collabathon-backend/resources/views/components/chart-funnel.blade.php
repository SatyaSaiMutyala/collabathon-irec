@props(['stages' => []])

@php
/**
 * Ordinal funnel — each stage is a strict subset of the one before it, so the
 * ramp is a single hue stepping light→dark (never categorical hues).
 * Ramp validated: monotone lightness, adjacent ΔL >= 0.06, light end 2.26:1.
 */
$stages = array_values($stages);
$ramp = ['var(--color-funnel-1)', 'var(--color-funnel-2)', 'var(--color-funnel-3)'];
$top = $stages ? (float) $stages[0]['value'] : 1;
@endphp

<div class="space-y-3.5">
    @foreach($stages as $i => $stage)
        @php
            $value = (float) $stage['value'];
            $pctOfTop = $top > 0 ? $value / $top * 100 : 0;
            $prev = $i > 0 ? (float) $stages[$i - 1]['value'] : null;
            $conv = $prev && $prev > 0 ? $value / $prev * 100 : null;
            $color = $ramp[min($i, count($ramp) - 1)];
        @endphp
        <div x-data="{ hot: false }" @mouseenter="hot = true" @mouseleave="hot = false" class="relative">
            <div class="flex items-baseline justify-between gap-3 mb-1.5">
                <span class="text-[12.5px] text-ink-2">{{ $stage['stage'] }}</span>
                <span class="flex items-baseline gap-2">
                    @if($conv !== null)
                        <span class="text-[11px] text-ink-3 nums">{{ number_format($conv, 1) }}% of previous</span>
                    @endif
                    <span class="text-[13.5px] font-semibold text-ink nums">{{ number_format($value) }}</span>
                </span>
            </div>
            {{-- Track is a lighter step of the same ramp; bar capped at 24px thick --}}
            <div class="h-[14px] rounded-[4px] bg-canvas overflow-hidden">
                <div class="h-full rounded-r-[4px] transition-[filter] duration-150"
                     :class="hot && 'brightness-110'"
                     style="width: {{ round(max($pctOfTop, 1.5), 2) }}%; background: {{ $color }}"></div>
            </div>
        </div>
    @endforeach
</div>
