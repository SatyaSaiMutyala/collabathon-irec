@props([
    'value' => 0,      // 0-100
    'label' => '',
    'color' => 'var(--color-chart-1)',
    'size' => 96,
    'stroke' => 9,
])

@php
$pct = max(0, min(100, (float) $value));
$r = ($size - $stroke) / 2;
$circumference = 2 * M_PI * $r;
$dash = $pct / 100 * $circumference;
@endphp

<div class="relative shrink-0 flex flex-col items-center justify-center rounded-full bg-panel shadow-raised"
     style="width: {{ $size }}px; height: {{ $size }}px">
    <svg viewBox="0 0 {{ $size }} {{ $size }}" width="{{ $size }}" height="{{ $size }}" class="-rotate-90 absolute inset-0">
        <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $r }}" fill="none"
                stroke="var(--color-canvas)" stroke-width="{{ $stroke }}" />
        <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $r }}" fill="none"
                stroke="{{ $color }}" stroke-width="{{ $stroke }}" stroke-linecap="round"
                stroke-dasharray="{{ round($dash, 2) }} {{ round($circumference - $dash, 2) }}" />
    </svg>
    <div class="relative text-center px-1">
        <p class="text-[15px] font-semibold text-ink leading-none tracking-[-0.01em] nums">{{ number_format($pct, 0) }}%</p>
        <p class="text-[9.5px] text-ink-3 mt-1 leading-tight">{{ $label }}</p>
    </div>
</div>
