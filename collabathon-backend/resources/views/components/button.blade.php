@props([
    'variant' => 'primary',
    'size' => 'md',
    'tag' => 'button',
    'icon' => null,
    'iconRight' => null,
])

@php
$variants = [
    'primary'       => 'bg-nav text-white hover:bg-nav-soft shadow-card',
    'gold'          => 'bg-primary text-white hover:bg-primary-dark shadow-card',
    'outline'       => 'bg-panel border border-line text-ink hover:bg-canvas hover:border-ink-3',
    'subtle'        => 'bg-canvas text-ink-2 hover:bg-line-soft hover:text-ink',
    'ghost'         => 'text-ink-2 hover:bg-canvas hover:text-ink',
    'danger'        => 'bg-danger text-white hover:brightness-110 shadow-card',
    'danger-ghost'  => 'text-danger hover:bg-danger-soft',
    'success-ghost' => 'text-success hover:bg-success-soft',
];

$sizes = [
    'xs' => 'h-7 px-2.5 text-[12px] gap-1.5 rounded-md',
    'sm' => 'h-8 px-3 text-[12.5px] gap-1.5 rounded-lg',
    'md' => 'h-9 px-4 text-[13.5px] gap-2 rounded-lg',
    'lg' => 'h-11 px-5 text-[14px] gap-2 rounded-xl',
];

$iconSizes = ['xs' => 'w-3.5 h-3.5', 'sm' => 'w-4 h-4', 'md' => 'w-4 h-4', 'lg' => 'w-[18px] h-[18px]'];

$classes = ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']);
$iconClass = ($iconSizes[$size] ?? $iconSizes['md']) . ' shrink-0';
@endphp

<{{ $tag }} {{ $attributes->merge(['class' => "inline-flex items-center justify-center font-medium whitespace-nowrap transition-[background-color,border-color,color,filter] duration-150 disabled:opacity-45 disabled:pointer-events-none $classes"]) }}>
    @if($icon)
        <x-icon :name="$icon" :class="$iconClass" />
    @endif
    {{ $slot }}
    @if($iconRight)
        <x-icon :name="$iconRight" :class="$iconClass" />
    @endif
</{{ $tag }}>
