@props(['title', 'subtitle' => null])

<div class="flex items-start justify-between gap-4 mb-6">
    <div>
        <h1 class="text-[20px] font-semibold text-navy">{{ $title }}</h1>
        @if($subtitle)
            <p class="text-[13px] text-text-secondary mt-1">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex items-center gap-3 shrink-0">{{ $actions }}</div>
    @endisset
</div>
