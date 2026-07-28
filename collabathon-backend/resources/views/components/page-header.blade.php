@props(['title', 'subtitle' => null])

{{-- Stacks below sm so a wide action group never squeezes the title. --}}
<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-6 mb-5 sm:mb-6">
    <div class="min-w-0">
        <h1 class="text-[19px] sm:text-[21px] font-semibold text-ink tracking-[-0.02em] leading-tight">{{ $title }}</h1>
        @if($subtitle)
            <p class="text-[13px] text-ink-2 mt-1.5 max-w-[76ch] leading-relaxed">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2.5 shrink-0">{{ $actions }}</div>
    @endisset
</div>
