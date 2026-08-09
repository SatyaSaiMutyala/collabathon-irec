@props(['values' => [], 'color' => 'var(--color-chart-1)', 'width' => 120, 'height' => 32])

@php
$values = array_values(array_map('floatval', $values));
$n = count($values);
$min = $n ? min($values) : 0;
$max = $n ? max($values) : 1;
$span = ($max - $min) ?: 1;

// Inset by the marker radius so the end dot is never clipped by the viewBox.
$pad = 3.5;
$w = (float) $width;
$h = (float) $height;

$pt = function (int $i) use ($values, $n, $min, $span, $pad, $w, $h) {
    $x = $n > 1 ? $pad + ($i / ($n - 1)) * ($w - 2 * $pad) : $w / 2;
    $y = $h - $pad - (($values[$i] - $min) / $span) * ($h - 2 * $pad);
    return [round($x, 2), round($y, 2)];
};

$coords = [];
for ($i = 0; $i < $n; $i++) {
    $coords[] = $pt($i);
}
// Monotone cubic, not a straight polyline — see SmoothPath's own docblock. Sparse
// data (long flat run then a late jump, typical of a 12-week window over a handful
// of records) drew as a jagged "hockey stick" angle; this reads as a trend line.
$line = \App\Support\SmoothPath::line($coords);
$last = $coords ? end($coords) : null;
@endphp

@if($n > 1)
    <svg viewBox="0 0 {{ $w }} {{ $h }}" width="{{ $w }}" height="{{ $h }}" fill="none"
         {{ $attributes->merge(['class' => 'overflow-visible shrink-0']) }} aria-hidden="true">
        <path d="{{ $line }}" fill="none" stroke="{{ $color }}" stroke-width="2.25"
              stroke-linecap="round" stroke-linejoin="round" opacity="0.85" />
        {{-- Current period in the accent; 2px surface ring keeps it legible over the line. --}}
        <circle cx="{{ $last[0] }}" cy="{{ $last[1] }}" r="3.25"
                fill="{{ $color }}" stroke="var(--color-panel)" stroke-width="2" />
    </svg>
@endif
