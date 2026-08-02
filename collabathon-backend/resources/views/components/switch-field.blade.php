@props(['label', 'name', 'hint' => null, 'checked' => false])

{{-- x-toggle is Alpine-only and submits nothing. This is the form-bound variant: a real
     checkbox, visually hidden, driving the track/knob through `peer-checked`. The paired
     hidden input means an unchecked box still posts "0" instead of dropping the key.

     The knob moves via `peer-checked:[&>span]:…` on the track — `peer-checked` alone is a
     sibling combinator, and the knob is a child of the track, not a sibling of the input. --}}
{{-- old() after a failed submit, then :checked, then the edit screen's $formRecord map. --}}
@php $isOn = (bool) old($name, $checked ?: data_get($formRecord ?? null, $name)); @endphp

<div {{ $attributes->only('class') }}>
    <input type="hidden" name="{{ $name }}" value="0">

    <label for="{{ $name }}" class="flex items-start gap-3 cursor-pointer">
        <span class="relative inline-flex shrink-0 mt-0.5">
            <input id="{{ $name }}" name="{{ $name }}" type="checkbox" value="1" @checked($isOn)
                   class="peer sr-only" {{ $attributes->except('class') }}>
            <span class="block w-[38px] h-[21px] bg-line p-[2px] transition-colors duration-150
                         peer-checked:bg-primary
                         peer-focus-visible:ring-[3px] peer-focus-visible:ring-primary-ring
                         peer-checked:[&>span]:translate-x-[17px]">
                <span class="block w-[17px] h-[17px] bg-white shadow-sm transition-transform duration-150"></span>
            </span>
        </span>

        <span class="min-w-0">
            <span class="block text-[12.5px] font-medium text-ink">{{ $label }}</span>
            @if($hint)
                <span class="block text-[11.5px] text-ink-3 mt-0.5">{{ $hint }}</span>
            @endif
        </span>
    </label>
</div>
