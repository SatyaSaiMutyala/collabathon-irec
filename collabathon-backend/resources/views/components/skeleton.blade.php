@props(['w' => 'w-full', 'h' => 'h-3', 'class' => ''])

{{--
    One shimmering placeholder block — the primitive every admin skeleton is built from.

        <x-skeleton w="w-1/2" h="h-4" />
        <x-skeleton w="w-9" h="h-9" class="rounded-avatar" />

    Sizing is Tailwind classes rather than props with units, so a skeleton inherits the
    same responsive vocabulary as the thing it stands in for (`w-1/2 md:w-1/3`) instead
    of a fixed pixel guess that only matches at one breakpoint.

    The sweep itself is `.skeleton` in resources/css/app.css — defined there rather than
    inline so it exists once and honours prefers-reduced-motion.
--}}
<span aria-hidden="true" {{ $attributes->merge(['class' => "skeleton block $w $h $class"]) }}></span>
