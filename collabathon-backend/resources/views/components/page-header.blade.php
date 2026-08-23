@props(['title', 'subtitle' => null])

{{--
    Stacks below `lg`, not `sm` — a page with several action buttons (CP's Approval
    queue/Add/Bulk upload/Export is 4) plus a full-sentence subtitle was still going
    side-by-side as early as `sm` (640px), where the buttons' own fixed width
    (`shrink-0`, they never give ground) left the title column so little room that a
    normal-length subtitle wrapped to 4-5 lines instead of the 1-2 it needs. `lg` gives
    the subtitle real width to work with on every screen this page actually gets
    viewed at before the buttons are allowed to fight it for space.
--}}
<div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 lg:gap-6 mb-5 sm:mb-6">
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
