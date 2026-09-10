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

    // Whether the file already on record can be shown as a picture. Decided from the
    // extension because that is all the stored path gives us — there is no mime column.
    $currentIsImage = $current && in_array(
        strtolower(pathinfo($current, PATHINFO_EXTENSION)),
        ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'],
        true,
    );
@endphp

{{-- `files` holds File objects, not names, because in multiple mode the selection is
     rewritten back onto the input (see sync()) and only real File objects can be.

     A native multi-file input replaces its whole selection on every pick and offers no way
     to drop one file, so picking a fifth image meant re-picking all five, and a mis-picked
     one meant starting over. Holding the list here and assigning it back through a
     DataTransfer is what makes "add more" append and "remove" possible at all. It also
     fixes a quieter native annoyance: opening the picker and cancelling clears the input,
     and sync() puts the accumulated list straight back. --}}
<div {{ $attributes->only('class') }} x-data="{
    files: [],
    /**
     * One entry per file: the File itself, plus a thumbnail URL when it is an image.
     * The URL is minted once here rather than in the markup — an object URL built inside
     * an x-bind would be rebuilt on every re-render and leak one blob per keystroke.
     */
    entryFor(file) {
        return { file, url: file.type.startsWith('image/') ? URL.createObjectURL(file) : null };
    },
    /** Push our list back onto the input, since that list is what actually gets posted. */
    sync() {
        const transfer = new DataTransfer();
        this.files.forEach(entry => transfer.items.add(entry.file));
        this.$refs.input.files = transfer.files;
    },
    release(entry) {
        if (entry && entry.url) { URL.revokeObjectURL(entry.url); }
    },
    picked(event) {
        const incoming = Array.from(event.target.files);

        if (! {{ $multiple ? 'true' : 'false' }}) {
            this.files.forEach(entry => this.release(entry));
            this.files = incoming.map(file => this.entryFor(file));
            return;
        }

        // Same name and size twice over is the same file picked twice — the browser has
        // no notion of that across two separate picks, so it would otherwise be uploaded
        // and stored as a duplicate.
        incoming.forEach(file => {
            if (! this.files.some(held => held.file.name === file.name && held.file.size === file.size)) {
                this.files.push(this.entryFor(file));
            }
        });

        this.sync();
    },
    remove(index) {
        this.release(this.files.splice(index, 1)[0]);
        this.sync();
    },
}">
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

        @if($multiple)
            {{-- The names themselves are listed underneath, so this line only has to say
                 how many there are and stay an obvious way back into the picker. --}}
            <span class="text-[13px] text-ink min-w-0" x-show="files.length" x-cloak
                  x-text="files.length === 1 ? '1 file selected — choose more…' : files.length + ' files selected — choose more…'"></span>
        @else
            {{-- The picked filename wraps rather than ellipsing. Half these fields sit in a
                 two-column grid, and `truncate` cut a real name — "Screenshot 2026-08-05 at
                 7.40.40 PM.png" — down to "Screenshot 2026-08-0…", which tells the user
                 nothing about which file they just chose. The control is a growable dropzone,
                 so a second line costs less than the lost information. `break-words`, not
                 `break-all`: it wraps at the spaces a screenshot name already has, and only
                 breaks mid-word for a name that has none — `WhatsApp_Image_2026-08-05.jpeg`
                 would otherwise overflow the row. --}}
            <template x-if="files[0]?.url">
                <img x-bind:src="files[0].url" alt=""
                     class="w-8 h-8 rounded object-cover border border-line-soft shrink-0">
            </template>
            <span class="text-[13px] text-ink min-w-0 break-words leading-snug" x-show="files.length" x-cloak
                  x-bind:title="files[0]?.file.name" x-text="files[0]?.file.name"></span>
        @endif

        {{-- Transparent and stretched over the label rather than `sr-only`.

             sr-only is `position:absolute` with a 1px box and no positioned ancestor, so
             the input sat at its static position — below the visible row, and often below
             the fold. Opening the picker focuses it, and the browser scrolls that 1px box
             into view: the page jumped ~250px the moment the file dialog appeared.

             Covering the label instead means the focused box is the box the user just
             clicked, so scrolling it into view is a no-op. It stays a real focusable
             input, so keyboard and screen-reader access are unchanged. --}}
        <input id="{{ $id }}" name="{{ $name }}" type="file" x-ref="input"
               @if($multiple) multiple @endif
               @if($accept) accept="{{ $accept }}" @endif
               @if($required) required @endif
               x-on:change="picked($event)"
               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
               {{ $attributes->except('class') }}>
    </label>

    @if($multiple)
        {{-- One row per file, each removable. Sits outside the label above: a click inside
             that label reopens the file picker, which is the last thing a remove button
             should do. --}}
        <ul class="mt-1.5 space-y-1" x-show="files.length" x-cloak>
            <template x-for="(entry, index) in files" :key="entry.file.name + entry.file.size">
                <li class="flex items-center gap-2 rounded-lg bg-canvas border border-line-soft px-2.5 py-1.5">
                    {{-- Thumbnail for an image, the generic file glyph for anything else
                         (a PDF cannot be previewed without a renderer). --}}
                    <template x-if="entry.url">
                        <img x-bind:src="entry.url" alt=""
                             class="w-9 h-9 rounded object-cover border border-line-soft shrink-0">
                    </template>
                    <template x-if="! entry.url">
                        <span class="w-9 h-9 rounded bg-panel border border-line-soft grid place-items-center shrink-0">
                            <x-icon name="download" class="w-3.5 h-3.5 text-ink-3" />
                        </span>
                    </template>
                    <span class="flex-1 min-w-0 text-[12px] text-ink-2 break-words leading-snug"
                          x-bind:title="entry.file.name" x-text="entry.file.name"></span>
                    <span class="text-[11px] text-ink-3 nums shrink-0"
                          x-text="entry.file.size > 1048576
                              ? (entry.file.size / 1048576).toFixed(1) + ' MB'
                              : Math.max(1, Math.round(entry.file.size / 1024)) + ' KB'"></span>
                    <button type="button" x-on:click="remove(index)"
                            class="text-ink-3 hover:text-danger rounded-md p-1 -m-1 shrink-0 transition-colors"
                            x-bind:aria-label="'Remove ' + entry.file.name">
                        <x-icon name="x" class="w-3.5 h-3.5" />
                    </button>
                </li>
            </template>
        </ul>
    @endif

    {{-- A file input cannot be pre-filled, so on an edit the stored file is shown beside it.
         Choosing a new one replaces it; leaving the input empty keeps it. --}}
    {{-- An image on record is shown as a thumbnail, not just named: "On file:
         cover-4821.jpg" tells an admin nothing about which picture they are replacing.
         Hidden as soon as a new file is picked, since that pick is the replacement. --}}
    @if($current)
        <div class="flex items-center gap-2 mt-1.5" @if($currentIsImage) x-show="! files.length" x-cloak @endif>
            @if($currentIsImage)
                <a href="{{ \App\Support\FileStorage::url($current) }}" target="_blank" rel="noopener" class="shrink-0">
                    <img src="{{ \App\Support\FileStorage::url($current) }}" alt=""
                         class="w-11 h-11 rounded-lg object-cover border border-line-soft">
                </a>
            @else
                <x-icon name="check" class="w-3.5 h-3.5 text-success shrink-0" />
            @endif
            <p class="text-[11.5px] text-ink-3 min-w-0">
                On file:
                <a href="{{ \App\Support\FileStorage::url($current) }}" target="_blank" rel="noopener"
                   class="text-ink-2 hover:text-ink underline decoration-line underline-offset-2 break-words">
                    {{ basename($current) }}
                </a>
            </p>
        </div>
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
