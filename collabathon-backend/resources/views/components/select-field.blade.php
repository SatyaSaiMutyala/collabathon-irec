@props(['label' => null, 'options' => [], 'required' => false, 'hint' => null, 'name' => null, 'selected' => null])

@php
$id = $name ?? 'f-' . \Illuminate\Support\Str::slug($label ?? uniqid());
// old() after a failed submit, then an explicit :selected, then the edit screen's
// $formRecord map — see the note in field.blade.php.
$currentValue = old($id, $selected ?? data_get($formRecord ?? null, $id));
$hasError = $errors->has($id);

// A flat list (['Residential', 'Commercial']) submits the label as its value; a keyed
// map ([2 => 'Admin'], ['draft' => 'Draft']) must submit its key. array_is_list() is
// what separates them — is_int($key) alone reads an id-keyed map as a flat list and
// submits the label, which then fails every exists: rule.
$optionList = $options instanceof \Illuminate\Support\Collection ? $options->all() : (array) $options;
$isFlatList = array_is_list($optionList);
$toneClass = $hasError
    ? 'border-danger focus:border-danger focus:ring-danger-ring'
    : 'border-line focus:border-primary focus:ring-primary-ring';
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
            @if($required) required @endif
            @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->except('class')->merge(['class' => 'w-full h-10 pl-3.5 pr-9 rounded-lg bg-panel border text-[13.5px] text-ink appearance-none
                focus:outline-none focus:ring-[3px] transition-[border-color,box-shadow] ' . $toneClass]) }}>
            {{ $slot }}
            @foreach($optionList as $key => $option)
                @php $optionValue = $isFlatList ? $option : $key; @endphp
                <option value="{{ $optionValue }}" @selected((string) $currentValue === (string) $optionValue)>{{ $option }}</option>
            @endforeach
        </select>
        <x-icon name="chevron-down" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
    </div>

    @error($id)
        <p id="{{ $id }}-error" class="flex items-start gap-1.5 text-[11.5px] text-danger mt-1.5">
            <x-icon name="x" class="w-3.5 h-3.5 shrink-0 mt-px" />
            <span>{{ $message }}</span>
        </p>
    @elseif($hint)
        <p class="text-[11.5px] text-ink-3 mt-1.5">{{ $hint }}</p>
    @enderror
</div>
