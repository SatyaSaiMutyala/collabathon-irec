@props([
    'name',
    'placeholder' => 'Filter…',
    'icon' => 'filter',
])

@php
    $current = request($name);
    $isSet = $current !== null && $current !== '';
@endphp

{{-- Same shape as filter-select — a small self-submitting form carrying every other
     active param — except a text input submits on Enter natively, so no onchange JS
     is needed the way a <select> needs one. --}}
<form method="GET" action="{{ url()->current() }}" class="contents">
    @foreach(request()->except([$name, 'page']) as $key => $value)
        @if(is_scalar($value))
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach

    <label class="relative">
        <span class="sr-only">{{ $placeholder }}</span>
        <x-icon :name="$icon" class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
        <input type="text" name="{{ $name }}" value="{{ $current }}" placeholder="{{ $placeholder }}"
               @class([
                   'h-8 w-32 pl-7 pr-2.5 rounded-lg text-[12.5px] transition-colors focus:outline-none',
                   'bg-primary-soft text-primary-dark ring-1 ring-inset ring-primary-ring' => $isSet,
                   'bg-canvas text-ink-2 hover:bg-line-soft placeholder:text-ink-3' => ! $isSet,
               ])>
    </label>
</form>
