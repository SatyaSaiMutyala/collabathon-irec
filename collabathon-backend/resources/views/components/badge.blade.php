@props(['tone' => 'neutral'])

@php
$tones = [
    'success' => 'bg-success-soft text-success',
    'danger' => 'bg-danger-soft text-danger',
    'warning' => 'bg-warning-soft text-warning',
    'primary' => 'bg-primary-soft text-primary-dark',
    'neutral' => 'bg-navy/5 text-text-secondary',
];
$classes = $tones[$tone] ?? $tones['neutral'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium tracking-wide $classes"]) }}>
    {{ $slot }}
</span>
