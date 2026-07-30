@props(['icon' => null, 'tone' => 'default', 'tag' => 'button'])

@php
$tones = [
    'default' => 'text-ink-2 hover:bg-canvas hover:text-ink',
    'danger'  => 'text-danger hover:bg-danger-soft',
];
@endphp

{{-- Only default the type when the caller hasn't set one: a duplicate `type` attribute
     resolves to the first occurrence, so hard-coding it here turned every
     `type="submit"` dropdown item into an inert button that never posted its form. --}}
<{{ $tag }} @if($tag === 'button' && ! $attributes->has('type')) type="button" @endif
    role="menuitem"
    {{ $attributes->merge(['class' => 'w-full flex items-center gap-2.5 px-3 py-2 text-[13px] text-left transition-colors ' . ($tones[$tone] ?? $tones['default'])]) }}>
    @if($icon)
        <x-icon :name="$icon" class="w-4 h-4 shrink-0 opacity-70" />
    @endif
    <span class="min-w-0 truncate">{{ $slot }}</span>
</{{ $tag }}>
