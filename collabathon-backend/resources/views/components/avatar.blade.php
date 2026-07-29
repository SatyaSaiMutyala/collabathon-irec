@props(['name' => '', 'size' => 'md', 'tone' => null])

@php
$parts = preg_split('/\s+/', trim($name)) ?: [];
$initials = strtoupper(
    mb_substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '')
);

$sizes = [
    'xs' => 'w-6 h-6 text-[10px]',
    'sm' => 'w-7 h-7 text-[11px]',
    'md' => 'w-9 h-9 text-[12.5px]',
    'lg' => 'w-11 h-11 text-[15px]',
];

// Deterministic tone from the name so the same person keeps the same swatch.
$palette = [
    'bg-primary-soft text-primary-dark ring-primary-ring',
    'bg-info-soft text-info ring-info-ring',
    'bg-success-soft text-success ring-success-ring',
    'bg-warning-soft text-warning ring-warning-ring',
];
$chosen = $tone ?? $palette[crc32($name) % count($palette)];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-full font-semibold ring-1 ring-inset shrink-0 select-none ' . ($sizes[$size] ?? $sizes['md']) . ' ' . $chosen]) }}
      title="{{ $name }}">
    {{ $initials ?: '—' }}
</span>
