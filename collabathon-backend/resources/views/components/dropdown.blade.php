@props(['align' => 'right', 'width' => 'w-56'])

@php
$alignClass = $align === 'left' ? 'left-0 origin-top-left' : 'right-0 origin-top-right';
@endphp

<div x-data="{ open: false }" @keydown.escape.window="open = false" class="relative">
    <div @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="menu">
        {{ $trigger }}
    </div>

    <div x-show="open"
         x-cloak
         @click.outside="open = false"
         x-transition:enter="transition ease-out duration-120"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-90"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         role="menu"
         class="absolute z-40 mt-2 {{ $alignClass }} {{ $width }} bg-panel border border-line rounded-xl shadow-pop py-1.5">
        {{ $slot }}
    </div>
</div>
