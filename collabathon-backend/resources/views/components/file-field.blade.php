@props([
    'label' => null,
    'name',
    'hint' => null,
    'accept' => null,
    'multiple' => false,
    'required' => false,
    'icon' => 'download',
    'current' => null,   // storage path already on record — edit forms pass this
])

{{-- Native file inputs cannot be repopulated by old(), so a failed submit always loses the
     selection. The filename readout is Alpine-local and exists to make that obvious rather
     than leave the control looking filled when it is empty. --}}
@php
    // Str::slug() would rewrite `cover_image` to `cover-image`, leaving the id out of step
    // with the field name and with x-field, which uses the name as-is. Only the array
    // brackets need stripping to make a valid id.
    $id = str_replace(['[]', '[', ']'], ['', '-', ''], $name);
    // Array inputs (`gallery[]`) report errors on the base key and on each index.
    $errorKey = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $hasError = $errors->has($errorKey) || $errors->has($errorKey . '.*');
    $message = $errors->first($errorKey) ?: $errors->first($errorKey . '.*');
@endphp

<div {{ $attributes->only('class') }} x-data="{ files: [] }">
    @if($label)
        <label for="{{ $id }}" class="flex items-center gap-1 text-[12.5px] font-medium text-ink mb-1.5">
            {{ $label }}
            @if($required)<span class="text-danger" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <label for="{{ $id }}"
           @class([
               'flex items-center gap-2.5 w-full min-h-10 px-3.5 py-2 rounded-lg bg-panel border border-dashed cursor-pointer transition-colors',
               'border-danger' => $hasError,
               'border-line hover:border-primary hover:bg-canvas' => ! $hasError,
           ])>
        <x-icon :name="$icon" class="w-4 h-4 text-ink-3 shrink-0" />

        <span class="text-[13px] text-ink-3 truncate" x-show="! files.length">
            {{ $multiple ? 'Choose files…' : 'Choose a file…' }}
        </span>
        <span class="text-[13px] text-ink truncate" x-show="files.length" x-cloak x-text="files.join(', ')"></span>

        <input id="{{ $id }}" name="{{ $name }}" type="file"
               @if($multiple) multiple @endif
               @if($accept) accept="{{ $accept }}" @endif
               @if($required) required @endif
               @change="files = Array.from($event.target.files).map(f => f.name)"
               class="sr-only"
               {{ $attributes->except('class') }}>
    </label>

    {{-- A file input cannot be pre-filled, so on an edit the stored file is shown beside it.
         Choosing a new one replaces it; leaving the input empty keeps it. --}}
    @if($current)
        <p class="flex items-center gap-1.5 text-[11.5px] text-ink-3 mt-1.5">
            <x-icon name="check" class="w-3.5 h-3.5 text-success shrink-0" />
            <span class="truncate">
                On file:
                <a href="{{ Storage::disk('public')->url($current) }}" target="_blank" rel="noopener"
                   class="text-ink-2 hover:text-ink underline decoration-line underline-offset-2">
                    {{ basename($current) }}
                </a>
            </span>
        </p>
    @endif

    @if($hasError)
        <p class="flex items-start gap-1.5 text-[11.5px] text-danger mt-1.5">
            <x-icon name="x" class="w-3.5 h-3.5 shrink-0 mt-px" />
            <span>{{ $message }}</span>
        </p>
    @elseif($hint)
        <p class="text-[11.5px] text-ink-3 mt-1.5">{{ $current ? 'Choose a file to replace it.' : $hint }}</p>
    @endif
</div>
