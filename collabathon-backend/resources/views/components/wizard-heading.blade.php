@props(['step', 'of', 'title', 'subtitle' => null])

<header class="pb-1">
    <p class="text-[11px] font-medium text-primary uppercase tracking-[0.08em] nums">Step {{ $step }} of {{ $of }}</p>
    <h2 class="text-[15px] font-semibold text-ink tracking-[-0.01em] mt-1">{{ $title }}</h2>
    @if($subtitle)
        <p class="text-[12.5px] text-ink-3 mt-1 max-w-[70ch] leading-relaxed">{{ $subtitle }}</p>
    @endif
</header>
