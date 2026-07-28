@props(['variant' => 'primary', 'size' => 'md', 'tag' => 'button'])

@php
$variants = [
    'primary' => 'bg-navy text-white hover:bg-navy-soft',
    'gold' => 'bg-primary text-navy hover:bg-primary-dark hover:text-white',
    'outline' => 'border border-border text-navy hover:border-navy/30',
    'ghost' => 'text-text-secondary hover:text-navy',
    'danger-ghost' => 'text-danger hover:bg-danger-soft',
    'success-ghost' => 'text-success hover:bg-success-soft',
];
$sizes = [
    'sm' => 'px-3 py-1.5 text-[12.5px]',
    'md' => 'px-4 py-2 text-[13.5px]',
];
$classes = ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']);
@endphp

<{{ $tag }} {{ $attributes->merge(['class' => "inline-flex items-center justify-center gap-1.5 rounded-lg font-medium transition-colors whitespace-nowrap $classes"]) }}>
    {{ $slot }}
</{{ $tag }}>
