@props(['label' => null, 'options' => [], 'required' => false, 'hint' => null, 'name' => null])

@php
$id = $name ?? 'f-' . \Illuminate\Support\Str::slug($label ?? uniqid());
@endphp

<div {{ $attributes->only('class') }}>
    @if($label)
        <label for="{{ $id }}" class="flex items-center gap-1 text-[12.5px] font-medium text-ink mb-1.5">
            {{ $label }}
            @if($required)<span class="text-danger" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <div class="relative">
        <select id="{{ $id }}" name="{{ $id }}"
            {{ $attributes->except('class')->merge(['class' => 'w-full h-10 pl-3.5 pr-9 rounded-lg bg-panel border border-line text-[13.5px] text-ink appearance-none
                focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary/15 transition-[border-color,box-shadow]']) }}>
            {{ $slot }}
            @foreach($options as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </select>
        <x-icon name="chevron-down" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
    </div>

    @if($hint)
        <p class="text-[11.5px] text-ink-3 mt-1.5">{{ $hint }}</p>
    @endif
</div>
