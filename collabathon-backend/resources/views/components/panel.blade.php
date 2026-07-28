@props(['title' => null, 'subtitle' => null, 'flush' => false, 'padded' => false])

<section {{ $attributes->merge(['class' => 'bg-panel border border-line rounded-xl shadow-card flex flex-col min-w-0']) }}>
    @if($title || isset($actions))
        <header class="px-5 py-3.5 border-b border-line-soft flex flex-wrap items-center justify-between gap-x-4 gap-y-2 shrink-0">
            <div class="min-w-0">
                @if($title)
                    <h2 class="text-[13.5px] font-semibold text-ink tracking-[-0.01em]">{{ $title }}</h2>
                @endif
                @if($subtitle)
                    <p class="text-[12px] text-ink-3 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2 shrink-0">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class([
        'min-w-0 flex-1',
        'px-5 py-4' => $padded,
        'overflow-hidden rounded-b-xl' => ! $flush,
    ])>
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="px-5 py-3 border-t border-line-soft shrink-0">{{ $footer }}</footer>
    @endisset
</section>
