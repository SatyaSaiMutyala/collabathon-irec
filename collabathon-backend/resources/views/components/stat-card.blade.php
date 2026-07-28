@props(['icon', 'label', 'value'])

<div class="bg-white border border-border rounded-xl px-5 py-4 flex items-center gap-4">
    <div class="w-10 h-10 rounded-lg bg-primary-soft text-primary-dark flex items-center justify-center shrink-0">
        <x-icon :name="$icon" class="w-5 h-5" />
    </div>
    <div class="min-w-0">
        <p class="text-2xl font-semibold text-navy leading-none">{{ $value }}</p>
        <p class="text-[13px] text-text-secondary mt-1.5">{{ $label }}</p>
    </div>
</div>
