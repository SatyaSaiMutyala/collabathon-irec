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

    {{-- `relative`, so the absolutely-positioned input below resolves against this label
         rather than against the page. --}}
    <label for="{{ $id }}"
           @class([
               'relative flex items-center gap-2.5 w-full min-h-10 px-3.5 py-2 rounded-lg bg-panel border border-dashed cursor-pointer transition-colors',
               'border-danger' => $hasError,
               'border-line hover:border-primary hover:bg-canvas' => ! $hasError,
           ])>
        <x-icon :name="$icon" class="w-4 h-4 text-ink-3 shrink-0" />

        {{-- min-w-0 is what makes `truncate` work on a flex child: without it the item's
             min-width resolves to its content, so a long placeholder widens the row instead
             of ellipsing inside it. --}}
        <span class="text-[13px] text-ink-3 truncate min-w-0" x-show="! files.length">
            {{ $multiple ? 'Choose files…' : 'Choose a file…' }}
        </span>

        {{-- The picked filename wraps rather than ellipsing. Half these fields sit in a
             two-column grid, and `truncate` cut a real name — "Screenshot 2026-08-05 at
             7.40.40 PM.png" — down to "Screenshot 2026-08-0…", which tells the user
             nothing about which file they just chose. The control is a growable dropzone,
             so a second line costs less than the lost information. `break-words`, not
             `break-all`: it wraps at the spaces a screenshot name already has, and only
             breaks mid-word for a name that has none — `WhatsApp_Image_2026-08-05.jpeg`
             would otherwise overflow the row.

             A multi-file pick names the first and counts the rest: joining twenty gallery
             filenames turned the row into a paragraph. The full list stays on the title. --}}
        <span class="text-[13px] text-ink min-w-0 break-words leading-snug" x-show="files.length" x-cloak
              x-bind:title="files.join(', ')"
              x-text="files.length > 1 ? files[0] + ' + ' + (files.length - 1) + ' more' : files[0]"></span>

        {{-- Transparent and stretched over the label rather than `sr-only`.

             sr-only is `position:absolute` with a 1px box and no positioned ancestor, so
             the input sat at its static position — below the visible row, and often below
             the fold. Opening the picker focuses it, and the browser scrolls that 1px box
             into view: the page jumped ~250px the moment the file dialog appeared.

             Covering the label instead means the focused box is the box the user just
             clicked, so scrolling it into view is a no-op. It stays a real focusable
             input, so keyboard and screen-reader access are unchanged. --}}
        <input id="{{ $id }}" name="{{ $name }}" type="file"
               @if($multiple) multiple @endif
               @if($accept) accept="{{ $accept }}" @endif
               @if($required) required @endif
               @change="files = Array.from($event.target.files).map(f => f.name)"
               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
               {{ $attributes->except('class') }}>
    </label>

    {{-- A file input cannot be pre-filled, so on an edit the stored file is shown beside it.
         Choosing a new one replaces it; leaving the input empty keeps it. --}}
    @if($current)
        <p class="flex items-center gap-1.5 text-[11.5px] text-ink-3 mt-1.5">
            <x-icon name="check" class="w-3.5 h-3.5 text-success shrink-0" />
            <span class="truncate">
                On file:
                <a href="{{ \App\Support\FileStorage::url($current) }}" target="_blank" rel="noopener"
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
