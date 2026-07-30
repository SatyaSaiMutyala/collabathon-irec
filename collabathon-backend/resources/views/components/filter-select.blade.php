@props([
    'name',
    'options' => [],      // value => label, or a flat list
    'placeholder' => 'All',
    'icon' => 'filter',
])

@php
    $current = request($name);
    $isSet = $current !== null && $current !== '';

    // Normalise a flat list into value => label. A keyed map ([5 => 'Skyline Realty'])
    // keeps its key — is_int($key) alone would treat it as a flat list and filter by
    // the label instead of the id, which silently matches nothing.
    $optionList = $options instanceof \Illuminate\Support\Collection ? $options->all() : (array) $options;
    $normalised = array_is_list($optionList)
        ? array_combine($optionList, $optionList)
        : $optionList;
@endphp

{{-- Submits on change; carries every other active param so filters compose.
     `page` is dropped so changing a filter returns to page 1. --}}
<form method="GET" action="{{ url()->current() }}" class="contents">
    @foreach(request()->except([$name, 'page']) as $key => $value)
        @if(is_scalar($value))
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach

    <label class="relative">
        <span class="sr-only">{{ $placeholder }}</span>
        <x-icon :name="$icon" class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
        <select name="{{ $name }}" onchange="this.form.submit()"
                @class([
                    'h-8 pl-7 pr-7 rounded-lg text-[12.5px] appearance-none cursor-pointer transition-colors focus:outline-none',
                    'bg-primary-soft text-primary-dark ring-1 ring-inset ring-primary-ring' => $isSet,
                    'bg-canvas text-ink-2 hover:bg-line-soft' => ! $isSet,
                ])>
            <option value="">{{ $placeholder }}</option>
            @foreach($normalised as $value => $label)
                <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-icon name="chevron-down" class="w-3.5 h-3.5 absolute right-2 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
    </label>
</form>
