@props(['icon' => 'inbox', 'title' => 'Nothing here yet', 'description' => null])

<div class="px-6 py-14 flex flex-col items-center text-center">
    <div class="w-11 h-11 rounded-xl bg-canvas border border-line flex items-center justify-center text-ink-3 mb-3.5">
        <x-icon :name="$icon" class="w-5 h-5" />
    </div>
    <p class="text-[13.5px] font-medium text-ink">{{ $title }}</p>
    @if($description)
        <p class="text-[12.5px] text-ink-3 mt-1 max-w-[36ch] leading-relaxed">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
