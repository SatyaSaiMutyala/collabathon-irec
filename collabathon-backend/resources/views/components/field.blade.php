@props([
    'label' => null,
    'placeholder' => '',
    'value' => '',
    'type' => 'text',
    'hint' => null,
    'required' => false,
    'icon' => null,
    'name' => null,
    'rows' => 3,         // textarea only
    'toggle' => false,   // password inputs: render a show/hide eye button
])

@php
$id = $name ?? 'f-' . \Illuminate\Support\Str::slug($label ?? uniqid());

/**
 * Value precedence: old() after a failed submit, then an explicit :value, then $formRecord.
 *
 * $formRecord is a flat [field name => value] map an edit screen passes to the view (see
 * PropertyController::toFormValues). It means one form template serves both create and
 * edit without 60 fields each wiring their own :value. A Carbon slips through the cast
 * layer as "Y-m-d H:i:s", which a date input silently rejects, so trim it here.
 */
$fallback = $value !== '' && $value !== null
    ? $value
    : data_get($formRecord ?? null, $id);

if ($fallback instanceof \DateTimeInterface) {
    $fallback = $fallback->format($type === 'datetime-local' ? 'Y-m-d\TH:i' : 'Y-m-d');
}

$currentValue = old($id, $fallback);
$hasError = $errors->has($id);
// Invalid fields swap the neutral border/ring for the danger tone so the failure is
// visible on the field itself, not just in the page-level summary.
$toneClass = $hasError
    ? 'border-danger focus:border-danger focus:ring-danger-ring'
    : 'border-line focus:border-primary focus:ring-primary-ring';
$inputClass = 'w-full h-10 rounded-lg bg-panel border text-[13.5px] text-ink placeholder:text-ink-3
    focus:outline-none focus:ring-[3px] transition-[border-color,box-shadow] ' . $toneClass;
@endphp

<div {{ $attributes->only('class') }}>
    @if($label)
        <label for="{{ $id }}" class="flex items-center gap-1 text-[12.5px] font-medium text-ink mb-1.5">
            {{ $label }}
            @if($required)<span class="text-danger" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <div class="relative" @if($toggle) x-data="{ reveal: false }" @endif>
        @if($icon)
            <x-icon :name="$icon" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-3 pointer-events-none" />
        @endif

        @if($toggle)
            {{-- Alpine owns `type` here so the value stays in one input across toggles. --}}
            <input id="{{ $id }}" name="{{ $id }}" :type="reveal ? 'text' : 'password'" type="password"
                placeholder="{{ $placeholder }}" value="{{ $currentValue }}"
                @if($required) required @endif
                @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
                {{ $attributes->except('class')->merge(['class' => $inputClass . ' ' . ($icon ? 'pl-9' : 'pl-3.5') . ' pr-10 font-mono tracking-tight']) }}>

            <button type="button" @click="reveal = ! reveal" tabindex="-1"
                    :aria-label="reveal ? 'Hide password' : 'Show password'"
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-3 hover:text-ink p-1.5 rounded-md transition-colors">
                <x-icon name="eye" class="w-4 h-4" x-show="! reveal" />
                <x-icon name="x" class="w-4 h-4" x-show="reveal" x-cloak />
            </button>
        @elseif($type === 'textarea')
            <textarea id="{{ $id }}" name="{{ $id }}" rows="{{ $rows }}" placeholder="{{ $placeholder }}"
                @if($required) required @endif
                @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
                {{ $attributes->except('class')->merge(['class' => str_replace('h-10', 'h-auto py-2.5', $inputClass) . ' px-3.5 resize-y']) }}>{{ $currentValue }}</textarea>
        @else
            <input id="{{ $id }}" name="{{ $id }}" type="{{ $type }}" placeholder="{{ $placeholder }}" value="{{ $currentValue }}"
                @if($required) required @endif
                @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
                {{ $attributes->except('class')->merge(['class' => $inputClass . ' ' . ($icon ? 'pl-9 pr-3.5' : 'px-3.5')]) }}>
        @endif
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
