@props(['label' => null, 'name', 'options' => [], 'hint' => null, 'columns' => 3, 'selected' => []])

{{-- Multi-select as a checkbox grid — used where the client's sheet enumerates the choices
     (amenities, payment plans, approving authorities). Posts `name[]`, which the controller
     stores straight into the matching JSON column. --}}
@php
    // old() after a failed submit, then :selected, then the edit screen's $formRecord map.
    $current = old($name, $selected ?: data_get($formRecord ?? null, $name));
    $checked = collect($current ?: [])->map(fn ($v) => (string) $v)->all();
    $optionList = $options instanceof \Illuminate\Support\Collection ? $options->all() : (array) $options;
    $gridClass = [2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-2 lg:grid-cols-3', 4 => 'sm:grid-cols-2 lg:grid-cols-4'][$columns] ?? 'sm:grid-cols-2';
@endphp

<div {{ $attributes->only('class') }}>
    @if($label)
        <p class="text-[12.5px] font-medium text-ink mb-2">{{ $label }}</p>
    @endif

    <div class="grid grid-cols-1 {{ $gridClass }} gap-x-4 gap-y-2">
        @foreach($optionList as $key => $option)
            @php
                $value = array_is_list($optionList) ? $option : $key;
                $inputId = $name . '-' . \Illuminate\Support\Str::slug((string) $value);
            @endphp
            <label for="{{ $inputId }}" class="flex items-center gap-2 cursor-pointer group">
                <input id="{{ $inputId }}" name="{{ $name }}[]" type="checkbox" value="{{ $value }}"
                       @checked(in_array((string) $value, $checked, true))
                       class="w-4 h-4 border-line text-primary accent-primary
                              focus:ring-[3px] focus:ring-primary-ring focus:outline-none shrink-0">
                <span class="text-[12.5px] text-ink-2 group-hover:text-ink transition-colors">{{ $option }}</span>
            </label>
        @endforeach
    </div>

    @if($hint)
        <p class="text-[11.5px] text-ink-3 mt-2">{{ $hint }}</p>
    @endif
</div>
