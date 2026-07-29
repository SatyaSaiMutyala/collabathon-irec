@props([
    'label' => null,
    'placeholder' => '',
    'value' => '',
    'type' => 'text',
    'hint' => null,
    'required' => false,
    'icon' => null,
    'name' => null,
])

@php
$id = $name ?? 'f-' . \Illuminate\Support\Str::slug($label ?? uniqid());
$inputClass = 'w-full h-10 rounded-lg bg-panel border border-line text-[13.5px] text-ink placeholder:text-ink-3
    focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary/15 transition-[border-color,box-shadow]';
@endphp

<div {{ $attributes->only('class') }}>
    @if($label)
        <label for="{{ $id }}" class="flex items-center gap-1 text-[12.5px] font-medium text-ink mb-1.5">
            {{ $label }}
            @if($required)<span class="text-danger" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <div class="relative">
        @if($icon)
            <x-icon :name="$icon" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
        @endif

        @if($type === 'textarea')
            <textarea id="{{ $id }}" name="{{ $id }}" rows="3" placeholder="{{ $placeholder }}"
                @if($required) required @endif
                {{ $attributes->except('class')->merge(['class' => str_replace('h-10', 'h-auto py-2.5', $inputClass) . ' px-3.5 resize-y']) }}>{{ $value }}</textarea>
        @else
            <input id="{{ $id }}" name="{{ $id }}" type="{{ $type }}" placeholder="{{ $placeholder }}" value="{{ $value }}"
                @if($required) required @endif
                {{ $attributes->except('class')->merge(['class' => $inputClass . ' ' . ($icon ? 'pl-9 pr-3.5' : 'px-3.5')]) }}>
        @endif
    </div>

    @if($hint)
        <p class="text-[11.5px] text-ink-3 mt-1.5">{{ $hint }}</p>
    @endif
</div>
